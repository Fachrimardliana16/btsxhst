# Menyiapkan Cloudflare Tunnel bernama untuk bitesexhaust.com.
#
# Dijalankan SEKALI setelah `cloudflared tunnel login` berhasil dan cert.pem
# tersimpan di %USERPROFILE%\.cloudflared\.
#
# Yang dilakukan skrip ini:
#   1. membuat tunnel bernama "bites" (kalau belum ada)
#   2. menulis %USERPROFILE%\.cloudflared\bites.yml berisi ingress:
#        bitesexhaust.com   -> http://127.0.0.1:8200
#        www.bitesexhaust.com -> http://127.0.0.1:8200
#   3. membuat catatan DNS (CNAME) di zone bitesexhaust.com yang menunjuk
#      tunnel tersebut
#
# Prasyarat: zone bitesexhaust.com sudah AKTIF di akun Cloudflare Anda.
# Kalau name server belum diarahkan, langkah 3 gagal dengan pesan yang jelas —
# itu normal; jalankan ulang skrip ini setelah NS aktif.
#
# Pakai: powershell -ExecutionPolicy Bypass -File tools/siapkan-tunnel.ps1

param(
    [string]$NamaTunnel = 'bites',
    [int]$Port = 8200,
    [string]$Domain = 'bitesexhaust.com'
)

$ErrorActionPreference = 'Stop'

# cloudflared mencetak peringatan versi ke STDERR. PowerShell menganggap stderr
# dari proses native sebagai terminating error, yang menghentikan skrip padahal
# perintahnya berhasil. Peringatan itu karena itu ditelan secara eksplisit.
$PeringatanLama = $ErrorActionPreference
# Matikan sementara supaya stderr cloudflared tidak dianggap fatal.
$ErrorActionPreference = 'Continue'

$cloudflared = 'C:\Program Files (x86)\cloudflared\cloudflared.exe'
$dirCf       = Join-Path $env:USERPROFILE '.cloudflared'
$cert        = Join-Path $dirCf 'cert.pem'
$confYml     = Join-Path $dirCf "$NamaTunnel.yml"

if (-not (Test-Path $cloudflared)) { throw "cloudflared tidak ditemukan: $cloudflared" }

if (-not (Test-Path $cert)) {
    throw @"
cert.pem belum ada — Anda belum login.

Jalankan lebih dulu di terminal ini:
    "$cloudflared" tunnel login

Peramban akan terbuka. Pilih zone $Domain, lalu kembali ke sini.
"@
}

Write-Output "== 1/3 membuat tunnel '$NamaTunnel' (dilewati bila sudah ada) =="
# `cloudflared tunnel list` mencetak tabel, bukan JSON yang mudah diurai
# (ada peringatan versi di stderr). Cukup cari nama tunnel di teks tabelnya.
function Get-TunnelId([string] $nama) {
    $baris = & $cloudflared tunnel list 2>$null |
        Where-Object { $_ -match "\s$([regex]::Escape($nama))\s" } |
        Select-Object -First 1
    if (-not $baris) { return $null }
    # Kolom pertama adalah UUID tunnel.
    return ($baris -split '\s+')[0]
}

$tunnelId = Get-TunnelId $NamaTunnel

if (-not $tunnelId) {
    & $cloudflared tunnel create $NamaTunnel 2>&1 | Out-Null
    $tunnelId = Get-TunnelId $NamaTunnel
    if (-not $tunnelId) { throw "Gagal membuat tunnel '$NamaTunnel'." }
}

$ada = [pscustomobject]@{ id = $tunnelId; name = $NamaTunnel }

Write-Output "   tunnel id: $tunnelId"

$kredensial = Join-Path $dirCf "$tunnelId.json"
if (-not (Test-Path $kredensial)) {
    throw "Berkas kredensial tidak ada: $kredensial"
}

Write-Output "== 2/3 menulis $confYml =="
# catch-all 404 di akhir: permintaan ke host lain tidak diteruskan ke aplikasi.
$isi = @"
# Dibuat oleh tools/siapkan-tunnel.ps1
#
# Tunnel bernama, jadi alamatnya TETAP — berbeda dari tunnel trycloudflare
# acak yang berganti setiap restart.
tunnel: $NamaTunnel
credentials-file: $($kredensial -replace '\\','\\')

ingress:
  # Toko utama.
  - hostname: $Domain
    service: http://127.0.0.1:$Port
    originRequest:
      # cloudflared menambahkan X-Forwarded-Proto: https; Laravel memakai itu
      # untuk menyusun URL aset sebagai https (lihat PercayaiProxy).
      noTLSVerify: true
      connectTimeout: 30s

  # www diarahkan ke host yang sama; app tidak membedakan keduanya.
  - hostname: www.$Domain
    service: http://127.0.0.1:$Port
    originRequest:
      noTLSVerify: true
      connectTimeout: 30s

  # Wajib ada dan harus terakhir.
  - service: http_status:404
"@
Set-Content -Path $confYml -Value $isi -Encoding utf8
Write-Output '   selesai.'

Write-Output '== 3/3 membuat catatan DNS =="'
foreach ($host_ in @($Domain, "www.$Domain")) {
    Write-Output "   $host_ -> tunnel $NamaTunnel"
    $hasil = & $cloudflared tunnel route dns $NamaTunnel $host_ 2>&1
    Write-Output ("   " + ($hasil -join "`n   "))
}

$ErrorActionPreference = $PeringatanLama

Write-Output ''
Write-Output '  ===================================================='
Write-Output '   Selesai. Sekarang jalankan:'
Write-Output '     powershell -ExecutionPolicy Bypass -File tools/nyalakan-produksi.ps1'
Write-Output '  ===================================================='
Write-Output ''
Write-Output "  Catatan: jika langkah DNS gagal dengan pesan zone tidak ditemukan,"
Write-Output "  zone $Domain belum aktif di akun Cloudflare. Arahkan name server"
Write-Output "  domain ke NS yang diberikan Cloudflare lebih dulu, tunggu propagasi"
Write-Output "  (biasanya < 1 jam), lalu jalankan skrip ini lagi."
