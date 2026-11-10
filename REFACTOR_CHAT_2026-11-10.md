# Refactor Chat & Bubble — bAIwor

**Tanggal:** 10 November 2026
**Repositori:** `C:\Users\Wijang\bAIwor\VPS Agentic\bAIwor_website`
**Server:** VPS Agentic (Tailscale `agentic` / `100.103.204.26`) + Cloudflare

---

## Ringkasan

Arsitektur chat yang sebelumnya terdiri dari **3 implementasi tumpang tindih** kini
konsolidasi menjadi **1 aplikasi chat + 1 bubble link**:

| Sebelum | Sesudah |
|---|---|
| Widget chat inline di `index.html` (7,4 KB JS + 195 baris CSS) | **Dihapus** |
| `chat-bubble.js` di 5 halaman (link ke subdomain) | **Dipakai di 6 halaman**, became satu-satunya entry point |
| App penuh di `chat.baiwor.my.id` | Tetap, kini jadi **satu-satunya tempat ngobrol** |
| `chat.php` (root) + `chat/chat.php` (13,5 KB) — 2 backend berbeda | **1 backend** (`chat/chat.php`), di-deploy ke kedua domain |
| `baiwor-chat.php` — endpoint mati, CORS wildcard | **Dihapus** |
| `inject_chat.py` — injector yang anchor-nya sudah mati | **Dihapus** |

Backend juga dirombak total: **Ollama dan Gemini dibuang**, digantikan arsitektur
**multi-provider** — **AGENTIK** (primer, Gemini 3.8 Flash) dengan **OpenRouter**
sebagai cadangan, plus RAG ChromaDB yang dihidupkan kembali. Ditambah proteksi
anti-kebocoran jawaban model.

**Status: DEPLOYED & terverifikasi di produksi.**

---

## 1. Apa yang Terubah

### 1.1 Arsitektur: satu app, satu bubble

```
Sebelum:
  baiwor.my.id/index.html  ──► widget inline (chat.php root)   ← 2 UX berbeda
  baiwor.my.id/halaman*    ──► bubble link ──► chat.baiwor.my.id
  chat.baiwor.my.id        ──► app penuh (chat/chat.php)

Sesudah:
  baiwor.my.id/*           ──► bubble link ──► chat.baiwor.my.id
  chat.baiwor.my.id        ──► app penuh ──► chat/chat.php
                             (backend identik di kedua domain)
```

**Dihapus dari `index.html`:**
- Markup `#chat-widget` (29 baris)
- CSS `#chat-widget`, `.chat-btn`, `.chat-window`, `.chat-header`, `.chat-messages`,
  `.msg`, `.chat-input-area`, `.chat-send` + `@keyframes msgIn` (195 baris)
- JS `toggleChat()`, `handleKeyPress()`, `appendMessage()`, `sendMessage()`,
  `getChatResponse()`, `SYSTEM_INSTRUCTION` (72 baris)

**Ditambahkan:** `<script src="/chat-bubble.js?v=6"></script>` di `index.html`,
`tentang.html`, `layanan.html`, `kontak.html`, `sinau.html`, `404.html`.
Versi cache-buster dinaikkan `v=5` → `v=6` di semua halaman.

### 1.2 Backend: `chat/chat.php` (16,6 KB, tulis ulang penuh)

**Dihapus:**
- Kunci Gemini hardcoded (`AIzaSyDP...So3WSE`) — sudah bocor & diblokir Google
- Seluruh tier Ollama (`gemma4:31b-cloud`, `gpt-oss:20b-cloud`, `qwen3:8b`)
- `getenv('GEMINI_API_KEY') ?: '<key hardcoded>'`
- Bug `78 => 5` di dalam `curl_setopt_array` (baris tak berniat)
- Duplikasi penulisan `$MODELS` (dua blok if/else identik)

**Ditambahkan:**
- Arsitektur **multi-provider**: `agentik` (primer) → `openrouter` (cadangan), tiap provider
  punya daftar model sendiri yang dicoba berurutan
