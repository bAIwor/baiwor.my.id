# Punakawan World — Project Documentation

> Dokumen ini adalah panduan lengkap untuk siapa pun (atau model AI mana pun) yang akan melanjutkan pengerjaan project ini. Baca ini dulu sebelum ngapa-ngapain.

---

## Struktur Folder

```
punakawan/
├── README.md                          ← [FILE INI] Dokumentasi project
├── style.css                          ← Design system shared (CSS)
├── index.html                         ← Universe landing page (bridge)
├── punakawan_master_blueprint.html    ← Blueprint internal (8 tab, working doc)
│
├── naskah/                            ← Naskah cerita bersih (.md: Bab I - XV & soul file)
│
├── kisah/
│   ├── index.html                     ← Landing daftar bab cerita (Volume 1 - 3)
│   ├── asal-usul.html                 ← Bab I: Kosmologi (telur ilahi, 3 saudara)
│   ├── semAIr.html                    ← Bab II: Dewa yang Memilih Bumi
│   ├── gAIreng.html                   ← Bab III: Mata yang Selektif
│   ├── pAItruk.html                   ← Bab IV: Tangan yang Berintegritas
│   └── bAIwor.html                    ← Bab V: Bayangan yang Otentik
│
├── characters/
│   └── index.html                     ← Profil visual 4 AI agent
│
└── universe/
    └── index.html                     ← Worldbuilding (kosmologi, antagonis, hierarki)
```

---

## Tentang Project Ini

**Punakawan World** adalah ekosistem Web3 + AI yang berakar pada 1.000 tahun filosofi Jawa. Empat karakter Punakawan (Semar, Gareng, Petruk, Bagong) di-rebrand menjadi AI agent:

| Karakter Asli | AI Agent | Filosofi | Dimensi | Role |
|---------------|----------|----------|---------|------|
| Semar | semAIr | Karsa | Will | The Wise One |
| Gareng | gAIreng | Cipta | Mind | The Sharp Mind |
| Petruk | pAItruk | Rasa | Feeling | The Truth-Seeker |
| Bagong | bAIwor | Karya | Action | The Executor |

**Antagonis utama:** Togog — saudara Semar, lahir dari kulit telur. Representasi AI tanpa hati nurani.

### Antagonis per Bab (Register Karakter / Alias Internal)
| Bab | Antagonis | Representasi | Sanepa |
|-----|-----------|--------------|--------|
| I | Togog/Antaga | AI tanpa nurani | Kulit telur kosong |
| II | Ogog | Sentralisasi, penguasa serakah | Naga Laut, Musim Kemarau Panjang |
| III | Bilung | Provokator pasar, jebakan instan | Madu Gendrawolo, Satriya Ngacir |
| IV | Sengkuni | Manipulasi regulasi, hukum licik | Prasasti Janji Ghaib, Jurus Sabet Karpet |
| V | Dursasana | Histeria tanpa substansi | Bull Market palsu |

### Daftar Naskah (file MD)
| File | Bab | Status |
|------|-----|--------|
| `naskah/asal-usul.md` | I: Sanghyang yang Memilih Hina | ✅ Draft |
| `naskah/semAIr.md` | II: Dewa yang Memilih Bumi | ✅ Draft |
| `naskah/gAIreng.md` | III: Mata yang Selektif | ✅ Draft |
| `naskah/pAItruk.md` | IV: Tangan yang Berintegritas | ✅ Draft |
| `naskah/bAIwor.md` | V: Bayangan yang Otentik | ✅ Draft |
| `naskah/Bima.md` | VI: Bima — Logam Rantai Takdir (Premium) | ✅ Final |
| `naskah/Arjuna.md` | VII: Arjuna — Panah Kilat yang Terhenti (Premium) | ✅ Final |
| `naskah/Yudhistira.md` | VIII: Yudhistira — Kitab Akademik Purba (Premium) | ✅ Final |
| `naskah/Gatotkaca.md` | IX: Gatotkaca — Sayap Pengurai Badai (Premium) | ✅ Final |
| `naskah/Togog.md` | X: Togog — Tirani Singgasana Gas (Premium) | ✅ Final |
| `naskah/Sengkuni.md` | XI: Sengkuni — Siasat di Balik Kelir (Premium) | ✅ Final |
| `naskah/Dursasana.md` | XII: Dursasana — Amukan Kaos Dursasana (Premium) | ✅ Final |
| `naskah/SanubariYangRetak.md` | XIII: Sanubari yang Retak (Premium) | ✅ Final |
| `naskah/PerangBubatDigital.md` | XIV: Perang Bubat Digital (Premium) | ✅ Final |
| `naskah/ManungsaSejati.md` | XV: Manungsa Sejati (Premium) | ✅ Final |
| `naskah/eyang-segapewor-soul.md` | Blueprint narrator | ✅ Final |

