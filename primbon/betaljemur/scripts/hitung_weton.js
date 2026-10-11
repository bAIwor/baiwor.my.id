/**
 * hitung_weton.js — Kalkulator Weton, Naga Dina, Arah Rejeki, dan Jodoh
 * Berdasarkan Kitab Primbon Betaljemur Adammakna karya KPH Cakraningrat.
 * 
 * Penggunaan:
 *   node hitung_weton.js 1990-05-21
 *   node hitung_weton.js --weton "Senin Pahing"
 *   node hitung_weton.js --jodoh "Minggu Pon" "Rabu Pahing"
 *   node hitung_weton.js --jodoh-tgl 1995-03-12 1996-07-20
 */

const NEPTU_HARI = { "Ahad": 5, "Minggu": 5, "Senin": 4, "Selasa": 3, "Rabu": 7, "Kamis": 8, "Jumat": 6, "Sabtu": 9 };
const NEPTU_PASARAN = { "Legi": 5, "Pahing": 9, "Paing": 9, "Pon": 7, "Wage": 4, "Kliwon": 8 };

const PASARAN_URUT = ["Legi", "Pahing", "Pon", "Wage", "Kliwon"];
const NAMA_HARI = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
const KUNCI_HARI = { "Minggu": "Ahad", "Senin": "Senin", "Selasa": "Selasa", "Rabu": "Rabu", "Kamis": "Kamis", "Jumat": "Jumat", "Sabtu": "Sabtu" };

const ANCHOR_MS = Date.UTC(1945, 7, 17); // 17 Agustus 1945 = Jumat Legi

// Bab 130: Naga Hari
const NAGA_HARI = {
  "Ahad": "Selatan",
  "Minggu": "Selatan",
  "Senin": "Barat Daya",
  "Selasa": "Barat",
  "Rabu": "Barat Laut",
  "Kamis": "Utara / Timur Laut",
  "Jumat": "Timur",
  "Sabtu": "Tenggara"
};

// Bab 131: Naga menurut Neptu Total
function getNagaNeptu(neptuTotal) {
  if ([7, 12, 17].includes(neptuTotal)) return "Timur Laut";
  if ([8, 13].includes(neptuTotal)) return "Barat Laut";
  if ([9, 14].includes(neptuTotal)) return "Barat Daya";
  if ([10, 15].includes(neptuTotal)) return "Tenggara";
  if ([11, 16, 18].includes(neptuTotal)) return "Timur";
  return "Tengah";
}

// Bab 155: Arah Sandhang Pangan (Rejeki & Dagang)
function getArahRezeki(neptuTotal) {
  const sisa = neptuTotal % 4; // siklus 4 penjuru
  if (sisa === 3 || neptuTotal === 7 || neptuTotal === 11 || neptuTotal === 15) {
    return {
      hidup: "Selatan",
      selamat: "Barat",
      mati: "Utara",
      sakit: "Timur",
      rekomendasi: "Selatan (Hidup) dan Barat (Selamat)"
    };
  } else if (sisa === 0 || neptuTotal === 8 || neptuTotal === 12 || neptuTotal === 16) {
    return {
      hidup: "Barat",
      selamat: "Utara",
      mati: "Timur",
      sakit: "Selatan",
      rekomendasi: "Utara (Selamat) dan Barat (Hidup)"
    };
  } else if (sisa === 1 || neptuTotal === 9 || neptuTotal === 13 || neptuTotal === 17) {
    return {
      hidup: "Timur",
      selamat: "Selatan",
      mati: "Barat",
      sakit: "Utara",
      rekomendasi: "Timur (Hidup) dan Selatan (Selamat)"
    };
  } else { // sisa 2: 10, 14, 18
    return {
      hidup: "Utara",
      selamat: "Timur",
      mati: "Selatan",
      sakit: "Barat",
      rekomendasi: "Utara (Hidup) dan Timur (Selamat)"
    };
  }
}

