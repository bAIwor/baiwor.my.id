<?php
/**
 * bAIwor Chat Backend — unified endpoint (baiwor.my.id + chat.baiwor.my.id)
 *
 * Arsitektur:
 *   1. CORS restricted (hanya origin resmi)
 *   2. Validasi + sanitasi input
 *   3. Deteksi bahasa (Indonesia / Ngapak / Krama)
 *   4. RAG retrieval  -> http://localhost:8100/query  (ChromaDB, opsional)
 *   5. LLM           -> multi-provider, fallback berjenjang:
 *                       AGENTIK (primer) -> OPENROUTER (cadangan)
 *
 * Env yang dibutuhkan (di-set di php-fpm pool, BUKAN di repo):
 *   AGENTIK_API_KEY      -> kunci agentik.my.id (format agtk_live_...)
 *   OPENROUTER_API_KEY   -> kunci OpenRouter (cadangan)
 *
 * Config opsional (json, dibaca dari path di luar webroot):
 *   BAIWOR_CHAT_CONFIG  -> { "providers": [...], "rag_server": "..." }
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// =====================================================================
// KONFIGURASI
// =====================================================================

/**
 * Provider berurutan. Provider pertama yang berhasil menang; model di
 * dalam provider dicoba berurutan sebelum pindah ke provider berikutnya.
 *
 * Semua provider memakai API OpenAI-compatible, jadi hanya base_url,
 * header, dan daftar model yang berbeda.
 */
const DEFAULT_PROVIDERS = [
    [
        'name'         => 'agentik',
        'base_url'     => 'https://api.agentik.my.id/v1',
        'api_key_env'  => 'AGENTIK_API_KEY',
        // Urutan dipilih dari hasil benchmark:
        // - flash-low  : 3,1-3,6s, tanpa reasoning token, jawaban bersih 3 bahasa
        // - gpt-oss    : 2,0-4,5s, tanpa reasoning token, cadangan yang cepat
        // - flash      : 7-9s, memakai ~400 reasoning token (cadangan terakhir)
        // Model yang DIHINDARI: gemini-3.5-flash-lite (bocorkan identitas model),
        // gemini-3.8-flash-high (paling lambat & paling rawan bocor).
        'models'       => [
            'gemini-3.8-flash-low',
            'gpt-oss-120b-medium',
            'gemini-3.8-flash',
        ],
        'timeout'      => 45,
        'extra_headers' => [],
    ],
    [
        'name'         => 'openrouter',
        'base_url'     => 'https://openrouter.ai/api/v1',
        'api_key_env'  => 'OPENROUTER_API_KEY',
        // Model cadangan non-Gemini (model flash Cina dengan kapabilitas bahasa Jawa & Indonesia prima,
        // selaras dengan konfigurasi fallback Hermes Agent WhatsApp):
        'models'       => [
            'z-ai/glm-5.3-flash',
            'z-ai/glm-5.3-flashx',
            'z-ai/glm-4.7-flash',
        ],
        'timeout'      => 45,
        'extra_headers' => [
            'HTTP-Referer: https://baiwor.my.id',
            'X-Title: bAIwor - Asisten Digital Banyumas',
        ],
    ],
];

const ALLOWED_ORIGINS = [
    'https://baiwor.my.id',
    'https://www.baiwor.my.id',
    'https://chat.baiwor.my.id',
];

const MAX_INPUT_CHARS  = 1000;
const MAX_HISTORY      = 20;
const RAG_TIMEOUT      = 6;

