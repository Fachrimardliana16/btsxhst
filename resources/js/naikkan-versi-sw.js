/**
 * Menaikkan versi cache service worker setiap kali aset di-build.
 *
 * KENAPA INI ADA
 * --------------
 * `public/sw.js` menyimpan aset dengan strategi cache-first: salinan yang
 * sudah ada selalu dipakai lebih dulu, tanpa pernah bertanya ke server. Itu
 * benar HANYA bila nama berkas berubah setiap kali isinya berubah — dan nama
 * dari Vite memang memuat hash, jadi sebagian besar aman.
 *
 * Masalahnya bukan di nama berkas, melainkan di BERKAS INDUK yang dirujuk:
 * HTML halaman dipegang peramban, dan service worker memegang salinan aset.
 * Selama `VERSI` di sw.js tidak berubah, blok `activate` tidak pernah
 * menghapus cache lama, sehingga aset yang namanya kebetulan sama (atau
 * permintaan tanpa hash) tetap disajikan dari salinan usang.
 *
 * Gejala nyatanya sudah pernah terjadi di proyek ini: CSS sudah diperbaiki dan
 * di-build, tetapi tampilan di peramban tidak berubah sama sekali.
 *
 * Skrip ini dijalankan sebagai langkah `prebuild`, jadi setiap `npm run build`
 * menghasilkan versi cache baru dan pengunjung menerima aset yang benar pada
 * kunjungan berikutnya.
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';

const akar = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const berkas = resolve(akar, 'public/sw.js');

const isi = readFileSync(berkas, 'utf8');
const pola = /const VERSI = 'iksan-v(\d+)';/;
const cocok = isi.match(pola);

if (!cocok) {
    console.error('[sw] Tidak menemukan "const VERSI = \'iksan-vN\';" di public/sw.js');
    process.exit(1);
}

const baru = Number(cocok[1]) + 1;

writeFileSync(berkas, isi.replace(pola, `const VERSI = 'iksan-v${baru}';`), 'utf8');

console.log(`[sw] Versi cache dinaikkan: v${cocok[1]} → v${baru}`);