// Bab 15-25: Perhitungan Jodoh (Pancasuda sisa 5 & Klasik sisa 8)
const ARTI_JODOH_5 = {
  1: { nama: "Sri", makna: "Rezeki melimpah, rumah tangga makmur dan damai." },
  2: { nama: "Rejeki / Lungguh", makna: "Mendapat kemuliaan, derajat, dan rezeki yang lancar." },
  3: { nama: "Gedhong", makna: "Kaya harta dan materi, sandang pangan tercukupi." },
  4: { nama: "Lara", makna: "Sering diuji cobaan kesehatan atau rintangan batin, butuh ketabahan dan doa." },
  0: { nama: "Pati", makna: "Banyak godaan atau rintangan berat dalam perjalanan rumah tangga, disarankan ruwatan/sedekah dan saling mengalah." }
};

const ARTI_JODOH_8 = {
  1: { nama: "Pegat", makna: "Rentan perselisihan atau perpisahan, perlu saling sabar dan menguatkan komunikasi." },
  2: { nama: "Ratu", makna: "Sangat dihormati masyarakat, hidup rukun, berwibawa layaknya raja dan ratu." },
  3: { nama: "Jodoh", makna: "Sangat cocok, saling melengkapi dan rukun hingga lanjut usia." },
  4: { nama: "Topo", makna: "Awal pernikahan mengalami prihatin/ujian berat, namun kelak mencapai kebahagiaan luar biasa." },
  5: { nama: "Tinari", makna: "Rezeki melimpah ruah, sering mendapat kemudahan dan keberuntungan tak terduga." },
  6: { nama: "Padu", makna: "Sering adu mulut soal hal-hal sepele, namun tidak sampai berpisah jika saling memaafkan." },
  7: { nama: "Sujanan", makna: "Rentan godaan pihak ketiga atau rasa cemburu, wajib menjaga keterbukaan." },
  0: { nama: "Pesthi", makna: "Rukun, tenteram, adem ayem, dan tidak goyah oleh cobaan apa pun." }
};

function hitungWeton(dateStr) {
  const m = /^(\d{4})-(\d{1,2})-(\d{1,2})$/.exec(dateStr || "");
  if (!m) throw new Error("Format tanggal harus YYYY-MM-DD, contoh: 1990-05-21");
  const y = +m[1], mo = +m[2], d = +m[3];
  if (mo < 1 || mo > 12 || d < 1 || d > 31) throw new Error("Tanggal tidak valid");

  const t = Date.UTC(y, mo - 1, d);
  const offset = Math.round((t - ANCHOR_MS) / 86400000);
  const pasaran = PASARAN_URUT[((offset % 5) + 5) % 5];
  const hari = NAMA_HARI[new Date(t).getUTCDay()];
  const kHari = KUNCI_HARI[hari];
  const nHari = NEPTU_HARI[kHari];
  const nPas = NEPTU_PASARAN[pasaran];
  const nTotal = nHari + nPas;

  return {
    tanggal: `${y}-${String(mo).padStart(2, "0")}-${String(d).padStart(2, "0")}`,
    hari,
    pasaran,
    weton: `${hari} ${pasaran}`,
    neptu_hari: nHari,
    neptu_pasaran: nPas,
    neptu_total: nTotal,
    naga_dina: {
      arah_naga_hari: NAGA_HARI[kHari],
      arah_naga_neptu: getNagaNeptu(nTotal),
      pantangan: `Hindari bepergian langsung menentang arah ${NAGA_HARI[kHari]} (Bab 130) & ${getNagaNeptu(nTotal)} (Bab 131)`
    },
    arah_rezeki: getArahRezeki(nTotal)
  };
}