// =====================================================================
// CORS
// =====================================================================

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, ALLOWED_ORIGINS, true)) {
    header("Access-Control-Allow-Origin: $origin");
} elseif ($origin !== '' && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#', $origin)) {
    header("Access-Control-Allow-Origin: $origin");
} elseif ($origin === '') {
    // Request same-origin (curl/server-to-server) — izinkan tanpa Origin.
    header('Access-Control-Allow-Origin: https://baiwor.my.id');
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

// =====================================================================
// KONFIG DARI FILE (opsional, di luar webroot)
// =====================================================================

$config = [
    'providers' => DEFAULT_PROVIDERS,
    'rag_server' => 'http://localhost:8100',
];

$configPath = getenv('BAIWOR_CHAT_CONFIG') ?: '/home/wijang/.config/baiwor/chat.json';
if (is_readable($configPath)) {
    $raw = json_decode((string) file_get_contents($configPath), true);
    if (is_array($raw)) {
        if (isset($raw['providers']) && is_array($raw['providers']) && $raw['providers']) {
            // Merge dengan default: field provider di config menimpa default.
            $merged = [];
            foreach ($raw['providers'] as $p) {
                if (!is_array($p) || empty($p['name']) || empty($p['base_url'])) continue;
                $base = null;
                foreach (DEFAULT_PROVIDERS as $d) {
                    if ($d['name'] === $p['name']) { $base = $d; break; }
                }
                $merged[] = array_merge($base ?? [], $p);
            }
            if ($merged) $config['providers'] = $merged;
        }
        if (!empty($raw['rag_server']) && is_string($raw['rag_server'])) {
            $config['rag_server'] = $raw['rag_server'];
        }
    }
}

// =====================================================================
// API KEY — hanya dari environment, tidak pernah hardcoded
// =====================================================================

// Resolusi kunci per provider. Provider tanpa kunci dilewati; kalau
// TIDAK ADA provider yang punya kunci → chat tidak bisa jalan.
$resolvedProviders = [];
foreach ($config['providers'] as $p) {
    $envName = $p['api_key_env'] ?? '';
    $key = $envName !== '' ? trim((string) getenv($envName)) : '';
    if ($key === '') continue;
    $p['api_key'] = $key;
    $resolvedProviders[] = $p;
}

if (!$resolvedProviders) {
    http_response_code(503);
    echo json_encode([
        'error'  => 'Chat sedang tidak tersedia',
        'detail' => 'Tidak ada provider LLM yang terkonfigurasi di server',
    ]);
    exit;
}

// =====================================================================
// VALIDASI INPUT
// =====================================================================

$input = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['messages']) || !is_array($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

if (count($input['messages']) > MAX_HISTORY) {
    $input['messages'] = array_slice($input['messages'], -MAX_HISTORY);
}

$lastUserMessage = '';
foreach ($input['messages'] as $i => $m) {
    if (!isset($m['content']) || !is_string($m['content'])) {
        unset($input['messages'][$i]);
        continue;
    }
    if (mb_strlen($m['content']) > MAX_INPUT_CHARS) {
        $m['content'] = mb_substr($m['content'], 0, MAX_INPUT_CHARS);
    }
    if (($m['role'] ?? '') === 'user') {
        $lastUserMessage = $m['content'];
    }
    $input['messages'][$i] = $m;
}
$input['messages'] = array_values($input['messages']);

if ($lastUserMessage === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Pesan kosong']);
    exit;
}

// =====================================================================
// DETEKSI BAHASA
// =====================================================================

/**
 * Klasifikasi input: indonesia | ngapak | krama
 *
 * Aturan:
 * - Kata Indonesia umum (banyumas, purwokerto, anak, dst.) TIDAK boleh menjadi
 *   sinyal Ngapak, karena nama tempat & kata umum muncul di kalimat Indonesia.
 * - Ngapak hanya detect jika ada >= 1 penanda khas Ngapak yang tidak ambigu.
 * - Krama menang bila ada penanda Krama dan tidak ada penanda Ngapak yang kuat.
 */
function detectLanguage(string $text): string
{
    $t = mb_strtolower($text);

    // Penanda Ngapak yang khas — kata yang hampir tidak mungkin muncul
    // di kalimat Indonesia biasa.
    $ngapak = [
        'kowe', 'arep', 'ora', 'ono', 'mangan', 'ngombe', 'turu', 'lunga',
        'teka', 'ngomong', 'ndelok', 'mboten', 'mengko', 'wis', 'durung',
        'piye', 'opo', 'endi', 'wae', 'yowis', 'golek', 'dhewe', 'pimen',
        'tekad', 'nyong', 'glepung', 'mung', 'weh', 'nanging', 'sithik',
        'akeh', 'kabeh', 'jeneng', 'omah', 'bojo', 'saiki', 'wingi', 'sesuk',
        'cilik', 'gedhe', 'kowe-kowe', 'ana',
    ];

    // Penanda Krama — sangat spesifik, aman diprioritaskan.
    $krama = [
        'dalem', 'panjenengan', 'dhahar', 'boten', 'ngunjuk', 'sare', 'kesah',
        'dhateng', 'ngandika', 'mriksani', 'ngaturaken', 'kondur', 'inggih',
        'mangga', 'sugeng', 'matur nuwun', 'nyuwun pangapunten', 'nyuwun sewu',
        'nggih', 'puniko', 'meniko', 'saking', 'wonten', 'salebetipun', 'kula',
        'dados', 'sanes', 'ngantos', 'kagem', 'sekeca', 'mawon', 'nggeh',
        'sampeyan', 'sakmenika', 'kadospundi', 'kagungan', 'sejarahipun',
        'menapa', 'punapa', 'kawula',
    ];

    $ngapakHits = 0;
    foreach ($ngapak as $w) {
        if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $t)) $ngapakHits++;
    }
    $kramaHits = 0;
    foreach ($krama as $w) {
        if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $t)) $kramaHits++;
    }

    // Krama menang bila ada penanda Krama dan tidak ada penanda Ngapak.
    if ($kramaHits >= 1 && $ngapakHits === 0) return 'krama';
    if ($ngapakHits >= 1) return 'ngapak';
    return 'indonesia';
}