- Kunci API hanya dari `getenv()` — **tidak pernah** di repo. Provider tanpa kunci dilewati
- Konfigurasi eksternal via `/home/wijang/.config/baiwor/chat.json` (provider + model + RAG)
- RAG retrieval yang **tidak boleh mematikan chat** (gagal → array kosong, bukan error)
- Sanitasi output berlapis: buang `<think>`, ubah `<a href>` jadi URL polos, `strip_tags`
- **Proteksi anti-bocoran**: jawaban yang memuat identitas model / system prompt dianggap
  gagal → dialihkan ke model berikutnya (lihat section 2.4)
- Metadata provider di respons (`bAIwor.provider`, `bAIwor.model`, `bAIwor.lang`, `bAIwor.rag`)
- Pesan error jujur per kondisi (503 "chat tidak tersedia" + daftar provider yang gagal)
- Batas waktu RAG turun ke 6 detik (sebelumnya 10 detik, dan RAG mati → setiap pesan
  burning 10 detik sebelum lanjut)

### 1.3 `chat/index.html`
**Dihapus:** sistem multi-percakapan berbasis `localStorage`
(`conversations`, `currentConvId`, `saveConversations()`, `getOrCreateConv()`).
Percakapan kini **berjalan tunggal di memori** — `newChat()` hanya reset state.

**Dipertahankan:** render Markdown, efek typewriter (8 ms/char), tombol Copy,
tombol Copy, riwayat chat dalam sesi, `Ctrl+N`, welcome chips, RAG context.

**Diperbaiki:**
- `body` request tidak lagi mengirim `model` hardcoded (`gemma4:31b-cloud`) —
  model sepenuhnya dipilih server
- Tangani `!response.ok` dengan pesan ramah (sebelumnya diam-diam fallback string)

### 1.4 Bug Deteksi Bahasa (ditemukan saat verifikasi, sudah diperbaiki)

Kata **`banyumas` dan `purwokerto` ada di daftar penanda Ngapak** pada kode lama.
Akibatnya pertanyaan Indonesia biasa yang menyebut nama tempat salah diklasifikasikan:

> "Tolong sebutkan satu tourist spot di **Banyumas**." → dijawab dalam **Ngapak**

Perbaikan: nama tempat & kata ambigu (`banyumas`, `purwokerto`, `anak`, `aku`, `jalaran`)
dikeluarkan dari daftar penanda Ngapak. Hanya penanda khas yang bisa memicu
(`kowe`, `arep`, `wis`, `durung`, `mengko`, `ono`, `ora`, `golek`, …).

Krama kini hanya menang bila ada penanda Krama **dan** nol penanda Ngapak — sebelumnya
perbandingan hitungan bisa salah menilai saat dua bahasa tercampur dalam satu kalimat.

Verifikasi dengan 18 kasus (semua lulus):

```
[OK] 'Siapa Bupati Banyumas?'                        -> indonesia
[OK] 'Tolong sebutkan satu tourist spot di Banyumas.'-> indonesia   ← sebelumnya salah
[OK] 'Berapa banyak anak di Banyumas?'               -> indonesia   ← sebelumnya salah
[OK] 'Apa arti krama inggil?'                        -> indonesia
[OK] 'Opo kutho paling cocog ing Banyumas?'           -> ngapak
[OK] 'Kowe arep menyang ngendi?'                     -> ngapak
[OK] 'Wis mangan durung?'                            -> ngapak
[OK] 'Dalem sampun dhahar?'                          -> krama
[OK] 'Kula badhe dhahar ing Banyumas'                -> krama
[OK] 'Sugeng enjing, kados pundi wartanipun?'        -> krama
                                                    pass=18 fail=0
```

### 1.5 Nginx

`error_page 404 =301 https://baiwor.my.id/404;` → `=302 https://baiwor.my.id/404.html`

Permanent redirect (301) untuk kondisi "not found" itu salah secara SEO — Google
menganggapnya pemindahan permanen dan mengindeks URL yang salah. Backup config
disimpan di `/home/wijang/backups/nginx/`.

---

## 2. Provider LLM: Agentik primer, OpenRouter cadangan

Awalnya refactor ini memakai OpenRouter free. Setelah audit lanjutan, **agentik.my.id
ternyata aktif** dan lebih akurat untuk bahasa Jawa, jadi backend direstrukturisasi
menjadi multi-provider.

### 2.1 Status agentik saat audit

