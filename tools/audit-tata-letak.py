"""Audit tata letak beranda di Chromium nyata (headless + CDP).

Yang diperiksa, dan kenapa:

  LUAPAN     elemen yang melewati lebar layar (scrollWidth > clientWidth).
             Ini penyebab paling umum "berantakan": satu elemen lebar memaksa
             seluruh halaman bergulir mendatar.
  TUMPUKAN   pasangan blok utama yang saling menimpa (garis-garis wilayahnya
             beririsan), mis. navbar di atas hero, ticker di atas hero.
  JARAK      celah vertikal antar blok utama: 0 px berarti menempel,
             terlalu besar berarti ada ruang kosong menganga.
  TINGGI     tinggi tiap blok terhadap tinggi layar. Blok > 100vh di ponsel
             berarti pengunjung harus menggulir lebih dari satu layar untuk
             satu bagian.
  GULIR      scrollWidth dokumen vs innerWidth: halaman punya guliran
             mendatar tersembunyi atau tidak.

Diukur pada tiga lebar: 390 (ponsel), 768 (tablet), 1366 (desktop).
Keluarannya JSON + ringkasan teks; tidak ada tangkapan layar karena audit ini
soal geometri, bukan warna.

Pakai: python tools/audit-tata-letak.py [url]
"""

from __future__ import annotations

import json
import os
import shutil
import subprocess
import sys
import tempfile
import time
import urllib.error
import urllib.request

import websocket

CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"
URL_BAWAAN = "http://127.0.0.1:8102/"
PORT = 9331

# Blok utama yang diperiksa jarak & tumpukannya, berurutan dari atas.
BLOK = [
    "header.race-navbar",
    ".promo-ticker",
    "section.hero-race",
    ".garage-card",
    "section.race-about",
    "section.race-categories",
    "section.race-catalog",
    "footer",
    "nav.fixed.bottom-0",
]

SKRIP = r"""
(() => {
  const blok = %s;
  const lebar = window.innerWidth;
  const tinggiLayar = window.innerHeight;

  const kotak = (sel) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const r = el.getBoundingClientRect();
    const gaya = getComputedStyle(el);
    return {
      sel,
      atas: Math.round(r.top + window.scrollY),
      bawah: Math.round(r.bottom + window.scrollY),
      tinggi: Math.round(r.height),
      lebar: Math.round(r.width),
      kiri: Math.round(r.left + window.scrollX),
      kanan: Math.round(r.right + window.scrollX),
      padAtas: Math.round(parseFloat(gaya.paddingTop) || 0),
      padBawah: Math.round(parseFloat(gaya.paddingBottom) || 0),
      marAtas: Math.round(parseFloat(gaya.marginTop) || 0),
      display: gaya.display,
      posisi: gaya.position,
    };
  };

  const daftar = blok.map(kotak).filter(Boolean);

  // Luapan: elemen yang lebih lebar dari layar atau menembus tepi kanan.
  const luapan = [];
  document.querySelectorAll('body *').forEach((el) => {
    const r = el.getBoundingClientRect();
    if (r.width === 0 || r.height === 0) return;
    if (r.right > lebar + 1 || r.left < -1) {
      const gaya = getComputedStyle(el);
      if (gaya.position === 'fixed' && gaya.visibility === 'hidden') return;
      luapan.push({
        tag: el.tagName.toLowerCase(),
        kelas: (el.className || '').toString().slice(0, 90),
        kiri: Math.round(r.left),
        kanan: Math.round(r.right),
        lebar: Math.round(r.width),
      });
    }
  });

  const tumpukan = [];
  for (let i = 0; i < daftar.length; i++) {
    for (let j = i + 1; j < daftar.length; j++) {
      const a = daftar[i], b = daftar[j];
      const irisanY = Math.min(a.bawah, b.bawah) - Math.max(a.atas, b.atas);
      const irisanX = Math.min(a.kanan, b.kanan) - Math.max(a.kiri, b.kiri);
      if (irisanY > 4 && irisanX > 4) {
        tumpukan.push({ a: a.sel, b: b.sel, tinggiIrisan: irisanY });
      }
    }
  }

  const jarak = [];
  for (let i = 1; i < daftar.length; i++) {
    const atas = daftar[i - 1], bawah = daftar[i];
    // Hanya blok yang berurutan tegak lurus (tidak berdampingan).
    if (bawah.atas < atas.atas) continue;
    jarak.push({ dari: atas.sel, ke: bawah.sel, celah: bawah.atas - atas.bawah });
  }

  return JSON.stringify({
    lebar,
    tinggiLayar,
    gulirMendatar: document.documentElement.scrollWidth - window.innerWidth,
    tinggiDokumen: document.documentElement.scrollHeight,
    blok: daftar,
    tumpukan,
    jarak,
    luapan: luapan.slice(0, 25),
    jumlahLuapan: luapan.length,
  });
})()
"""


def tunggu(port: int, batas: float = 20.0) -> bool:
    akhir = time.time() + batas
    while time.time() < akhir:
        try:
            with urllib.request.urlopen(f"http://127.0.0.1:{port}/json/version", timeout=1):
                return True
        except (urllib.error.URLError, OSError):
            time.sleep(0.25)
    return False