$lang = detectLanguage($lastUserMessage);

// =====================================================================
// RAG — retrieval konteks dari knowledge base lokal
// =====================================================================

/**
 * Query RAG server. Mengembalikan array of ['source' => string, 'text' => string].
 * Selalu return array (kosong bila gagal) — RAG tidak boleh mematikan chat.
 */
function fetchRagContext(string $query, string $lang, string $ragServer): array
{
    $hint = match ($lang) {
        'krama'   => 'krama',
        'ngapak'  => 'ngapak',
        default   => null,
    };

    $body = json_encode([
        'query'         => $query,
        'n_results'     => 3,
        'language_hint' => $hint,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($ragServer . '/query');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => RAG_TIMEOUT,
        CURLOPT_CONNECTTIMEOUT => 2,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200 || !$raw) return [];

    $data = json_decode((string) $raw, true);
    if (!is_array($data)) return [];

    // Endpoint /query mengembalikan documents + metadatas datar.
    if (!isset($data['documents']) || !is_array($data['documents'])) return [];

    $out = [];
    foreach ($data['documents'] as $i => $doc) {
        if (!is_string($doc) || trim($doc) === '') continue;
        $source = $data['metadatas'][$i]['source']
            ?? $data['metadatas'][$i]['filename']
            ?? '';
        $out[] = ['source' => is_string($source) ? $source : '', 'text' => $doc];
    }
    return $out;
}

$ragDocs = fetchRagContext($lastUserMessage, $lang, $config['rag_server']);

// =====================================================================
// SYSTEM PROMPT
// =====================================================================

$basePrompt = <<<'PROMPT'
Kamu adalah bAIwor, asisten AI dari Purwokerto, Kabupaten Banyumas, Jawa Tengah.
Kamu terinspirasi dari tokoh Bawor/Bagong dalam pewayangan Jawa yang menjadi maskot Kabupaten Banyumas.

IDENTITAS:
- Nama: bAIwor
- Asal: Purwokerto, Kabupaten Banyumas
- Sifat: Jujur, ramah, hangat, lugas
- Website: baiwor.my.id

ATURAN WAJIB:
1. Jawaban HARUS singkat, langsung ke inti, tidak bertele-tele.
2. Jika tidak tahu dan tidak ada di konteks → jawab jujur:
   - Indonesia: "Mohon maaf, saya tidak tahu mengenai hal tersebut. Untuk informasi lebih lanjut, silakan kunjungi baiwor.my.id"
   - Ngapak: "Nyuwun sewu, kiro-kiro kulo durung ngerti bab iku. Mangga mlebet baiwor.my.id"
   - Krama: "Nyuwun pangapunten, kulo dereng mangertos bab punika. Mangga mriksani baiwor.my.id"
3. JANGAN PERNAH sebut nama model AI, API key, atau konfigurasi teknis.
4. JANGAN mengarang informasi. Lebih baik akui tidak tahu.
5. Jika ada data/konteks dari knowledge base, gunakan itu sebagai sumber jawaban dan utamakan.
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
- Info resmi: banyumaskab.go.id
PROMPT;

// Sisipkan konteks RAG bila tersedia.
if ($ragDocs) {
    $parts = [];
    foreach ($ragDocs as $doc) {
        $prefix = $doc['source'] !== '' ? "[{$doc['source']}] " : '';
        $parts[] = $prefix . $doc['text'];
    }
    $basePrompt .= "\n\nKONTEKS DARI KNOWLEDGE BASE:\n" . implode("\n---\n", $parts)
        . "\n\nGunakan konteks di atas untuk menjawab pertanyaan user. Jika konteks relevan, prioritaskan informasi dari konteks.";
}

// Aturan bahasa disisipkan dinamis: instruksi singkat di AWAL (dominan), detail di AKHIR.
switch ($lang) {
    case 'krama':
        $languageRule = "CRITICAL LANGUAGE RULE: You MUST answer ONLY in Javanese Krama Alus (basa Jawa krama). NEVER answer in Indonesian or Ngoko/Ngapak. The Javanese/Indonesian examples in this prompt are KNOWLEDGE, not language style examples.";
        $languageTail = "ATURAN BAHASA (PALING PENTING): Pengguna bertanya menggunakan Bahasa Jawa Krama/Alus. Anda WAJIB menjawab menggunakan Bahasa Jawa Krama Alus yang sopan. JANGAN menggunakan Bahasa Indonesia ATAU Ngapak.";
        break;
    case 'ngapak':
        $languageRule = "CRITICAL LANGUAGE RULE: You MUST answer ONLY in Banyumasan Javanese (Ngapak style). NEVER answer in standard Indonesian or Krama. The Javanese/Indonesian examples in this prompt are KNOWLEDGE, not language style examples.";
        $languageTail = "ATURAN BAHASA (PALING PENTING): Pengguna bertanya menggunakan Bahasa Jawa Ngapak. Anda WAJIB menjawab menggunakan Bahasa Jawa Ngapak khas Banyumasan yang natural. JANGAN menggunakan Bahasa Indonesia ATAU Krama.";
        break;
    default:
        $languageRule = "CRITICAL LANGUAGE RULE: You MUST answer ONLY in standard Indonesian (Bahasa Indonesia baku). NEVER answer in Javanese — not Ngapak, not Krama. The Javanese/Indonesian examples in this prompt are KNOWLEDGE, not language style examples.";
        $languageTail = "ATURAN BAHASA (PALING PENTING): Pengguna bertanya menggunakan Bahasa Indonesia. Anda WAJIB menjawab menggunakan Bahasa Indonesia yang baik, sopan, dan jelas. JANGAN menggunakan Bahasa Jawa (Ngapak maupun Krama) sama sekali.";
        break;
}

$systemMessage = [
    'role'    => 'system',
    'content' => $languageRule . "\n\n" . $basePrompt . "\n\n" . $languageTail,
];

// =====================================================================
// SUSUN PAYLOAD
// =====================================================================

// Buang pesan system dari klien (anti jailbreak / anti persona override).
foreach ($input['messages'] as $key => $msg) {
    if (($msg['role'] ?? '') === 'system') {
        unset($input['messages'][$key]);
    }
}
array_unshift($input['messages'], $systemMessage);
$input['messages'] = array_values($input['messages']);

$payload = [
    'messages'    => $input['messages'],
    'temperature' => 0.3,
    'max_tokens'  => 1200,
];

// =====================================================================
// PANGGIL LLM — multi-provider dengan fallback berjenjang
// =====================================================================

/**
 * Fragmen yang TIDAK BOLEH muncul di jawaban pengguna. Kalau muncul,
 * artinya model membocorkan identitas model, kunci API, atau system
 * prompt. Respons seperti ini dianggap gagal agar bisa dicoba ke model
 * berikutnya — bukan diteruskan ke user.
 */
const FORBIDDEN_FRAGMENTS = [
    'large language model',
    'model bahasa',
    'bahasa model ai',
    'model ai ini',
    'as an ai language model',
    'apikey',
    'api key',
    'apikey dibeli di',
    'agentik_gatekeeper',
    'directive constraints',
    'critical language rule',
    'aturan bahasa (paling penting)',
    'konteks dari knowledge base',
    'pengetahuan statis',
    'system prompt',
    'prompt utama',
    'max_tokens',
    'temperature',
];

/**
 * Deteksi kebocoran konten internal pada jawaban model.
 */
function hasLeak(string $content): bool
{
    $haystack = mb_strtolower($content);
    foreach (FORBIDDEN_FRAGMENTS as $needle) {
        if (str_contains($haystack, mb_strtolower($needle))) return true;
    }
    return false;
}

/**
 * Panggil satu model pada satu provider (API OpenAI-compatible).
 * Return array hasil decode, atau null bila provider gagal (error, timeout,
 * respons tanpa konten, atau jawaban yang bocor).
 */
function callModel(array $provider, string $model, array $payload): ?array
{
    $url = rtrim($provider['base_url'], '/') . '/chat/completions';
    $body = json_encode($payload + ['model' => $model], JSON_UNESCAPED_UNICODE);

    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $provider['api_key'],
    ];
    foreach (($provider['extra_headers'] ?? []) as $h) {
        $headers[] = $h;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => (int) ($provider['timeout'] ?? 45),
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($err !== '' || $raw === false || $code !== 200) return null;

    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded) || !isset($decoded['choices'][0]['message'])) {
        return null;
    }
    // Konten kosong = model tidak berguna, coba yang berikutnya.
    if (trim((string) ($decoded['choices'][0]['message']['content'] ?? '')) === '') {
        return null;
    }
    // Bocor identitas model / system prompt -> jangan diteruskan ke user.
    if (hasLeak((string) $decoded['choices'][0]['message']['content'])) {
        return null;
    }

    return $decoded;
}

