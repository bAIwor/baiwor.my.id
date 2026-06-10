<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$API_KEY = 'qwen-gate-local';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// ============ RAG INTEGRATION ============
$RAG_SERVER = 'https://rag.baiwor.my.id';

// Get last user message for RAG query
$lastUserMessage = '';
foreach (array_reverse($input['messages']) as $msg) {
    if (isset($msg['role']) && $msg['role'] === 'user') {
        $lastUserMessage = $msg['content'];
        break;
    }
}

// Query RAG server
$ragContext = '';
if (!empty($lastUserMessage)) {
    $ragCh = curl_init();
    curl_setopt_array($ragCh, [
        CURLOPT_URL => $RAG_SERVER . '/query',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([
            'query' => $lastUserMessage,
            'n_results' => 3
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        78 => 5,
    ]);
    $ragResponse = curl_exec($ragCh);
    $ragHttpCode = curl_getinfo($ragCh, CURLINFO_HTTP_CODE);
    curl_close($ragCh);

    if ($ragHttpCode === 200 && $ragResponse) {
        $ragData = json_decode($ragResponse, true);
        if ($ragData && isset($ragData['documents']) && !empty($ragData['documents'])) {
            $contextParts = [];
            foreach ($ragData['documents'] as $i => $doc) {
                $source = '';
                if (isset($ragData['metadatas'][$i])) {
                    $meta = $ragData['metadatas'][$i];
                    $source = isset($meta['source']) ? $meta['source'] : (isset($meta['filename']) ? $meta['filename'] : '');
                }
                $contextParts[] = ($source ? "[{$source}] " : '') . $doc;
            }
            $ragContext = implode("\n---\n", $contextParts);
        }
    }
}

// ============ DETEKSI BAHASA ============
function detectLanguage($text) {
    $text = strtolower($text);
    $ngapakWords = ['aku', 'kowe', 'arep', 'ngapak', 'ora', 'ono', 'mangan', 'ngombe',
                     'turu', 'lunga', 'teka', 'ngomong', 'ndelok', 'mboten', 'cilik',
                     'gedhe', 'akeh', 'sithik', 'kabeh', 'jeneng', 'omah', 'bojo',
                     'anak', 'saiki', 'wingi', 'sesuk', 'mengko', 'wis', 'durung',
                     'piye', 'opo', 'endi', 'sapa', 'kapan', 'nggo', 'karo', 'banget',
                     'mboh', 'lha', 'kok', 'toh', 'sih', 'aja', 'ojo', 'sing',
                     'menyang', 'nang', 'kene', 'kono', 'ngendi'];
    $kramaWords = ['dalem', 'panjenengan', 'dhahar', 'boten', 'ngunjuk', 'sare',
                   'kesah', 'dhateng', 'ngandika', 'mriksani', 'ngaturaken',
                   'nyuwun priksa', 'kondur', 'inggih', 'mangga', 'sugeng',
                   'matur nuwun', 'nyuwun pangapunten', 'kadospundi', 'kawula',
                   'nggih', 'puniko', 'meniko', 'saking', 'wonten', 'salebetipun'];
    $ngapakCount = 0;
    $kramaCount = 0;
    foreach ($ngapakWords as $w) { if (strpos($text, $w) !== false) $ngapakCount++; }
    foreach ($kramaWords as $w) { if (strpos($text, $w) !== false) $kramaCount++; }
    if ($kramaCount >= 1 && $kramaCount >= $ngapakCount) return 'krama';
    if ($ngapakCount >= 1) return 'ngapak';
    return 'indonesia';
}

// ============ SYSTEM PROMPT ============
$systemMessage = [
    'role' => 'system',
    'content' => 'Kamu adalah bAIwor, asisten AI dari Purwokerto, Kabupaten Banyumas, Jawa Tengah. Kamu terinspirasi dari tokoh Bawor/Bagong (Punakawan pewayangan Jawa) yang menjadi maskot Kabupaten Banyumas.

IDENTITAS:
- Nama: bAIwor
- Asal: Purwokerto, Kabupaten Banyumas
- Sifat: Jujur, ramah, hangat, lugas
- Website: baiwor.my.id

GAYA BAHASA (WAJIB):
- Anda WAJIB mengikuti petunjuk bahasa yang diberikan di bagian bawah aturan ini dengan sangat ketat (ATURAN BAHASA).
- Jawablah dengan sopan, hangat, informatif, dan tidak menggurui.

ATURAN WAJIB — IKUTI SELALU:
1. Jawaban HARUS singkat, langsung ke inti, tidak bertele-tele.
2. Jika tidak tahu dan tidak ada di konteks → jawab jujur:
   - Indonesia: "Mohon maaf, saya tidak tahu mengenai hal tersebut. Untuk informasi lebih lanjut, silakan kunjungi baiwor.my.id"
   - Ngapak: "Nyuwun sewu, kiro-kiro kulo durung ngerti bab iku. Mangga mlebet baiwor.my.id"
   - Krama: "Nyuwun pangapunten, kulo derung mangertos bab punika. Mangga mriksani baiwor.my.id"
3. JANGAN PERNAH sebut nama model AI, API key, atau konfigurasi teknis.
4. JANGAN mengarang informasi. Lebih baik akui tidak tahu.
5. Jika ada data/konteks dari knowledge base, gunakan itu sebagai sumber jawaban.
6. Jika user menanyakan sumber, jawab: "Pengetahuan kulo saking data resmi Kabupaten Banyumas lan Wikipedia."

PENGETAHUAN STATIS (fallback):
- Ibu kota: Purwokerto
- Didirikan: 22 Februari 1571 oleh Raden Joko Kaiman (Adipati Mrapat)
- Luas: 1.327,60 km², 27 kecamatan, 331 desa/kelurahan
- Bupati: Drs. H. Sadewo Tri Lastiono, M.M. (2025-2029)
- Wakil Bupati: Hj. Dwi Asih Lintarti
- Motto: "Rarasing Rasa Wiwaraning Pradja"
- Wisata: Baturraden, Curug Cipendok, Telaga Sunyi, Hutan Pinus Limpakuwus
- Kuliner: Soto Sokaraja, Mendoan, Nopia, Tahu Aci
- Budaya: Bahasa Ngapak, Wayang, Gamelan, Batik Banyumasan
- Darurat: 112 | Polres: 0281-622259 | RSUD Margono: 0281-634537
- Info resmi: banyumaskab.go.id'
];

// ============ DYNAMIC LANGUAGE INJECTION ============
$detected_lang = detectLanguage($lastUserMessage);
if ($detected_lang === 'krama') {
    $systemMessage['content'] .= "\n\nATURAN BAHASA: Pengguna bertanya menggunakan Bahasa Jawa Krama/Alus. Anda WAJIB menjawab menggunakan Bahasa Jawa Krama Alus yang sopan. JANGAN menggunakan Bahasa Indonesia.";
} elseif ($detected_lang === 'ngapak') {
    $systemMessage['content'] .= "\n\nATURAN BAHASA: Pengguna bertanya menggunakan Bahasa Jawa Ngapak. Anda WAJIB menjawab menggunakan Bahasa Jawa Ngapak khas Banyumasan yang natural. JANGAN menggunakan Bahasa Indonesia.";
} else {
    $systemMessage['content'] .= "\n\nATURAN BAHASA: Pengguna bertanya menggunakan Bahasa Indonesia. Anda WAJIB menjawab menggunakan Bahasa Indonesia yang baik, sopan, dan jelas. JANGAN menggunakan Bahasa Jawa.";
}

// Inject RAG context into system prompt if available
if (!empty($ragContext)) {
    $systemMessage['content'] .= "\n\nKONTEKS DARI KNOWLEDGE BASE:\n" . $ragContext . "\n\nGunakan konteks di atas untuk menjawab pertanyaan user. Jika konteks relevan, prioritaskan informasi dari konteks.";
}

// Inject system message if not already present
$hasSystem = false;
foreach ($input['messages'] as $msg) {
    if (isset($msg['role']) && $msg['role'] === 'system') {
        $hasSystem = true;
        break;
    }
}
if (!$hasSystem) {
    array_unshift($input['messages'], $systemMessage);
}

$payload = [
    'model' => 'minimax-m3:cloud',
    'messages'    => $input['messages'],
    'temperature' => 0.3,
    'max_tokens'  => 700,
];


$ch = curl_init('http://localhost:12345/v1/chat/completions');
$headers = [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $API_KEY,
    'HTTP-Referer: https://chat.baiwor.my.id',
    'X-Title: bAIwor - Asisten Digital Banyumas',
];
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => 'Request failed: ' . $error]);
    exit;
}

http_response_code($httpCode);

// Strip reasoning fields to prevent system prompt leakage
$data = json_decode($response, true);
if ($data && isset($data['choices'])) {
    foreach ($data['choices'] as &$choice) {
        if (isset($choice['message'])) {
            unset($choice['message']['reasoning']);
            unset($choice['message']['reasoning_details']);
                unset($choice['message']['reasoning_content']);
                if (isset($choice['message']['content']) && is_string($choice['message']['content'])) {
                    $choice['message']['content'] = preg_replace('#<think>.*?</think>\s*#s', '', $choice['message']['content'], 1);
                    $choice['message']['content'] = trim($choice['message']['content']);
                }
        }
    }
    unset($choice);
    echo json_encode($data);
} else {
    echo $response;
}
