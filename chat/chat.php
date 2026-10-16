<?php
header('Content-Type: application/json');

// ============ RESTRICTED CORS ============
$allowedOrigins = [
    'https://chat.baiwor.my.id',
    'https://baiwor.my.id',
    'https://www.baiwor.my.id'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
} elseif (empty($origin)) {
    header('Access-Control-Allow-Origin: https://chat.baiwor.my.id');
} elseif (preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
} else {
    http_response_code(403);
    echo json_encode(['error' => 'Origin not allowed']);
    exit;
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Vary: Origin');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$API_KEY = ''; // Ollama lokal tidak butuh key

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['messages']) || !is_array($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// Batasi riwayat pesan maksimal 20 percakapan terakhir
if (count($input['messages']) > 20) {
    $input['messages'] = array_slice($input['messages'], -20);
}

// Batasi panjang pesan user (maksimal 1000 karakter per pesan)
foreach ($input['messages'] as &$m) {
    if (isset($m['content']) && is_string($m['content'])) {
        if (mb_strlen($m['content']) > 1000) {
            $m['content'] = mb_substr($m['content'], 0, 1000);
        }
    }
}
unset($m);

// ============ RAG INTEGRATION ============
$RAG_SERVER = 'http://localhost:8100';

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
    // Word-boundary matching: mencegah false positive seperti "saja" (mengandung "aja")
    $ngapakWords = ['aku', 'kowe', 'arep', 'ngapak', 'ora', 'ono', 'mangan', 'ngombe',
                     'turu', 'lunga', 'teka', 'ngomong', 'ndelok', 'mboten', 'cilik',
                     'gedhe', 'akeh', 'sithik', 'kabeh', 'jeneng', 'omah', 'bojo',
                     'anak', 'saiki', 'wingi', 'sesuk', 'mengko', 'wis', 'durung',
                     'piye', 'opo', 'endi', 'sapa', 'kapan', 'nggo', 'karo', 'banget',
                     'mboh', 'lha', 'kok', 'toh', 'sih', 'aja', 'ojo', 'sing',
                     'menyang', 'nang', 'kene', 'kono', 'ngendi', 'wae', 'yowis',
                     'golek', 'dhewe', 'pimen', 'tekad', 'nyong', 'glepung'];
    $kramaWords = ['dalem', 'panjenengan', 'dhahar', 'boten', 'ngunjuk', 'sare',
                   'kesah', 'dhateng', 'ngandika', 'mriksani', 'ngaturaken',
                   'kondur', 'inggih', 'mangga', 'sugeng', 'kawula',
                   'menapa', 'punapa', 'kadospundi', 'pundi', 'kados pundi',
                   'matur nuwun', 'nyuwun pangapunten', 'nyuwun sewu',
                   'nggih', 'puniko', 'meniko', 'saking', 'wonten', 'salebetipun',
                   'kula', 'dados', 'sanes', 'ngantos', 'kagem', 'sekeca',
                   'mawon', 'nggeh', 'sampeyan', 'sakmenika'];
    $ngapakCount = 0;
    $kramaCount = 0;
    foreach ($ngapakWords as $w) {
        if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $text)) $ngapakCount++;
    }
    foreach ($kramaWords as $w) {
        if (strpos($text, $w) !== false) $kramaCount++;
    }
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

// Inject RAG context into system prompt if available
if (!empty($ragContext)) {
    $systemMessage['content'] .= "\n\nKONTEKS DARI KNOWLEDGE BASE:\n" . $ragContext . "\n\nGunakan konteks di atas untuk menjawab pertanyaan user. Jika konteks relevan, prioritaskan informasi dari konteks.";
}

