"""
Uji beban sederhana: N permintaan bersamaan terhadap beberapa rute.

Ditulis sendiri karena `ab` dan `wrk` tidak tersedia di mesin ini. Yang diukur
hanya tiga hal yang paling menentukan: laju berhasil, waktu tanggap, dan
apakah ada galat 5xx. Bukan pengganti uji beban sungguhan (yang butuh mesin
terpisah, jaringan nyata, dan campuran pengunjung), tetapi cukup untuk
menjawab "apakah server tumbang pada beban X".

Pakai: python uji_beban.py [jumlah_total] [konkuren]
"""

import os
import statistics
import sys
import threading
import time
import urllib.error
import urllib.request
from collections import Counter

DASAR = os.environ.get("BEban_URL", "http://127.0.0.1:8102")
RUTE = ["/", "/produk", "/tentang", "/kontak"]

TOTAL = int(sys.argv[1]) if len(sys.argv) > 1 else 2000
KONKUREN = int(sys.argv[2]) if len(sys.argv) > 2 else 50

kunci = threading.Lock()
hasil = []
galat = Counter()
selesai = [0]


def satu_permintaan(i):
    jalur = RUTE[i % len(RUTE)]
    url = DASAR + jalur
    mulai = time.perf_counter()
    try:
        req = urllib.request.Request(url, headers={"Accept-Encoding": "gzip", "User-Agent": "uji-beban"})
        with urllib.request.urlopen(req, timeout=30) as r:
            r.read()
            kode = r.status
    except urllib.error.HTTPError as e:
        kode = e.code
    except Exception as e:  # noqa: BLE001
        kode = 0
        with kunci:
            galat[type(e).__name__] += 1

    lama = (time.perf_counter() - mulai) * 1000
    with kunci:
        hasil.append((kode, lama))
        selesai[0] += 1


def pekerja(indeks):
    for i in indeks:
        satu_permintaan(i)


def main():
    print(f"Target   : {DASAR}")
    print(f"Total    : {TOTAL} permintaan · konkurensi {KONKUREN}")
    print(f"Rute     : {', '.join(RUTE)}\n")

    # Panaskan cache lebih dulu: pengunjung pertama selalu lebih lambat.
    for r in RUTE:
        try:
            urllib.request.urlopen(DASAR + r, timeout=30).read()
        except Exception:
            pass

    mulai = time.perf_counter()
    bagian = [[] for _ in range(KONKUREN)]
    for i in range(TOTAL):
        bagian[i % KONKUREN].append(i)

    benang = [threading.Thread(target=pekerja, args=(b,)) for b in bagian]
    for t in benang:
        t.start()
    for t in benang:
        t.join()
    total_detik = time.perf_counter() - mulai

    kode = Counter(k for k, _ in hasil)
    waktu = sorted(l for _, l in hasil)
    n = len(waktu)

    def persen(p):
        return waktu[min(n - 1, int(n * p / 100))] if n else 0

    print("HASIL")
    print(f"  Durasi total     : {total_detik:.2f} s")
    print(f"  Throughput       : {n / total_detik:.1f} permintaan/detik")
    print(f"  Rata-rata        : {statistics.mean(waktu):.0f} ms")
    print(f"  Median (p50)     : {persen(50):.0f} ms")
    print(f"  p95              : {persen(95):.0f} ms")
    print(f"  p99              : {persen(99):.0f} ms")
    print(f"  Terlambat        : {max(waktu):.0f} ms")
    print()
    print("  Kode status:")
    for k, v in sorted(kode.items()):
        label = "OK" if k == 200 else ("GALAT JARINGAN" if k == 0 else "GAGAL")
        print(f"    {k:<4} {v:<6} {label}")
    if galat:
        print("  Jenis galat:", dict(galat))

    gagal = sum(v for k, v in kode.items() if k != 200)
    print()
    print(f"  Berhasil : {kode.get(200, 0)}/{n} ({kode.get(200,0)/n*100:.1f}%)")
    print("  VERDIKT  : " + ("TUMBANG" if gagal else "BERTAHAN"))


if __name__ == "__main__":
    main()