def ukur(url: str, lebar: int, tinggi: int) -> dict:
    akar = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out", "profil-audit")
    os.makedirs(akar, exist_ok=True)
    profil = tempfile.mkdtemp(prefix="audit-", dir=akar)

    proses = subprocess.Popen(
        [CHROME, "--headless=new", f"--remote-debugging-port={PORT}", f"--user-data-dir={profil}",
         "--no-first-run", "--no-default-browser-check", "--disable-gpu", "--remote-allow-origins=*",
         f"--window-size={lebar},{tinggi}", "about:blank"],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )

    try:
        if not tunggu(PORT):
            raise RuntimeError("Chrome tidak membuka port debugging.")

        with urllib.request.urlopen(f"http://127.0.0.1:{PORT}/json/list", timeout=5) as r:
            target = next(t for t in json.load(r) if t["type"] == "page")

        ws = websocket.create_connection(target["webSocketDebuggerUrl"], timeout=60, suppress_origin=True)
        nomor = [0]

        def kirim(metode: str, **params):
            nomor[0] += 1
            ws.send(json.dumps({"id": nomor[0], "method": metode, "params": params}))
            while True:
                pesan = json.loads(ws.recv())
                if pesan.get("id") == nomor[0]:
                    return pesan.get("result", pesan)

        kirim("Page.enable")
        kirim("Runtime.enable")
        kirim("Emulation.setDeviceMetricsOverride",
              width=lebar, height=tinggi, deviceScaleFactor=1, mobile=lebar < 768)

        kirim("Page.navigate", url=url)
        akhir = time.time() + 30
        while time.time() < akhir:
            siap = kirim("Runtime.evaluate", expression="document.readyState", returnByValue=True)
            if siap.get("result", {}).get("value") == "complete":
                break
            time.sleep(0.15)

        time.sleep(1.2)

        hasil = kirim("Runtime.evaluate",
                      expression=SKRIP % json.dumps(BLOK),
                      returnByValue=True)
        data = json.loads(hasil["result"]["value"])
        ws.close()

        return data
    finally:
        proses.terminate()
        try:
            proses.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proses.kill()
        shutil.rmtree(profil, ignore_errors=True)


def ringkas(nama: str, data: dict) -> list[str]:
    baris = [f"\n=== {nama}  (layar {data['lebar']}×{data['tinggiLayar']}) ==="]

    if data["gulirMendatar"] > 0:
        baris.append(f"  !! GULIR MENDATAR: {data['gulirMendatar']}px lebih lebar dari layar")
    else:
        baris.append("  gulir mendatar: tidak ada")

    baris.append(f"  tinggi dokumen: {data['tinggiDokumen']}px")

    baris.append("  blok:")
    for b in data["blok"]:
        rasio = b["tinggi"] / data["tinggiLayar"] if data["tinggiLayar"] else 0
        tanda = " <<< >1 layar" if rasio > 1.05 else ""
        baris.append(f"    {b['sel']:<28} atas={b['atas']:<6} tinggi={b['tinggi']:<6} "
                     f"pad={b.get('padAtas',0)}/{b.get('padBawah',0)} mar={b.get('marAtas',0)} "
                     f"({rasio:.2f}×layar, {b['posisi']}){tanda}")

    if data["tumpukan"]:
        baris.append("  TUMPUKAN:")
        for t in data["tumpukan"]:
            baris.append(f"    {t['a']}  ×  {t['b']}  ({t['tinggiIrisan']}px)")
    else:
        baris.append("  tumpukan: tidak ada")

    baris.append("  jarak antar blok:")
    for j in data["jarak"]:
        tanda = " <<< NEMPEL" if j["celah"] <= 0 else (" <<< JAUH" if j["celah"] > 160 else "")
        baris.append(f"    {j['dari']} → {j['ke']}: {j['celah']}px{tanda}")

    if data["jumlahLuapan"]:
        baris.append(f"  LUAPAN ({data['jumlahLuapan']} elemen):")
        for l in data["luapan"][:12]:
            baris.append(f"    <{l['tag']} class=\"{l['kelas']}\"> kiri={l['kiri']} kanan={l['kanan']} lebar={l['lebar']}")
    else:
        baris.append("  luapan: tidak ada")

    return baris


def main() -> None:
    url = sys.argv[1] if len(sys.argv) > 1 else URL_BAWAAN
    semua = []
    for nama, (w, h) in {"ponsel": (390, 844), "tablet": (768, 1024), "desktop": (1366, 900)}.items():
        data = ukur(url, w, h)
        semua.append({"nama": nama, "data": data})

    keluaran = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out", "audit-tata-letak.json")
    with open(keluaran, "w", encoding="utf-8") as f:
        json.dump(semua, f, ensure_ascii=False, indent=2)

    for item in semua:
        print("\n".join(ringkas(item["nama"], item["data"])))

    print(f"\nJSON lengkap: {keluaran}")


if __name__ == "__main__":
    main()