// ATURAN BAHASA: instruksi singkat di AWAL (dominan) + detail di AKHIR
// ============ DYNAMIC LANGUAGE INJECTION ============
$detected_lang = detectLanguage($lastUserMessage);
if ($detected_lang === 'krama') {
    $systemMessage['content'] = "CRITICAL LANGUAGE RULE: You MUST answer ONLY in Javanese Krama Alus (basa Jawa krama). NEVER answer in Indonesian or Ngoko/Ngapak. The Javanese/Indonesian examples in this prompt are KNOWLEDGE, not language style examples."
        . "\n\n" . $systemMessage['content']
        . "\n\nATURAN BAHASA (PALING PENTING): Pengguna bertanya menggunakan Bahasa Jawa Krama/Alus. Anda WAJIB menjawab menggunakan Bahasa Jawa Krama Alus yang sopan. JANGAN menggunakan Bahasa Indonesia ATAU Ngapak.";
} elseif ($detected_lang === 'ngapak') {
    $systemMessage['content'] = "CRITICAL LANGUAGE RULE: You MUST answer ONLY in Banyumasan Javanese (Ngapak style). NEVER answer in standard Indonesian or Krama. The Javanese/Indonesian examples in this prompt are KNOWLEDGE, not language style examples."
        . "\n\n" . $systemMessage['content']
        . "\n\nATURAN BAHASA (PALING PENTING): Pengguna bertanya menggunakan Bahasa Jawa Ngapak. Anda WAJIB menjawab menggunakan Bahasa Jawa Ngapak khas Banyumasan yang natural. JANGAN menggunakan Bahasa Indonesia ATAU Krama.";
} else {
    $systemMessage['content'] = "CRITICAL LANGUAGE RULE: You MUST answer ONLY in standard Indonesian (Bahasa Indonesia baku). NEVER answer in Javanese — not Ngapak, not Krama. The Javanese/Indonesian examples in this prompt are KNOWLEDGE, not language style examples."
        . "\n\n" . $systemMessage['content']
        . "\n\nATURAN BAHASA (PALING PENTING): Pengguna bertanya menggunakan Bahasa Indonesia. Anda WAJIB menjawab menggunakan Bahasa Indonesia yang baik, sopan, dan jelas. JANGAN menggunakan Bahasa Jawa (Ngapak maupun Krama) sama sekali.";
}

// Hapus pesan system dari klien untuk mencegah jailbreak / manipulasi persona
foreach ($input['messages'] as $key => $msg) {
    if (isset($msg['role']) && $msg['role'] === 'system') {
        unset($input['messages'][$key]);
    }
}
// Selalu sisipkan system message resmi dari server
array_unshift($input['messages'], $systemMessage);
$input['messages'] = array_values($input['messages']);

$payload = [
    'messages'    => $input['messages'],
    'temperature' => 0.3,
    'max_tokens'  => 700,
];

// Tier 1: Google Gemini 2.5 Flash via OpenAI-compatible endpoint
$GEMINI_KEY = getenv('GEMINI_API_KEY') ?: 'AIzaSyDPQbCI04fjKWY1nnErajcY4hiw9So3WSE';
$response = null;
$httpCode = 0;
$error = '';

if (!empty($GEMINI_KEY)) {
    $payload['model'] = 'gemini-2.5-flash';
    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/openai/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $GEMINI_KEY
        ],
        CURLOPT_TIMEOUT => 25
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if (!$error && $httpCode === 200) {
        $check = json_decode($response, true);
        if ($check && !isset($check['error']) && isset($check['choices'])) {
            // Sukses via Google Gemini
        } else {
            $response = null;
        }
    } else {
        $response = null;
    }
}

// Fallback Tier 2: OpenGateway lokal VPS
if (!$response) {
    $detected_lang = detectLanguage($lastUserMessage);
    if ($detected_lang === 'indonesia') {
        $MODELS = ['gpt-oss:20b-cloud', 'gemma4:31b-cloud', 'qwen3:8b'];
    } else {
        $MODELS = ['gemma4:31b-cloud', 'gpt-oss:20b-cloud', 'qwen3:8b'];
    }

    foreach ($MODELS as $model) {
        $payload['model'] = $model;
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

        if (!$error && $httpCode === 200) {
            $check = json_decode($response, true);
            if ($check && !isset($check['error']) && isset($check['choices'])) {
                break;
            }
        }
    }
}

if ($error || !$response) {
    http_response_code(500);
    echo json_encode(['error' => 'Request failed: ' . ($error ?: 'all models failed')]);
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
