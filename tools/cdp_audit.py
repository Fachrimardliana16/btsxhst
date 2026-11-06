"""
Helper CDP untuk audit UI/UX Bites Exhaust.

Dipisah ke berkas supaya dapat dipakai ulang dari beberapa panggilan tanpa
menulis ulang logika websocket. Chrome headless dijalankan terpisah
(port 9444, profil sementara) agar tidak mengganggu Chrome pengguna.

Pakai dari skrip lain:

    from cdp_audit import koneksi
    t = koneksi(lebar=390, tinggi=844)
    t.goto("http://127.0.0.1:8200/")
    print(t.js("document.title"))
    t.close()
"""

import json
import time
import urllib.request

import websocket

CDP = "http://127.0.0.1:9444"

# Skrip pengukuran tata letak. Dikembalikan sebagai dict.
SCRIPT_UKUR = r"""
(() => {
  const r = (sel) => {
    const el = document.querySelector(sel);
    if (!el) return null;
    const b = el.getBoundingClientRect();
    return {top:Math.round(b.top), bottom:Math.round(b.bottom),
            left:Math.round(b.left), right:Math.round(b.right),
            w:Math.round(b.width), h:Math.round(b.height)};
  };
  const nomor = [...document.querySelectorAll('.part-no,[class*="part-no"]')]
      .map(e => e.textContent.trim()).filter(t => /^\d{2}\s*\//.test(t));
  const dup = [...document.querySelectorAll('article')].map(k => {
      const n = ((k.innerText||'').match(/Habis/g)||[]).length;
      return n;
    }).filter(n => n > 0);
  const m = document.querySelector('.navbar-merek span:first-child');
  const brand = m ? {sw:m.scrollWidth, cw:m.clientWidth,
                     potong:m.scrollWidth>m.clientWidth+1,
                     teks:m.textContent.trim()} : null;
  const gal = document.querySelector('.galeri-bersih') ||
              document.querySelector('.galeri-editorial');
  const galeri = gal ? {items: gal.children.length,
                        tinggi: Math.round(gal.getBoundingClientRect().height),
                        kelasAnak: [...gal.children].map(c=>c.className.split(' ')[0]),
                        transform: [...gal.children].map(c=>getComputedStyle(c).transform !== 'none'),
                        bayangan: [...gal.children].map(c=>getComputedStyle(c).boxShadow !== 'none'),
                       } : null;
  // Elemen yang bisa menumpuk di dasar layar
  const melayang = {};
  ['.bottom-bar-cart', '.nav-bawah__kaca', '.wa-melayang', '[class*="whatsapp-fab"]']
    .forEach(s => { const v = r(s); if (v) melayang[s] = v; });
  return {
    tinggiHalaman: document.documentElement.scrollHeight,
    navbar: r('header') || r('.navbar'),
    footer: r('footer'),
    navBawah: r('.nav-bawah'),
    navKaca: r('.nav-bawah__kaca'),
    barKeranjang: r('.bottom-bar-cart'),
    nomorBagian: nomor,
    duplikatStok: dup,
    brand, galeri, melayang,
  };
})()
"""


class Tab:
    def __init__(self, ws_url):
        self.ws = websocket.create_connection(ws_url, timeout=60)
        self.i = 0

    def cmd(self, method, **params):
        self.i += 1
        self.ws.send(json.dumps({"id": self.i, "method": method, "params": params}))
        while True:
            m = json.loads(self.ws.recv())
            if m.get("id") == self.i:
                return m

    def js(self, expr):
        r = self.cmd("Runtime.evaluate", expression=expr,
                     returnByValue=True, awaitPromise=True)
        res = r.get("result", {})
        if "exceptionDetails" in res:
            raise RuntimeError(str(res["exceptionDetails"])[:400])
        return res["result"].get("value")

    def goto(self, url, tunggu=2.5):
        self.cmd("Page.navigate", url=url)
        time.sleep(tunggu)

    def ukur(self):
        return self.js(SCRIPT_UKUR)

    def close(self):
        try:
            self.ws.close()
        except Exception:
            pass


def koneksi(lebar=390, tinggi=844, mobile=True):
    tab = json.load(urllib.request.urlopen(
        urllib.request.Request(f"{CDP}/json/new?about:blank", method="PUT"),
        timeout=15))
    t = Tab(tab["webSocketDebuggerUrl"])
    t.cmd("Emulation.setDeviceMetricsOverride",
          width=lebar, height=tinggi, deviceScaleFactor=2, mobile=mobile)
    return t