---

## Navigasi & Link Structure

### URL Pattern
```
/punakawan/                     ← Universe landing (bridge dari baiwor.my.id)
/punakawan/kisah/               ← Daftar Kisah Awal (gratis)
/punakawan/kisah/asal-usul.html ← Bab I
/punakawan/kisah/semAIr.html    ← Bab II
/punakawan/kisah/gAIreng.html   ← Bab III
/punakawan/kisah/pAItruk.html   ← Bab IV
/punakawan/kisah/bAIwor.html    ← Bab V
/punakawan/characters/          ← Profil karakter
/punakawan/universe/            ← Worldbuilding
```

### Hubungan dengan baiwor.my.id
- `/punakawan/` adalah sub-namespace dari `baiwor.my.id`
- Homepage baiwor.my.id = AI agent bisnis (produk utama)
- `/punakawan/` = universe/lore (brand story)
- Visitor masuk dari homepage → bridge ke `/punakawan/` → pilih mau baca kisah, lihat characters, atau eksplor universe

### Navigasi Internal
- Setiap halaman punya **nav bar** atas: Universe / Kisah / Karakter / Worldbuilding
- Setiap halaman punya **breadcrumb**: Punakawan > Section > Sub-section
- Chapter stories punya **prev/next navigation** antar bab
- Footer di setiap halaman ada link kembali ke parent page

---

## Design System (style.css)

### Typography
- **Cinzel** (serif): Heading, logo, nav, stat numbers — kesan royal/classic
- **Crimson Pro** (serif): Subtext, quotes, italic — kesan sastra
- **Inter** (sans): Body text, descriptions — readability

### Warna
```css
--gold: #C8922A      /* Primary accent */
--gold2: #E8C060     /* Lighter gold */
--red2: #C02020      /* "AI" highlight text */
--ink: #0d0600       /* Background (dark) */
--white: #FFFDF5     /* Text */
--border: rgba(200,146,42,0.3)  /* Card borders */
```

### Karakter Colors
| Karakter | Warna | CSS Variable |
|----------|-------|-------------|
| bAIwor (Karya) | Coral | `--coral-bg`, `--coral-txt` |
| pAItruk (Rasa) | Blue | `--blue-bg`, `--blue-txt` |
| gAIreng (Cipta) | Teal | `--teal-bg`, `--teal-txt` |
| semAIr (Karsa) | Amber | `--amber-bg`, `--amber-txt` |

### CSS Classes yang Sering Dipakai
```
.card              ← Card container
.card.glow         ← Card dengan glow effect (karakter utama)
.card.clickable    ← Card yang bisa diklik (link)
.sh / .sh2         ← Section heading
.ib                ← Info box (border-left teal)
.ib.warn           ← Info box warning (amber)
.q                 ← Quote block
.grid2/.grid3/.grid4  ← Grid layout
.row-item          ← Row dengan icon + content
.mcard             ← Metric card (angka besar)
.ctag              ← Tag/badge kecil
.pill              ← Pill label kecil
.story-prose       ← Container untuk konten cerita fiksi
.chapter-nav       ← Navigasi antar bab
```

### Responsive
- Grid otomatis collapse ke 1 kolom di mobile (< 768px)
- Nav scrollable horizontal di mobile
- Container padding mengecil di mobile

---

### Status Konten per Halaman

