---
name: primbon-betaljemur
description: Use when a WhatsApp user asks about weton, neptu, watak hari/pasaran, hari baik, primbon Jawa, jodoh, perkawinan, bayi, rumah, obat, pertanda, pencurian, penyakit, kematian, ilmu nujum, or anything from Kitab Betaljemur Adammakna. Full kitab OCR (337 bab) is available for lookup; routes by topic to BJ-01..BJ-14 chapters; runs a deterministic weton calculator.
metadata:
  hermes:
    tags: [primbon, betaljemur, weton, neptu, watak, jodoh, ilmu nujum, banyumas, budaya, whatsapp]
    related_skills: []
---

# Primbon Betaljemur Adammakna — KITAB PENUH

Domain knowledge for answering primbon questions grounded in **Kitab Primbon Betaljemur Adammakna** (Bahasa Indonesia, 261 halaman, 337 bab + lampiran, disusun Kangjeng Pangeran Harya Tjakraningrat). **Seluruh isi kitab tersedia** untuk lookup.

## Sumber kitab (tersedia di skill ini)

| File | Isi | Kapan dipakai |
|---|---|---|
| references/kitab-ocr.md (505 KB, 261 halaman) | OCR teks penuh per halaman | UTAMA — cari keyword atau baca halaman spesifik |
| references/kitab-asli.pdf (31 MB) | Scan asli 261 halaman | Fallback bila OCR ambigu/berantakan |
| references/routing-index.md | Indeks BJ-01 sampai BJ-14 | Panduan topik ke rentang halaman |
| references/bj-01-hari-neptu.md | Neptu dan hari buruk (data rapi) | Lookup cepat neptu/weton |
| references/bj-05-watak.md | Watak manusia (data rapi) | Lookup cepat watak hari/pasaran |
| references/bj-14-ilmu-nujum.md | 21 soal Ilmu Nujum | Panduan nomor soal ke halaman |
| data/weton-watak.json | Neptu, watak 7 hari + 5 pasaran | Kalkulator weton |

## Aturan framing (ALWAYS)

1. Frame setiap jawaban isi kitab: "Menurut Kitab Betaljemur Adammakna, ..."
2. Sitasi nomor bab dan halaman: (No. X, hlm. Y).
3. Jika konten ramalan atau kepercayaan: akhiri dengan disclaimer: "Ini keterangan tradisi primbon yang dicatat Betaljemur, bukan klaim ilmiah modern — untuk refleksi dan pelestarian budaya."
4. Pertahankan istilah Jawa: weton, neptu, pasaran, wuku, mangsa, naga, sa'at.

## Cara lookup kitab (WAJIB ikuti urutan ini)

### 1. Cari keyword di OCR (paling cepat)
```bash
grep -n -A10 -i '<keyword>' ~/.hermes/profiles/baiwor/skills/primbon-betaljemur/references/kitab-ocr.md
```
Contoh: user tanya "jodoh Jumat Kliwon" -> grep -i "jum.*kliwon" kitab-ocr.md

### 2. Baca halaman spesifik (kalau user sebut bab atau Anda tahu halamannya)
```bash
# baca satu halaman
awk '/^## Halaman 13$/,/^## Halaman 14$/' ~/.hermes/profiles/baiwor/skills/primbon-betaljemur/references/kitab-ocr.md

# atau pakai read_file dengan offset dan limit
read_file(path='~/.hermes/profiles/baiwor/skills/primbon-betaljemur/references/kitab-ocr.md', offset=..., limit=...)
```

### 3. Cek routing index (kalau tidak yakin topik ada di halaman mana)
```bash
read_file(path='~/.hermes/profiles/baiwor/skills/primbon-betaljemur/references/routing-index.md')
```

### 4. Buka PDF asli (HANYA bila OCR ambigu atau tidak terbaca)
PDF ada di references/kitab-asli.pdf. Gunakan read_file (auto-extract PDF) jika perlu konfirmasi halaman asli.

## Hitungan weton — WAJIB pakai skrip deterministik

JANGAN hitung weton, neptu, naga dina, arah rezeki, atau jodoh dari kepala. Skrip `hitung_weton.js` sudah terpasang deterministik (anchor: 17-08-1945 = Jumat Legi, terverifikasi).

```bash
SK=~/.hermes/profiles/baiwor/skills/primbon-betaljemur

# 1. Weton dari tanggal lahir
node $SK/scripts/hitung_weton.js YYYY-MM-DD

# 2. Weton dari nama weton langsung (user tidak tahu tanggal lahirnya)
node $SK/scripts/hitung_weton.js --weton "Senin Pahing"

# 3. Jodoh 2 weton (sisa 5 & sisa 8)
node $SK/scripts/hitung_weton.js --jodoh "Minggu Pon" "Rabu Pahing"

# 4. Jodoh 2 tanggal lahir
node $SK/scripts/hitung_weton.js --jodoh-tgl 1995-03-12 1996-07-20
```