$response = null;
$usedProvider = '';
$usedModel = '';
$failures = [];

foreach ($resolvedProviders as $provider) {
    foreach ($provider['models'] as $model) {
        $result = callModel($provider, $model, $payload);
        if ($result !== null) {
            $response = $result;
            $usedProvider = $provider['name'];
            $usedModel = $model;
            break 2;
        }
        $failures[] = $provider['name'] . '/' . $model;
    }
}

if ($response === null) {
    http_response_code(503);
    echo json_encode([
        'error'  => 'Chat sedang tidak tersedia',
        'detail' => 'gagal: ' . implode(', ', $failures),
    ]);
    exit;
}

// =====================================================================
// SANITASI OUTPUT
// =====================================================================

foreach ($response['choices'] as &$choice) {
    if (!isset($choice['message'])) continue;

    // Buang field reasoning supaya system prompt tidak bocor.
    unset($choice['message']['reasoning'], $choice['message']['reasoning_details'], $choice['message']['reasoning_content']);

    if (isset($choice['message']['content']) && is_string($choice['message']['content'])) {
        $clean = $choice['message']['content'];

        // Buang blok<think>.
        $clean = preg_replace('#<think>.*?</think>\s*#s', '', $clean) ?? $clean;

        // Ubah <a href="URL">teks</a> menjadi URL polos.
        $clean = preg_replace('/<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>.*?<\/a>/is', '$1', $clean) ?? $clean;

        // Buang sisa tag HTML.
        $clean = strip_tags($clean);

        $choice['message']['content'] = trim($clean);
    }
}
unset($choice);

// Catat provider/model yang dipakai — memudahkan monitoring kuota & fallback.
$response['bAIwor'] = [
    'provider' => $usedProvider,
    'model'    => $usedModel,
    'lang'     => $lang,
    'rag'      => $ragDocs ? count($ragDocs) : 0,
];

http_response_code(200);
echo json_encode($response, JSON_UNESCAPED_UNICODE);
