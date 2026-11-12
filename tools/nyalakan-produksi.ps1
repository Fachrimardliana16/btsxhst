<#
.SYNOPSIS
    Menyalakan bitesexhaust.com: nginx + php-cgi + Cloudflare Tunnel (named).

.DESCRIPTION
    Berbeda dari tools/nyalakan.ps1 (pengembangan, tunnel trycloudflare acak),
    skrip ini memakai tunnel BERNAMA yang terikat ke akun Cloudflare Anda dan
    ke domain bitesexhaust.com. Alamatnya tetap, tidak berubah setiap restart.

    Tiga komponen:
      1. php-cgi   di 127.0.0.1:9300..9303   (FastCGI, 4 proses paralel)
      2. nginx     di 127.0.0.1:8200         (conf/bites.conf)
      3. cloudflared                          (tunnel bernama "bites")

    Hanya 127.0.0.1 yang mendengarkan. Tidak ada port yang terbuka ke LAN;
    satu-satunya jalan masuk publik adalah cloudflared, yang membuka koneksi
    KELUAR ke Cloudflare (tidak butuh port-forwarding / IP publik).

.PARAMETER Stop
    Hentikan ketiganya.

.PARAMETER TanpaTunnel
    Nyalakan nginx + php-cgi saja (uji lokal di http://127.0.0.1:8200).

.PARAMETER Port
    Port nginx lokal. Default 8200.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools/nyalakan-produksi.ps1
    powershell -ExecutionPolicy Bypass -File tools/nyalakan-produksi.ps1 -Stop
#>

param(
    [switch]$Stop,
    [switch]$TanpaTunnel,
    [int]$Port = 8200
)

$ErrorActionPreference = 'Stop'

$proyek      = 'D:\Projects\iksanknalpot2'
$nginxDir    = Join-Path $env:LOCALAPPDATA 'nginx\nginx-1.28.0'
$php         = Join-Path $env:LOCALAPPDATA 'php85\php-cgi.exe'
$phpCli      = Join-Path $env:LOCALAPPDATA 'php85\php.exe'
$cloudflared = 'C:\Program Files (x86)\cloudflared\cloudflared.exe'
$confTunnel  = Join-Path $env:USERPROFILE '.cloudflared\bites.yml'
$namaTunnel  = 'bites'
$portPhp     = 9300..9303
$berkasUrl   = Join-Path $proyek 'tools\out\produksi.txt'

function HentikanSemua {
    # Hanya proses milik TUMPUKAN PRODUKSI ini.
    # Pengembangan memakai port 8100/920x dan conf iksan.conf; jangan diganggu.

    # Perintah sebenarnya: cloudflared tunnel --config ...\bites.yml --no-autoupdate run bites
    # Setelah "tunnel " yang muncul adalah "--config", BUKAN "run bites", sehingga pola
    # lama ("tunnel\s+(run\s+)?bites") TIDAK PERNAH cocok dan proses lama menumpuk.
    # Cocokkan lewat "run <nama>" ATAU berkas konfigurasi tunnel ini.
    Get-CimInstance Win32_Process -Filter "Name='cloudflared.exe'" -ErrorAction SilentlyContinue |
        Where-Object {
            $_.CommandLine -match "run\s+$namaTunnel(\s|`"|$)" -or
            $_.CommandLine -match [regex]::Escape("$namaTunnel.yml")
        } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

    Get-CimInstance Win32_Process -Filter "Name='php-cgi.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -match ':930[0-9]' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

    Get-CimInstance Win32_Process -Filter "Name='nginx.exe'" -ErrorAction SilentlyContinue |
        Where-Object { $_.CommandLine -match 'bites\.conf' } |
        ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
}

function Cek-Port([int]$p) {
    $klien = New-Object System.Net.Sockets.TcpClient
    try {
        $tugas = $klien.ConnectAsync('127.0.0.1', $p)
        if ($tugas.Wait(700)) { return $klien.Connected }
        return $false
    } catch {
        return $false
    } finally {
        $klien.Dispose()
    }
}

if ($Stop) {
    HentikanSemua
    Remove-Item $berkasUrl -ErrorAction SilentlyContinue
    Write-Output 'Tumpukan produksi dihentikan (nginx, php-cgi, cloudflared).'
    exit 0
}