function parseWetonString(str) {
  const parts = str.trim().split(/\s+/);
  if (parts.length < 2) throw new Error("Format weton harus dua kata, contoh: 'Senin Pahing' atau 'Rebo Pon'");
  
  let h = parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase();
  let p = parts[1].charAt(0).toUpperCase() + parts[1].slice(1).toLowerCase();

  if (h === "Minggu") h = "Ahad";
  if (h === "Rebo") h = "Rabu";
  if (h === "Jum'at") h = "Jumat";
  if (p === "Paing") p = "Pahing";

  const nH = NEPTU_HARI[h];
  const nP = NEPTU_PASARAN[p];
  if (!nH) throw new Error(`Nama hari '${parts[0]}' tidak valid.`);
  if (!nP) throw new Error(`Nama pasaran '${parts[1]}' tidak valid.`);

  const nTotal = nH + nP;
  return {
    hari: h === "Ahad" ? "Minggu" : h,
    pasaran: p,
    weton: `${h === "Ahad" ? "Minggu" : h} ${p}`,
    neptu_hari: nH,
    neptu_pasaran: nP,
    neptu_total: nTotal,
    naga_dina: {
      arah_naga_hari: NAGA_HARI[h],
      arah_naga_neptu: getNagaNeptu(nTotal),
      pantangan: `Hindari menuju arah ${NAGA_HARI[h]} (Bab 130) & ${getNagaNeptu(nTotal)} (Bab 131)`
    },
    arah_rezeki: getArahRezeki(nTotal)
  };
}

function hitungJodoh(w1, w2) {
  const obj1 = typeof w1 === "string" ? parseWetonString(w1) : w1;
  const obj2 = typeof w2 === "string" ? parseWetonString(w2) : w2;

  const total = obj1.neptu_total + obj2.neptu_total;
  const sisa5 = total % 5;
  const sisa8 = total % 8;

  return {
    calon_1: { weton: obj1.weton, neptu: obj1.neptu_total },
    calon_2: { weton: obj2.weton, neptu: obj2.neptu_total },
    total_neptu_jodoh: total,
    perhitungan_sisa_5: {
      sisa: sisa5,
      kategori: ARTI_JODOH_5[sisa5].nama,
      makna: ARTI_JODOH_5[sisa5].makna
    },
    perhitungan_sisa_8: {
      sisa: sisa8,
      kategori: ARTI_JODOH_8[sisa8].nama,
      makna: ARTI_JODOH_8[sisa8].makna
    },
    sumber: "Kitab Primbon Betaljemur Adammakna Bab 15–25, Hlm. 12–18"
  };
}

// Router CLI
const args = process.argv.slice(2);
if (args.length === 0) {
  console.log(JSON.stringify({ petunjuk: "node hitung_weton.js [YYYY-MM-DD | --weton 'Senin Pahing' | --jodoh 'Minggu Pon' 'Rabu Pahing']" }));
  process.exit(0);
}

try {
  if (args[0] === "--weton" && args[1]) {
    console.log(JSON.stringify(parseWetonString(args.slice(1).join(" ")), null, 2));
  } else if (args[0] === "--jodoh" && args[1] && args[2]) {
    console.log(JSON.stringify(hitungJodoh(args[1], args[2]), null, 2));
  } else if (args[0] === "--jodoh-tgl" && args[1] && args[2]) {
    const w1 = hitungWeton(args[1]);
    const w2 = hitungWeton(args[2]);
    console.log(JSON.stringify(hitungJodoh(w1, w2), null, 2));
  } else if (args[0] === "--test") {
    const t1 = hitungWeton("1945-08-17");
    const t2 = parseWetonString("Senin Pahing");
    const t3 = hitungJodoh("Minggu Pon", "Rabu Pahing");
    console.log("TEST PASS:", t1.weton === "Jumat Legi" && t2.neptu_total === 13 && t3.total_neptu_jodoh === 28);
  } else {
    console.log(JSON.stringify(hitungWeton(args[0]), null, 2));
  }
} catch (err) {
  console.error(JSON.stringify({ error: err.message }));
  process.exit(1);
}