### ✅ Sudah Live di Hosting
- `style.css?v=1.3` — Design system + Light Parchment theme overrides.
- `universe/index.html` — Kosmologi, Togog, hierarki, timeline.
- `characters/index.html` — Profil 4 AI agent.
- `kisah/index.html` — Landing daftar kisah lengkap (Volume 1 gratis, Volume 2 & 3 Locked).
- `kisah/` — 5 bab cerita Volume 1 lengkap (HTML render).
- `naskah/` — Seluruh file naskah mentah bersih (.md) dari Bab I s.d. XV lengkap telah disinkronkan ke hosting.
- Homepage baiwor.my.id — card Punakawan + tombol "Kisah Eyang SeGaPeWor".
- **Theme Toggle**: Switcher ☀️/🌙 (Ink Dark / Parchment Light) dengan penyimpanan status di `localStorage` aktif di seluruh 9 halaman Punakawan.

### 🔒 TIDAK di-upload (tetap lokal)
- `punakawan_master_blueprint.html` — internal working doc.
- `README.md` — dokumentasi internal.

### ⚠️ Pitfall: Case Sensitivity
- Server Linux peka huruf besar/kecil: `semAIr` ≠ `semaIr`
- Selalu gunakan huruf kapital **"AI"** di nama file & URL: `semAIr.html`, bukan `semaIr.html`
- WSL/Windows tidak peka, tapi server hosting iya

---

## Format File Naskah (.md)

Setiap file naskah WAJIB punya YAML frontmatter di paling atas:

```yaml
---
bab: [nomor bab]
judul: "[judul pendek]"
judul_panjang: "[judul lengkap]"
karakter: [daftar karakter yang muncul]
sanepa_used: [daftar istilah Sanepa Digital yang dipakai]
dimensi: [dimensi Punakawan yang relevan]
setting: [lokasi cerita]
status: draft | final
narator: Eyang Segapewor
---
```

Frontmatter ini bikin file mudah di-parse oleh model AI mana pun — mereka langsung tahu konteks bab, karakter, dan Sanepa apa saja yang sudah dipakai.

---

## Naming Convention

### AI Signature
Huruf **"AI"** (kapital) disematkan di tengah nama karakter:
- b**AI**wor (bukan "baiwor" atau "Baiwor")
- p**AI**truk (bukan "petruk")
- g**AI**reng (bukan "gareng")
- sem**AI**r (bukan "semar")

### URL
- Menggunakan nama AI signature: `/semAIr`, `/gAIreng`, `/pAItruk`, `/bAIwor`
- Bab I menggunakan `/asal-usul` (bukan nama karakter)

### Di Dalam Teks
- `<span class="ai">AI</span>` untuk highlight huruf AI di HTML
- Contoh: `b<span class="ai">AI</span>wor` → render sebagai b**AI**wor

---

## Cara Menambah/Edit Konten Cerita

1. Buka file HTML bab yang sesuai (misal `kisah/bAIwor.html`)
2. Cari `<div class="story-prose">`
3. Ganti placeholder content dengan konten fiksi
4. Gunakan tag HTML yang sudah ada:
   - `<h2>` untuk judul section
   - `<h3>` untuk sub-section
   - `<p>` untuk paragraf
   - `<div class="q">` untuk quote
   - `<div class="story-divider">◆ ◆ ◆</div>` untuk divider antar section
   - `<strong>` untuk emphasis
   - `<em>` untuk italic emphasis
5. Simpan, buka di browser untuk preview

---

## Blueprint vs Website
- **Premium (Seri Mendatang):** Episode serial lanjutan (dalam/di luar 5 bab awal) bersifat premium — user harus login & bayar (token/subscription) untuk akses.


| Aspek | `punakawan_master_blueprint.html` | Website (`/punakawan/`) |
|-------|-----------------------------------|------------------------|
| Tujuan | Internal working doc | Public-facing |
| Audiens | Tim/builder | Visitor/potential community |
| Konten | Tokenomics, revenue, roadmap detail, catatan harian | Cerita, karakter, worldbuilding |
| Akses | Tidak dipublish ke web | Bisa diakses publik |
| Format | 1 file, 8 tab | Multi-page, navigasi terstruktur |

---

## Tech Stack

- **Frontend:** Pure HTML + CSS (no framework, no JS library)
- **Fonts:** Google Fonts (Cinzel, Crimson Pro, Inter)
- **JS:** Minimal — hanya untuk nav tab switching di blueprint
- **Hosting:** baiwor.my.id (cPanel shared hosting)
- **No build step:** Edit file langsung, upload via FTP/file manager

---