# ---------------------------------------------------------------- prasyarat
foreach ($f in @($php, $phpCli, $cloudflared, (Join-Path $nginxDir 'nginx.exe'))) {
    if (-not (Test-Path $f)) { throw "Tidak ditemukan: $f" }
}
if (-not (Test-Path (Join-Path $proyek '.env.production'))) {
    throw "Tidak ditemukan $proyek\.env.production"
}

HentikanSemua
Start-Sleep -Milliseconds 800

# ---------------------------------------------------------------- aset & cache
Write-Output '== 1/5 build aset (vite) =='
Push-Location $proyek
try {
    if (-not (Test-Path (Join-Path $proyek 'public\build\manifest.json'))) {
        npm run build 2>&1 | Out-String | Write-Output
    } else {
        Write-Output '   public/build/manifest.json sudah ada, dilewati.'
    }

    # Semua perintah artisan di bawah HARUS memakai .env.production.
    # Tanpa --env=production, artisan akan membaca .env (pengembangan) dan
    # membangun cache konfigurasi yang salah — situs lalu berjalan dengan
    # APP_DEBUG=true dan database pengembangan.
    Write-Output '== 2/5 cache konfigurasi, rute, view, event =='
    foreach ($perintah in @('config:clear', 'route:clear', 'view:clear',
                            'config:cache', 'route:cache', 'view:cache', 'event:cache')) {
        & $phpCli artisan $perintah --env=production --no-interaction 2>&1 | Out-Null
        Write-Output "   $perintah"
    }

    # Storage link untuk /storage/* (foto produk).
    #
    # DIHARAPKAN GAGAL di mesin ini: volume D: berformat exFAT, yang tidak
    # mendukung symlink/junction, sehingga artisan melempar
    # "Local NTFS volumes are required to complete the operation".
    # Itu bukan kesalahan yang perlu menghentikan penyalakan: nginx sendiri
    # yang menyajikan /storage/ langsung dari storage/app/public (lihat
    # conf/bites.conf). Karena itu kegagalan di sini ditelan dengan sengaja.
    if (-not (Test-Path (Join-Path $proyek 'public\storage'))) {
        # 2>&1 | Out-Null TIDAK cukup: dengan $ErrorActionPreference='Stop',
        # PowerShell mengubah stderr proses native menjadi terminating error
        # dan skrip berhenti tepat di sini. Diperlukan try/catch eksplisit.
        try {
            & $phpCli artisan storage:link --env=production --no-interaction 2>&1 | Out-Null
        } catch {
            # Diharapkan di exFAT; alasannya ada di komentar di atas.
        }
        if (Test-Path (Join-Path $proyek 'public\storage')) {
            Write-Output '   storage:link dibuat'
        } else {
            Write-Output '   storage:link dilewati (exFAT: symlink tidak didukung; nginx melayani /storage/ langsung)'
        }
    }
} finally {
    Pop-Location
}

# ---------------------------------------------------------------- php-cgi
Write-Output '== 3/5 php-cgi =='
$env:PHP_FCGI_CHILDREN   = '1'
$env:PHP_FCGI_MAX_REQUESTS = '500'
$env:REDIRECT_STATUS     = '200'
# CGI TIDAK membaca .env dengan sendirinya, tetapi PHP membaca file .env
# lewat dotenv Laravel saat boot. Untuk memastikan CGI memakai berkas
# produksi, .env.production disalin ke .env SAAT tumpukan produksi menyala?
# TIDAK. Kedua lingkungan memakai .env yang sama-sama dibaca; karena itu
# CGI dijalankan dengan APP_ENV eksplisit di environment proses.
$env:APP_ENV = 'production'

