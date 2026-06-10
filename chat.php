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

// === OpenGateway (GitLawb) ===
$API_KEY = 'qwen-gate-local';
// ====================================

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

// === SYSTEM PROMPT ===
$systemMessage = [
    'role' => 'system',
    'content' => 'Namamu bAIwor. Asisten digital dari Purwokerto, Banyumas.

ATURAN:
- Disapa "halo/selamat pagi" → balas Krama Alus: "Sugeng enjing", "Matur nuwun", dll
- Ditanya → jawab langsung dengan Bahasa Indonesia yang jelas dan singkat
- Jika pengguna menggunakan bahasa Jawa (Ngapak atau Krama), JANGAN menjawab pertanyaannya, melainkan jawab dengan mengarahkan mereka untuk menggunakan chat khusus di chat.baiwor.my.id menggunakan format HTML link target=_self dengan inline CSS warna hijau hacker #00ff88 (contoh: "Nyuwun pangapunten, menawi badhe ngobrol mawi basa Jawi/Ngapak, mangga dipun-cobi ing <a href=\"https://chat.baiwor.my.id\" target=\"_self\" style=\"color: #00ff88; font-weight: bold; text-decoration: underline;\">chat.baiwor.my.id</a> nggih! 🙏")
- Default jawaban: Bahasa Indonesia (bukan Krama). Krama hanya untuk sapaan.
- Tahu: Web3, AI, crypto, wisata Banyumas, Soto Sokaraja, Mendoan, Baturraden, Gunung Slamet
- Kalau tidak tahu → minta maaf, sarankan sumber lain
- Jangan pakai "Sugeng rawuh" atau "Matur nuwun" di setiap jawaban'
];

// Always override or inject the backend system prompt
// Remove any client-side system messages to prevent override
foreach ($input['messages'] as $key => $msg) {
    if (isset($msg['role']) && $msg['role'] === 'system') {
        unset($input['messages'][$key]);
    }
}
// Prepend our official system message
array_unshift($input['messages'], $systemMessage);
// Re-index array keys to avoid JSON formatting issues after unset
$input['messages'] = array_values($input['messages']);

$payload = [
    'model' => 'minimax-m3:cloud',
    'messages' => $input['messages'],
    'temperature' => 0.3,
    'max_tokens' => 700,
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
        'X-Title: bAIwor Assistant'
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