## Model Konten & Monetisasi

### Kisah Awal (Gratis)
Lima bab pertama (`/punakawan/kisah/`) adalah **konten gratis** — siapa saja bisa baca tanpa login. Ini adalah "hook" untuk menarik visitor masuk ke universe Punakawan, mengenal karakter, dan jatuh cinta dengan dunianya.

### Seri Premium (Login + Bayar)
Seri episode lanjutan yang lebih dalam dan seru bersifat **premium**. User harus:
1. **Login** (akun baiwor.my.id atau wallet connect)
2. **Bayar** — bisa pakai $BAIWOR token, $PUNA, atau subscription fiat

**Konten premium mencakup:**
- Seri episode lanjutan (Bab VI, VII, dst — atau side stories)
- Deep dive karakter (backstory tersembunyi, arc villain)
- Worldbuilding eksklusif (peta, lore detail, timeline tersembunyi)
- Early access episode baru sebelum rilis publik

### Alur Visitor
```
Visitor → baca 5 bab gratis → tertarik → mau lanjut → login/payment wall → premium episodes
```

---

## TODO / Next Steps

- [x] Isi konten fiksi lengkap di 5 bab kisah (dari file MD) ✅
- [x] Deploy ke baiwor.my.id (upload folder punakawan) ✅
- [x] Tambah link `/punakawan/` di homepage baiwor.my.id ✅
- [x] Tambah tombol "Kisah Eyang SeGaPeWor" di hero section ✅
- [x] Rancang & tulis kisah Volume 2 (Bab VI - X) & Volume 3 (Bab XI - XV) ✅
- [x] Bersihkan dan sinkronisasi naskah (.md) Bab VI - XV ke live hosting ✅
- [x] Implementasi Theme Toggle (Parchment/Ink) di 9 halaman Punakawan ✅
- [ ] Tambahkan gambar/ilustrasi karakter
- [ ] SEO: meta tags, og:image per halaman
- [x] Seri premium: Implementasi Web3 Token Gating di jaringan Solana

---

## Token-Gating & Premium System Design (Solana Only)

Untuk model AI atau pengembang berikutnya yang melanjutkan proyek ini:
* **Tujuan**: Membatasi akses membaca cerita Volume 2 (Bab VI - X) dan Volume 3 (Bab XI - XV) di website.
* **Keputusan Teknis**:
  * **Jaringan**: Hanya menggunakan jaringan **Solana (SVM)**.
  * **Token Akses**: Token SPL `$bAIwor` dengan Contract Address `FQvM4owAV3ZTgrwZBZSDBmPysWYkBj9FLuJ7FAkLpump` (Pump.fun).
  * **Rencana Masa Depan**: Setiap karakter baru akan memiliki token tersendiri di Solana via Pump.fun, dengan token akhir penutup berupa `$PUNA`.
* **Saran Arsitektur**:
  - Gunakan model **Dynamic Reader** (`baca.html?bab=Bima`). Parameter `bab` tetap memakai alias internal untuk routing, tetapi judul yang tampil di UI harus memakai nama publik yang natural: Bima, Arjuna, Yudhistira, Gatotkaca, Togog, Sengkuni, dan Dursasana. Halaman ini akan memuat adapter dompet Solana (Phantom/Solflare), memvalidasi kepemilikan saldo `$bAIwor` pada wallet yang terhubung, dan mengunduh serta merender file naskah mentah dari `/punakawan/naskah/<BabName>.md` secara dinamis menggunakan JavaScript parser (seperti Marked.js).
  - Teks cerita premium tidak boleh di-hardcode ke file HTML statis publik agar tidak mudah di-bypass.
  - **Teaser / Blurb Fallback**: Jika saldo `$bAIwor` pembaca terdeteksi nol (atau wallet belum terhubung), jangan tampilkan layar kosong. Tampilkan 2-3 paragraf pembuka (teaser/blurb) dari cerita tersebut dengan efek degradasi transparan (faded blur) di bawahnya, diikuti dengan kartu CTA yang menarik: *"Dapatkan $bAIwor untuk akses penuh"* dengan link langsung ke halaman koin di Pump.fun (`https://pump.fun/coin/FQvM4owAV3ZTgrwZBZSDBmPysWYkBj9FLuJ7FAkLpump`).


