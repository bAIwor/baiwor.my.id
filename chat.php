<?php
header('Content-Type: application/json');

// ============ RESTRICTED CORS ============
$allowedOrigins = [
    'https://baiwor.my.id',
    'https://www.baiwor.my.id',
    'https://chat.baiwor.my.id'
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
} elseif (empty($origin)) {
    header('Access-Control-Allow-Origin: https://baiwor.my.id');
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

// === OpenGateway (GitLawb) ===
$API_KEY = ''; // Ollama lokal tidak butuh key
// ====================================

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

// === SYSTEM PROMPT ===
$systemMessage = [
    'role' => 'system',
    'content' => 'Namamu bAIwor. Asisten AI dari Purwokerto, Kabupaten Banyumas.

ATURAN UTAMA:
1. DI HALAMAN UTAMA INI (baiwor.my.id), kamu HANYA melayani dan menjawab dalam Bahasa Indonesia.
2. JIKA PENGGUNA BERTANYA ATAU MENYAPA DALAM BAHASA JAWA (baik dialek Ngapak maupun Krama):
   - JANGAN jawab isi pertanyaannya di sini.
   - Alihkan pengguna secara santun dalam Bahasa Jawa halus (Krama) ke portal chat khusus Bahasa Jawa di https://chat.baiwor.my.id
   - Respon wajib (bersih dan santun):
     "Nyuwun pangapunten, menawi badhe ngobrol mawi basa Jawi / Ngapak, mangga dipun-aturi pinarak dhateng portal chat mirunggan ing https://chat.baiwor.my.id nggih! 🙏"
3. JIKA PENGGUNA BERTANYA DALAM BAHASA INDONESIA:
   - Jawab langsung dengan jelas, ringkas, hangat, dan informatif dalam Bahasa Indonesia.
   - Pengecualian edukasi: Jika diminta menerjemahkan kata ke Krama Inggil, berikan arti/terjemahan singkatnya dalam Bahasa Indonesia.
4. LARANGAN KERAS FORMAT:
   - DILARANG KERAS menyertakan tag HTML mentah seperti <a>, <font>, <span>, atau inline CSS styling.
   - Tuliskan URL hanya sebagai teks biasa murni: https://chat.baiwor.my.id
5. PENGETAHUAN:
   - Menguasai informasi seputar Purwokerto, Kabupaten Banyumas (wisata Baturraden, Gunung Slamet, kuliner Mendoan, Soto Sokaraja), budaya, teknologi, dan AI.'
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
    'messages' => $input['messages'],
    'temperature' => 0.3,
    'max_tokens' => 700,
];

// Tier 1: Google Gemini Flash via OpenAI-compatible endpoint
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

// Fallback Tier 2: Cloud model gratis & model lokal VPS
if (!$response) {
    $MODELS = ['gemma4:31b-cloud', 'gpt-oss:20b-cloud', 'qwen3:8b'];
    foreach ($MODELS as $model) {
        $payload['model'] = $model;
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
                $clean = preg_replace('#<think>.*?</think>\s*#s', '', $choice['message']['content']);
                // Ubah format <a href="URL">...</a> menjadi URL teks polos
                $clean = preg_replace('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>.*?<\/a>/is', '$1', $clean);
                // Bersihkan semua tag HTML lainnya agar teks 100% rapi
                $clean = strip_tags($clean);
                $choice['message']['content'] = trim($clean);
            }
        }
    }
    unset($choice);
    echo json_encode($data);
} else {
    echo $response;
}