```
GET https://api.agentik.my.id/health
→ {"status":"healthy","service":"AGENTIK AI Engine","version":"2.1.0","region":"ap-southeast-1"}

GET https://api.agentik.my.id/v1/models   (dengan key agtk_live_...)
→ HTTP 200, 8 model
```

**Kenapa sempat 401 waktu refactor pertama:** di `/home/wijang/www/puraikerto/.env`
semua baris `AGENTIK_API_KEY=agtk_live_...` **terkomentari** (prefix `#`). Kunci yang
aktif ada di `/home/wijang/scripts/prewarm.sh` (dipakai project puraikerto).

Paket key ini adalah **BUILDER** — pesan errornya: *"Model 'unknown' tidak tersedia
di kelompok paket BUILDER Anda"*. Model `unknown` muncul kalau body request tidak
menyertakan field `model` (bug di script test, bukan di agentik).

### 2.2 Perbandingan (semua diukur langsung di VPS)

| Aspek | Agentik | OpenRouter free |
|---|---|---|
| Model tersedia | 8 (Gemini 3.8 Flash + gpt-oss-120b) | 13 free |
| Kualitas Ngapak | "Purwokerto. Kutha kiye dadi pusat pemerintahan… lan paling rame neng Banyumas." | "Purwokerto, kutho ibukota Kabupaten Banyumas." (Indonesia bocor) |
| Kualitas Krama | "Kula boten nedha amargi kula awujud program AI." | "Nyuwun pangapunten, kulo mboten saged dhahar…" (Indonesia bocor) |
| Stabilitas (6 paralel) | **6/6 → 200** | 4/6 → **502** |
| Kepatuhan system prompt | **Tinggi** — dataroni Simulacra | Cukup |

### 2.3 Benchmark per model agentik (10 Nov 2026)

| Model | Latensi | Reasoning token | Verdict |
|---|---|---|---|
| `gemini-3.8-flash-low` | 3,1–3,6 s | 0 | ✅ **Utama** — bersih di 3 bahasa |
| `gpt-oss-120b-medium` | 2,0–4,5 s | 0 | ✅ Cadangan cepat |
| `gemini-3.8-flash` | 7–9 s | ~400 | ✅ Cadangan terakhir |
| `gemini-3.5-flash-lite` | 1,0–1,7 s | 0 | ⚠️ Dihindari — bocorkan identitas model |
| `gemini-3.8-flash-high` | 7–9 s | ~400 | ⚠️ Dihindari — paling lambat & paling rawan bocor |

### 2.4 Bug kebocoran yang ditemukan & diperbaiki

Model Gemini tier tinggi kadang **membocorkan fragmen instruksi internal** ke jawaban:

```
[Dalem sampun dhahar?]
→ "directive constraints:         *   Terse.         *   Direct facts immediately. …"
```

Penyebab: model memakai ~400 **reasoning token** untuk jawaban pendek, dan saat
`max_tokens` mepet, sisa token untuk konten ternoda fragmen instruksi.

**Perbaikan (dua lapis):**
1. `max_tokens` dinaikkan 700 → **1200**
2. **Proteksi kebocoran** — `FORBIDDEN_FRAGMENTS` di `chat/chat.php`. jawaban yang memuat
   "large language model", "apikey", "directive constraints", "CRITICAL LANGUAGE RULE",
   "ATURAN BAHASA", "KONTEKS DARI KNOWLEDGE BASE", "system prompt", dll. dianggap
   **gagal** → otomatis pindah ke model berikutnya, tidak pernah sampai ke user.

### 2.5 Catatan penting tentang agentik + RAG

Tanpa system prompt, agentik menjawab **data usang**: *"Penjabat (Pj) Bupati Iwanuddin
Iskandar"* (data 2024). Begitu system prompt lengkap + konteks RAG disuntikkan
(persis seperti `chat.php`), jawabannya **benar**: *"Drs. H. Sadewo Tri Lastiono, M.M."*.

Artinya agentik **patuh konteks** — dan justru lebih baik dari OpenRouter, karena
gaya Jawasanya natural:
> "Purwokerto. Kutha kiye dadi pusat pemerintahan, pendidikan, kuliner, lan paling rame neng Banyumas."

---

## 3. Arsitektur backend final

