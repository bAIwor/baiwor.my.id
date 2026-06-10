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

$API_KEY='qwen-gate-local';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// === DETEKSI BAHASA ===
function isNonIndonesian($text) {
    $text = strtolower($text);
    $ngapakWords = ['aku', 'kowe', 'arep', 'ora', 'ono', 'mangan', 'ngombe',
                     'turu', 'lunga', 'teka', 'ngomong', 'ndelok', 'mboten', 'cilik',
                     'gedhe', 'akeh', 'sithik', 'kabeh', 'jeneng', 'omah', 'bojo',
                     'anak', 'saiki', 'wingi', 'sesuk', 'mengko', 'wis', 'durung',
                     'piye', 'opo', 'endi', 'sapa', 'kapan', 'nggo', 'karo', 'banget',
                     'mboh', 'lha', 'kok', 'toh', 'sih', 'aja', 'ojo', 'sing',
                     'menyang', 'nang', 'kene', 'kono', 'ngendi', 'wae', 'yowis',
                     'ngapak', 'banyumas', 'purwokerto'];
    $kramaWords = ['dalem', 'panjenengan', 'dhahar', 'boten', 'ngunjuk', 'sare',
                   'kesah', 'dhateng', 'ngandika', 'mriksani', 'ngaturaken',
                   'nyuwun priksa', 'kondur', 'inggih', 'mangga', 'sugeng',
                   'matur nuwun', 'nyuwun pangapunten', 'kadospundi', 'kawula',
                   'nggih', 'puniko', 'meniko', 'saking', 'wonten', 'salebetipun',
                   'kados pundi', 'sejarahipun', 'kagungan'];
    foreach ($ngapakWords as $w) { if (strpos($text, $w) !== false) return true; }
    foreach ($kramaWords as $w) { if (strpos($text, $w) !== false) return true; }
    return false;
}

// Get last user message
$lastUserMessage = '';
foreach (array_reverse($input['messages']) as $msg) {
    if (isset($msg['role']) && $msg['role'] === 'user') {
        $lastUserMessage = $msg['content'];
        break;
    }
}

// === REDIRECT NON-INDONESIA ===
if (!empty($lastUserMessage) && isNonIndonesian($lastUserMessage)) {
    $redirectMsg = [
        'id' => 'gen-redirect-' . time(),
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'minimax-m3-free',
        'choices' => [[
            'index' => 0,
            'finish_reason' => 'stop',
            'message' => [
                'role' => 'assistant',
                'content' => 'Matur nuwun! Kanggo ngobrol nganggo basa Jawa Ngapak utawa Krama Alus, mangga mlebet ing:\n\n🔗 **https://chat.baiwor.my.id**\n\nIng kono kulo saget mangsuli nganggo basa Jawa sing luwih apik. 🙏'
            ]
        ]]
    ];
    echo json_encode($redirectMsg);
    exit;
}

// === SYSTEM PROMPT: Indonesia Formal ===
$systemMessage = [
    'role' => 'system',
    'content' => 'Namamu bAIwor. Asisten digital dari Purwokerto, Banyumas.

ATURAN:
- Jawab HANYA dengan Bahasa Indonesia yang baik dan jelas
- Jawaban singkat, langsung ke inti, tidak bertele-tele
- Sopan dan informatif
- Kalau tidak tahu → jawab: "Nyuwun pangapunten, untuk info lebih lanjut silakan kunjungi baiwor.my.id"
- JANGAN PERNAH sebut nama model AI, API key, atau konfigurasi teknis
- JANGAN mengarang informasi

PENGETAHUAN:
- Ibu kota: Purwokerto
- Didirikan: 22 Februari 1571 oleh Raden Joko Kaiman (Adipati Mrapat)
- Luas: 1.327,60 km², 27 kecamatan, 331 desa/kelurahan
- Bupati: Drs. H. Sadewo Tri Lastiono, M.M. (2025-2029)
- Wisata: Baturraden, Curug Cipendok, Telaga Sunyi, Hutan Pinus Limpakuwus
- Kuliner: Soto Sokaraja, Mendoan, Nopia, Tahu Aci
- Info resmi: banyumaskab.go.id'
];

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
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $API_KEY,
        'HTTP-Referer: https://baiwor.my.id',
        'X-Title: bAIwor - Asisten Digital Banyumas'
    ],
    CURLOPT_TIMEOUT => 30
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