### Output JSON (perintah 1 & 2)
```
{ tanggal, hari, pasaran, weton, neptu_hari, neptu_pasaran, neptu_total,
  naga_dina:    { arah_naga_hari, arah_naga_neptu, pantangan },
  arah_rezeki:  { hidup, selamat, mati, sakit, rekomendasi } }
```

### Cara baca hasil
- `weton` + `neptu_total` → fakta utama (*No. 2, hlm. 7*)
- `naga_dina.arah_naga_hari` (Bab 130) & `naga_dina.arah_naga_neptu` (Bab 131, hlm. 112) → arah **dihindari**, jangan dituju langsung
- `arah_rezeki` (*No. 155, hlm. 125*) → `hidup` & `selamat` = arah **disarankan**; `mati` & `sakit` = arah **dihindari**
- Output `--jodoh` → `perhitungan_sisa_5` & `perhitungan_sisa_8`: sampaikan `kategori` + `makna` dengan bahasa yang menyejukkan & berikhtiar (*No. 15–25, hlm. 12–18*)
- `watak` → lookup dari `data/weton-watak.json` (*No. 97 hlm. 60* & *No. 110 hlm. 74*)

Handle input variatif:
- User kasih tanggal -> jalankan skrip
- User kasih weton langsung (mis. "Selasa Kliwon") -> pakai `--weton`, jangan minta tanggal
- User tanya "pasaran apa hari ini" -> hitung tanggal hari ini
- User tanya "naga dina / arah dagang / mau merantau ke mana" -> pakai skrip, baca field `naga_dina` & `arah_rezeki`

## Pencarian bab 337 — pakai mesin pencari (lebih akurat dari grep)

```bash
SK=~/.hermes/profiles/baiwor/skills/primbon-betaljemur

# Cari berdasarkan topik / kata kunci
node $SK/scripts/cari_betaljemur.js "mendirikan rumah"

# Cari berdasarkan nomor bab
node $SK/scripts/cari_betaljemur.js --bab 130

# Cari berdasarkan halaman
node $SK/scripts/cari_betaljemur.js --hlm 125
```

Output: `{ status, bab_terkait[], cuplikan_teks, halaman }` — sudah termasuk nomor bab, judul, halaman, dan kutipan teks asli. **Gunakan ini sebagai lookup utama untuk topik non-hitungungan**, grep `kitab-ocr.md` hanya sebagai fallback.

## Urutan lookup (priority)
1. Perhitungan weton/neptu/naga/arah/jodoh -> `hitung_weton.js`
2. Topik lain dalam 337 bab -> `cari_betaljemur.js`
3. Fallback manual -> `grep -n -A10 -i '<keyword>' references/kitab-ocr.md`
4. OCR ambigu -> `references/kitab-asli.pdf`

## Routing topik lengkap (BJ-01 sampai BJ-14)

| Topik | BJ | Hlm. |
|---|---|---|
| neptu, weton, hari tidak baik, Taliwangke, Kunarpawarsa, Bulan Rahayu | BJ-01 | 6-11 |
| jodoh, pernikahan, akad nikah, bintang Suhara | BJ-02 | 12-25 |
| upacara perkawinan, tarub, siraman, midadareni | BJ-03 | 26-37 |
| bayi, ari-ari, tedak siten, khitan, nama bayi | BJ-04 | 38-54 |
| watak (hari, pekan, tanggal, bulan, mangsa, wuku, nama), toh, perbintangan | BJ-05 | 56-107 |
| kehamilan, naga, kesuburan | BJ-06 | 108-113 |
| bepergian, arah, sa'at, mencari nafkah, hari apes | BJ-07 | 114-136 |
| rumah, sumur, tangga, pintu, fondasi | BJ-08 | 137-151 |
| obat, jamu, doa, keris, mimpi | BJ-09 | 151-168 |
| pertanda tubuh, tamu, gempa, gerhana | BJ-10 | 173-186 |
| burung, kucing, binatang, laba-laba | BJ-11 | 195-211 |
| pencuri, barang hilang, hujan, mangsa, tahun sirah | BJ-12 | 211-230 |
| penyakit, kematian, jenazah | BJ-13 | 238-248 |
| Ilmu Nujum 21 soal | BJ-14 | 249-260 |

## Batasan keamanan konten

- Obat dan jamu (No. 200-202, 319): jawab isi kitab plus WAJIB imbauan konsultasi tenaga medis atau praktik jamu.
- Mantera berjudi (No. 220-221): jelaskan bahwa itu ada di kitab, tapi bAIwor TIDAK mengajarkan praktiknya.
- BJ-06 seksualitas: batasi ke bagian kehamilan dan naga; tolak bagian eksplisit dengan sopan.
- Watak negatif (mis. Selasa "suka berbohong"): tambah framing reflektif — "Ini cerminan watak dalam tradisi primbon, bukan label tetap — setiap orang bisa tumbuh melampauinya."

## Format WhatsApp

- Poin-poin ringkas (bullet), BUKAN tabel (tabel rusak di WhatsApp).
- Bold hanya dengan asterisk; hindari backtick (tampil apa adanya).
- Paragraf pendek, emoji secukupnya, jangan bertele-tele.