```
chat/chat.php  (SATU file, di-deploy ke 2 domain)
   │
   ├─ CORS whitelist  → 403 kalau origin asing
   ├─ Validasi + batas 1000 char/msg, 20 msg
   ├─ Deteksi bahasa (Indonesia / Ngapak / Krama)
   ├─ RAG query → localhost:8100  (gagal?-chat tetap jalan)
   ├─ System prompt (statis + aturan bahasa + konteks RAG)
   │
   └─ LLM: provider berurutan, model berurutan
        ├─ 1. agentik    : gemini-3.8-flash-low → gpt-oss-120b-medium → gemini-3.8-flash
        └─ 2. openrouter : gemma-4-26b:free → gemma-4-31b:free → nemotron-3-ultra → lfm-3-1.7b
             (semua jawaban dicek anti-bocor; yang bocor dicoba ke model berikutnya)
```

Respon sekarang memuat metadata untuk monitoring:
```json
"bAIwor": {"provider":"agentik","model":"gemini-3.8-flash-low","lang":"indonesia","rag":3}
```

### Verifikasi fallback (diuji dengan mematikan env agentik)

```
env AGENTIK_API_KEY dihapus → provider: openrouter / google/gemma-4-26b-a4b-it:free  ✓
env dipulihkan              → provider: agentik / gemini-3.8-flash-low                ✓
```

---

| Provider | Status lama | Alasan dibuang |
|---|---|---|
| **Gemini** | `AIzaSyDP...So3WSE` | Kunci bocor di repo publik; Google memblokir → HTTP 403 *"Your API key was reported as leaked"* |
| **Ollama** | `localhost:12345` | Tidak ada model terpasang (`/api/tags` → `{"models":[]}`, storage 12K). Cloud model butuh kredit berbayar |
| **OpenRouter** | — | **Dipakai sekarang.** Kunci `OPENROUTER_API_KEY` sudah ada di VPS (`/home/wijang/www/puraikerto/.env`, sudah dipakai project lain) |

**Model yang dipakai** (`google/gemma-4-26b-a4b-it:free`) — diuji langsung di VPS
dan jawabannya benar dalam tiga bahasa. Fallback berjenjang:
`gemma-4-26b` → `gemma-4-31b` → `nemotron-3-ultra-550b` → `lfm-3-1.7b`.

> Ollama **tidak** dihapus dari server, hanya tidak lagi dipakai chat. Service-nya
> masih jalan di `127.0.0.1:12345` dan bisa dipakai untuk project lain.

---

## 4. RAG Server — Dihidupkan Kembali

Temuan audit: RAG **tidak hilang**, hanya tidak running.

- Kode: `/home/wijang/baiworchat/rag-server/` (FastAPI + ChromaDB + sentence-transformers)
- Data: `chroma.sqlite3` 11,9 MB — **utuh**, 4 collection, 6.938 entri
- Penyebab mati: entri PM2 (`baiwor-rag`) hilang dari daftar process, jadi tidak ada
  yang me-restart setelah reboot

**Masalah tambahan yang ditemukan:** PM2 dijalankan dengan interpreter python sistem,
bukan venv → `ModuleNotFoundError: No module named 'fastapi'`. Service appears "online"
di PM2 padahal port 8100 tidak pernah listening. Diperbaiki dengan
`ecosystem.config.json` yang menunjuk interpreter venv secara eksplisit.

**Health check sekarang:**
```json
{"status":"ok","model":"all-MiniLM-L6-v2","collections":{
  "banyumas_fakta":446,"ngapak_kosakata":2996,"krama_inggil":999,"word_frequency":2497}}
```

---

## 5. Verifikasi Produksi (semua diuji langsung ke server live)

### Chat — tiga bahasa, dua domain (diuji live setelah deploy agentik)

