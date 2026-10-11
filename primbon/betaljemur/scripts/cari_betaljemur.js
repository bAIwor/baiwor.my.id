#!/usr/bin/env node
/**
 * cari_betaljemur.js — Mesin pencari teks & bab Kitab Primbon Betaljemur Adammakna
 * 
 * Penggunaan:
 *   node cari_betaljemur.js --bab 130
 *   node cari_betaljemur.js --hlm 112
 *   node cari_betaljemur.js "naga hari"
 *   node cari_betaljemur.js "mimpi"
 *   node cari_betaljemur.js "mendirikan rumah"
 */

const fs = require('fs');
const path = require('path');

const KITAB_FILE = path.join(__dirname, '../data/Kitab_Primbon_Betaljemur_Adammakna.md');
const DAFTAR_ISI_FILE = path.join(__dirname, '../data/daftar-isi.md');

if (!fs.existsSync(KITAB_FILE)) {
  console.error(JSON.stringify({ error: "Berkas Kitab Betaljemur tidak ditemukan." }));
  process.exit(1);
}

const args = process.argv.slice(2);
if (args.length === 0) {
  console.log(JSON.stringify({ petunjuk: "node cari_betaljemur.js [query atau --bab X atau --hlm Y]" }));
  process.exit(0);
}

const content = fs.readFileSync(KITAB_FILE, 'utf8');
const lines = content.split('\n');

function cariBab(nomorBab) {
  const babRegex = new RegExp('^No\\.?\\s*' + nomorBab + '(?![0-9])', 'i');
  let startIdx = -1;
  let halSebelumnya = "Tidak diketahui";

  for (let i = 0; i < lines.length; i++) {
    const line = lines[i].trim();
    if (line.startsWith('## Halaman ')) {
      halSebelumnya = line.replace('## Halaman ', '').trim();
    }
    if (babRegex.test(line)) {
      startIdx = i;
      break;
    }
  }

  if (startIdx === -1) {
    return { status: "not_found", message: "Bab No. " + nomorBab + " tidak ditemukan." };
  }

  // Ambil isi bab sampai bab berikutnya (No. X) atau batas maksimal 50 baris
  let babLines = [];
  let currentHal = halSebelumnya;
  for (let i = startIdx; i < lines.length && babLines.length < 50; i++) {
    const l = lines[i];
    if (l.trim().startsWith('## Halaman ')) {
      currentHal = l.trim().replace('## Halaman ', '').trim();
    }
    if (i > startIdx && /^No\.?\s*\d+(?![0-9])/.test(l.trim())) {
      break;
    }
    babLines.push(l);
  }

  return {
    status: "ok",
    bab: nomorBab,
    halaman: currentHal,
    teks: babLines.join('\n').trim()
  };
}

function cariKataKunci(query) {
  const qLower = query.toLowerCase();
  const matches = [];

  // 1. Cek di daftar isi terlebih dahulu untuk identifikasi bab yang relevan
  if (fs.existsSync(DAFTAR_ISI_FILE)) {
    const dContent = fs.readFileSync(DAFTAR_ISI_FILE, 'utf8');
    const dLines = dContent.split('\n');
    for (const dl of dLines) {
      if (dl.includes('|') && dl.toLowerCase().includes(qLower)) {
        const parts = dl.split('|').map(s => s.trim()).filter(Boolean);
        if (parts.length >= 3 && !isNaN(parts[0])) {
          matches.push({
            tipe: "daftar_isi",
            bab: parts[0],
            judul: parts[1],
            halaman: parts[2]
          });
        }
      }
    }
  }

  // 2. Jika ada kecocokan di bab daftar isi, ambil isi bab pertama
  if (matches.length > 0) {
    const babUtama = matches[0].bab;
    const detailBab = cariBab(babUtama);
    return {
      status: "ok",
      query: query,
      bab_terkait: matches.slice(0, 5),
      cuplikan_teks: detailBab.status === "ok" ? detailBab.teks : null,
      halaman: detailBab.halaman || matches[0].halaman
    };
  }

  // 3. Jika tidak ada di judul bab, lakukan pencarian langsung di isi naskah
  // 3a. Cocokkan frasa utuh (persis)
  let snippet = [];
  let foundHal = "Tidak diketahui";
  let mode = "frasa";
  for (let i = 0; i < lines.length; i++) {
    const l = lines[i];
    if (l.startsWith('## Halaman ')) {
      foundHal = l.replace('## Halaman ', '').trim();
    }
    if (l.toLowerCase().includes(qLower)) {
      const start = Math.max(0, i - 2);
      const end = Math.min(lines.length, i + 15);
      snippet = lines.slice(start, end);
      break;
    }
  }

  // 3b. Fallback: pecah query jadi token, semua token harus muncul (dekat satu sama lain)
  if (snippet.length === 0) {
    const tokens = qLower.split(/\s+/).filter(t => t.length >= 3);
    if (tokens.length >= 2) {
      mode = "token";
      const jarakMaks = 60;
      for (let i = 0; i < lines.length; i++) {
        if (!lines[i].trim()) continue;
        const hit = [];
        for (const tk of tokens) {
          const idx = lines.slice(Math.max(0, i - jarakMaks), Math.min(lines.length, i + jarakMaks))
            .findIndex(ln => ln.toLowerCase().includes(tk));
          if (idx !== -1) hit.push(Math.max(0, i - jarakMaks) + idx);
        }
        if (hit.length === tokens.length) {
          const lo = Math.max(0, Math.min.apply(null, hit) - 3);
          const hi = Math.min(lines.length, Math.max.apply(null, hit) + 15);
          // Nomor halaman: pakai marker halaman PERTAMA yang ada di dalam cuplikan,
          // baru kalau tidak ada, scan ke atas dari cuplikan.
          foundHal = "Tidak diketahui";
          for (let j = lo; j < hi; j++) {
            if (lines[j].startsWith('## Halaman ')) {
              foundHal = lines[j].replace('## Halaman ', '').trim();
              break;
            }
          }
          if (foundHal === "Tidak diketahui") {
            for (let j = lo; j >= 0; j--) {
              if (lines[j].startsWith('## Halaman ')) {
                foundHal = lines[j].replace('## Halaman ', '').trim();
                break;
              }
            }
          }
          snippet = lines.slice(lo, hi);
          break;
        }
      }
    }
  }

  if (snippet.length > 0) {
    return {
      status: "ok",
      query: query,
      mode: mode,
      halaman: foundHal,
      cuplikan_teks: snippet.join('\n').trim()
    };
  }

  return { status: "not_found", message: "Tidak ditemukan pembahasan tentang '" + query + "' di Kitab Betaljemur." };
}

let hasil;
if (args[0] === '--bab' && args[1]) {
  hasil = cariBab(args[1]);
} else if (args[0] === '--hlm' && args[1]) {
  const hlm = args[1];
  const start = content.indexOf('## Halaman ' + hlm);
  if (start !== -1) {
    const potong = content.substring(start, start + 1500);
    hasil = { status: "ok", halaman: hlm, cuplikan_teks: potong.trim() };
  } else {
    hasil = { status: "not_found", message: "Halaman " + hlm + " tidak ditemukan." };
  }
} else {
  hasil = cariKataKunci(args.join(' '));
}

console.log(JSON.stringify(hasil, null, 2));