foreach ($p in $portPhp) {
    Start-Process -FilePath $php `
        -ArgumentList @('-b', "127.0.0.1:$p", '-c', (Join-Path (Split-Path $php) 'php.ini')) `
        -WorkingDirectory $proyek `
        -WindowStyle Hidden
}

Start-Sleep -Seconds 3
$hidup = 0
foreach ($p in $portPhp) { if (Cek-Port $p) { $hidup++ } }
Write-Output "   php-cgi menjawab: $hidup/$($portPhp.Count)"
if ($hidup -eq 0) { throw 'Tidak satu pun php-cgi hidup. Periksa php.ini / port 930x.' }

# ---------------------------------------------------------------- nginx
Write-Output '== 4/5 nginx =='
# nginx.exe tidak mem-daemon-kan diri di Windows: Start-Process TANPA -Wait.
Start-Process -FilePath (Join-Path $nginxDir 'nginx.exe') `
    -ArgumentList @('-p', $nginxDir, '-c', 'conf/bites.conf') `
    -WorkingDirectory $nginxDir `
    -WindowStyle Hidden

Start-Sleep -Seconds 3
if (-not (Cek-Port $Port)) {
    throw "nginx tidak menjawab di 127.0.0.1:$Port. Periksa tools/out/nginx-bites-error.log"
}
Write-Output "   nginx hidup di 127.0.0.1:$Port"

$lokal = $false
foreach ($i in 1..20) {
    try {
        $r = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/" -UseBasicParsing -TimeoutSec 5
        if ($r.StatusCode -eq 200) { $lokal = $true; break }
    } catch { Start-Sleep -Milliseconds 500 }
}
if (-not $lokal) { throw "Lokal 127.0.0.1:$Port tidak menjawab 200." }
Write-Output '   lokal 200 OK.'

if ($TanpaTunnel) {
    Write-Output ''
    Write-Output '  ===================================================='
    Write-Output "   HANYA LOKAL:  http://127.0.0.1:$Port"
    Write-Output '   (tunnel DILEWATI — -TanpaTunnel)'
    Write-Output '  ===================================================='
    exit 0
}

# ---------------------------------------------------------------- tunnel
Write-Output '== 5/5 cloudflared tunnel =='
if (-not (Test-Path $confTunnel)) {
    throw "Konfigurasi tunnel tidak ada: $confTunnel. Jalankan tools/siapkan-tunnel.ps1 lebih dulu."
}

$logTunnel = Join-Path $proyek 'tools\out\tunnel-bites.log'
Remove-Item $logTunnel -ErrorAction SilentlyContinue

Start-Process -FilePath $cloudflared `
    -ArgumentList @('tunnel', '--config', $confTunnel, '--no-autoupdate', 'run', $namaTunnel) `
    -RedirectStandardError $logTunnel `
    -RedirectStandardOutput (Join-Path $proyek 'tools\out\tunnel-bites.out.log') `
    -WindowStyle Hidden

# Tunggu koneksi terdaftar, bukan menebak lama tidurnya.
$terhubung = $false
foreach ($i in 1..40) {
    Start-Sleep -Milliseconds 500
    if (Test-Path $logTunnel) {
        if (Select-String -Path $logTunnel -Pattern 'Registered tunnel connection' -Quiet -ErrorAction SilentlyContinue) {
            $terhubung = $true
            break
        }
    }
}
if (-not $terhubung) {
    Write-Output '   PERINGATAN: "Registered tunnel connection" belum muncul di log.'
    Write-Output "   Periksa $logTunnel"
}

$url = 'https://bitesexhaust.com'
# Cek publik dibatasi waktunya dengan sengaja.
#
# Sebelumnya loop ini menunggu sampai 40 x (10 detik timeout + 1 detik jeda)
# = sampai 7 menit ketika domain belum menjawab, dan seluruh skrip tampak
# menggantung. Tunnel yang sudah terhubung tetap dianggap BERHASIL meski
# domain belum menjawab (mis. saat DNS belum propagasi), jadi cukup coba
# beberapa kali lalu lanjut.
$publik = $false
foreach ($i in 1..5) {
    try {
        $r = Invoke-WebRequest -Uri "$url/" -UseBasicParsing -TimeoutSec 8 -MaximumRedirection 5
        if ($r.StatusCode -ge 200 -and $r.StatusCode -lt 400) { $publik = $true; break }
    } catch { Start-Sleep -Seconds 2 }
}

@(
    "Lokal  : http://127.0.0.1:$Port"
    "Publik : $url"
    "Admin  : $url/admin/login"
    "Waktu  : $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')"
) | Set-Content -Path $berkasUrl -Encoding utf8

Write-Output ''
Write-Output '  ===================================================='
if ($publik) {
    Write-Output "   SIAP (publik menjawab):  $url/admin/login"
} elseif ($terhubung) {
    Write-Output "   Tunnel terhubung, tetapi $url belum menjawab 200."
    Write-Output '   Selama name server belum diarahkan ke Cloudflare, ini NORMAL.'
    Write-Output "   Lokal tetap dapat dipakai: http://127.0.0.1:$Port"
} else {
    Write-Output '   Tunnel BELUM terhubung. Lihat tools/out/tunnel-bites.log'
}
Write-Output '  ===================================================='
Write-Output ''
Write-Output "  Ringkasan disimpan di: $berkasUrl"