| Domain | Uji | Input | Hasil |
|---|---|---|---|
| utama | Indonesia | "Siapa Bupati Banyumas?" | "Bupati Banyumas saat ini adalah Drs. H. Sadewo Tri Lastiono, M.M." ✓ |
| utama | Indonesia | "Jelaskan apa itu Soto Sokaraja…" | "…kuliner khas soto berkuah kaldu gurih yang menggunakan bumbu sambal kacang…" ✓ |
| utama | Ngapak | "Opo kutho paling cocog ing Banyumas?" | "Purwokerto. Kutha kiye dadi pusat pemerintahan, pendidikan, kuliner, lan paling rame neng Banyumas." ✓ |
| utama | Krama | "Dalem sampun dhahar?" | "Kula boten nedha amargi kula awujud program AI." ✓ |
| chat | Indonesia | "Apa yang perlu diketahui tentang Baturraden?" | "…kawasan wisata andalan Kabupaten Banyumas di lereng selatan Gunung Slamet, sekitar 15 km…" ✓ |
| chat | Ngapak | "Kowe arep mangan apa?" | "Enyong kepengin mangan mendoan anget karo cengis." ✓ |
| chat | Krama | "Kula badhe dhahar ing Banyumas" | "Dhaharan ingkang dipunrekomendasikaken… Soto Sokaraja, Mendoan, Nopia…" ✓ |

Provider pada semua request: **`agentik` / `gemini-3.8-flash-low`**, RAG 3 dokumen,
**tidak ada kebocoran** pada jawaban mana pun. Distribusi 5 request berturut-turut:
5/5 `agentik/gemini-3.8-flash-low` (tidak pernah jatuh ke cadangan).

### Halaman & bubble

```
200  https://baiwor.my.id/          bubble v6 OK | widget inline sudah hilang
200  https://baiwor.my.id/tentang   bubble v6 OK | widget inline sudah hilang
200  https://baiwor.my.id/layanan
200  https://baiwor.my.id/kontak
200  https://baiwor.my.id/sinau
200  https://chat.baiwor.my.id/     (app chat, tanpa bubble — sesuai desain)
```

### Endpoint chat kedua domain
```
POST https://baiwor.my.id/chat.php     -> OK (agentik / gemini-3.8-flash-low)
POST https://chat.baiwor.my.id/chat.php -> OK (agentik / gemini-3.8-flash-low)
```

### Keamanan
| Uji | Hasil |
|---|---|
| CORS origin asing (`evil.example.com`) | **HTTP 403** ✓ |
| CORS origin resmi | `access-control-allow-origin: https://baiwor.my.id` + `vary: Origin` ✓ |
| Rate limit burst 25 request | 8 × 400 (lolos) + 17 × **503** (di-rate-limit) ✓ |
| Log nginx | `limiting requests, excess: 5.607 by zone "chat_limit"` ✓ |
| Deteksi kebocoran | jawaban bermarker "large language model" / "apikey" / "directive constraints" diblokir, dialihkan ke model berikutnya ✓ |

### RAG
```
collection: ngapak_kosakata  | intent: ngapak_kosakata
  [0] "Ngoko: Apa = Arti: Apa"
  [1] "Ngoko: Mêntas = Arti: Baru saja"
```
Leak test via `chat.php`: "Apa itu Soto Sokaraja dan dari mana asalnya?" → dijawab
benar ("khas dari Kabupaten Banyumas, KHUSUS Sokaraja, bumbu kacang").

---

## 6. File yang Dihapus

| File | Alasan |
|---|---|
| `chat.php` (root, 7,4 KB) | Digantikan `chat/chat.php` (unified) |
| `baiwor-chat.php` (5,9 KB) | Endpoint mati, CORS wildcard, model `minimax-m3:cloud` tak terpakai |
| `inject_chat.py` (6,3 KB) | Anchor (`<!-- FEATURES SECTION -->`, `  </style>\n</head>`) sudah hilang → script tidak mungkin jalan |

Di server, `baiwor-chat.php` dipindahkan (bukan dihapus) ke
`/home/wijang/backups/baiwor/deadcode_20261110/`.

---

## 7. Catatan Operasional

### Env yang WAJIB ada di server
```
/etc/php/8.5/fpm/pool.d/www.conf
  env[AGENTIK_API_KEY]    = agtk_live_...
  env[OPENROUTER_API_KEY] = sk-or-v1-...
  env[BAIWOR_CHAT_CONFIG] = /home/wijang/.config/baiwor/chat.json
```
Kalau **tidak ada satu pun** provider yang punya kunci → backend balas **HTTP 503**
dengan pesan jelas (bukan diam atau error PHP). Provider tanpa kunci otomatis
dilewati, jadi kehilangan `AGENTIK_API_KEY` hanya menurunkan ke OpenRouter —
chat tetap jalan.

### Ganti provider/model tanpa sentuh kode
```bash
ssh agentic "nano /home/wijang/.config/baiwor/chat.json"
# { "providers": [ { "name", "base_url", "api_key_env", "models", "timeout", "extra_headers" } ],
#   "rag_server": "http://localhost:8100" }
```

### Restart RAG
```bash
ssh agentic "cd /home/wijang/baiworchat/rag-server && pm2 start ecosystem.config.json && pm2 save"
```
Butuh ±20 detik (memuat model embedding). `pm2 save` wajib supaya service kembali
setelah reboot.

### Deploy — `chat/chat.php` ke DUA lokasi
```powershell
scp -o BatchMode=yes chat/chat.php agentic:/home/wijang/www/chat/
scp -o BatchMode=yes chat/chat.php agentic:/home/wijang/www/baiwor/chat.php
```
Lupa salah satu → kedua domain berperilaku berbeda.

### Pantau provider & kuota
Setiap respons chat memuat metadata:
```json
"bAIwor": {"provider":"agentik","model":"gemini-3.8-flash-low","lang":"indonesia","rag":3}
```
Kalau `provider` mulai `"openrouter"` terus-menerus, berarti agentik bermasalah
(kuota habis / key tidak terbaca) — cek `env[AGENTIK_API_KEY]` di php-fpm pool.

---

## 8. Sisa pekerjaan yang diketahui

1. **Kunci Gemini lama masih ada di riwayat git** (commit `718d0b4`, repo publik).
   Sudah tidak dipakai kode, tapi masih terbaca lewat
   `raw.githubusercontent.com/bAIwor/baiwor.my.id/main/chat/chat.php` di commit lama.
   Perlu rotasi di Google AI Studio + `git filter-repo` bila ingin dibersihkan.
2. **`chat-bubble.js` tidak ada di `chat.baiwor.my.id`** — itu memang sesuai desain
   (halaman chat sudah jadi tujuan), tapi `.home-bubble` di sana yang mengarah balik
   ke beranda perlu dicek periodically.
3. **Server masih punya file yang tidak ada di repo** — `privacy.html`, `terms.html`,
   `callback.html`, `app_icon_1024.png/jpg`, beberapa `tiktok-*.txt`. Kalau repo
   dipakai untuk rebuild, file ini hilang. Perlu dipindahkan ke repo atau dicatat
   sebagai server-only.
4. **Aset berat** ~13 MB tidak terpakai (`baiwor_gunungan_original.png` 6,3 MB,
   `bAIwor_default.png`, `banner_baiwor.png`, `bAIwor_maskot.png`).
5. **`seo-local.html` masih ter-deploy sebagai halaman HTML utuh** di root, padahal
   isinya fragmen meta tanpa `<html>`/`<title>`.
6. **`primbon/data.json` di server** tidak ter-track di repo lokal.
7. **RAG `banyumas_fakta` hanya 446 entri** (dokumentasi lama menyebut 316) —
   data bertambah, tapi_pipeline scrape tidak pernah dijalankan ulang sejak Mei 2026.
8. **Kuota agentik belum dipantau.** Key `agtk_live_` yang dipakai chat **sama dengan**
   yang dipakai project puraikerto (`prewarm.sh`). Paket key adalah **BUILDER** —
   agentik tidak menyediakan endpoint usage/balance (semua 404), jadi sisa kuota
   hanya bisa dilihat dari dashboard agentik.my.id. Kalau kuota habis, chat otomatis
   jatuh ke OpenRouter — tidak mati, tapi kualitas Jawa turun.
   → Pertimbangkan key agentik khusus chat bila pemakaian lewat.
9. **Model agentik tier tinggi masih bisa bocor.** Proteksi anti-bocoran di backend
   menutupinya (jawaban diblokir → pindah model), tapi `gemini-3.8-flash-low` tetap
   dipakai sebagai model utama karena paling bersih.

---

*Dokumen ini dibuat selama refactor chat & bubble yang langsung di-deploy dan
diuji ke server produksi (SSH via Tailscale + HTTP live).*
