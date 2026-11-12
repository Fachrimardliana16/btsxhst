<#
.SYNOPSIS
    Penjaga produksi bitesexhaust.com - memulihkan tumpukan bila benar-benar mati.

.DESCRIPTION
    Dijalankan Windows Scheduled Task tiap 5 menit, saat boot, dan saat logon.

    KENAPA RESTART DIPICU TERPISAH (detached)
    -----------------------------------------
    Versi pertama menjalankan nyalakan-produksi.ps1 langsung di dalam proses
    penjaga. Akibatnya nginx, php-cgi, dan cloudflared menjadi ANAK dari
    proses penjaga. Task Scheduler menganggap tugas belum selesai selama
    masih ada proses hidup di job object-nya, sehingga penjaga berstatus
    "Running" sampai ExecutionTimeLimit (15 menit) lalu diterminasi
    (kode 267014) - walau situs sudah pulih sejak menit ke-2.

    Sekarang penjaga hanya MEMICU restart lewat WMI (Win32_Process.Create).
    Proses yang lahir dari WMI berada di luar job object tugas, jadi penjaga
    selesai normal dan hasil tugas tercatat 0.

    PRINSIP PEMERIKSAAN
    -------------------
    * lokal 8200 dicoba 3x dengan jeda 3 detik; baru dianggap mati bila
      ketiganya gagal. Satu probe gagal bukan alasan restart.
    * publik gagal tidak langsung me-restart. Butuh 2 kegagalan berturut
      (10 menit) supaya gangguan sesaat Cloudflare tidak memicu downtime.
    * restart hanya bila lokal 8200 bukan 200, atau proses tunnel "bites"
      tidak ada.

.NOTES
    Hanya menyentuh tumpukan PRODUKSI (tunnel "bites", port 8200/930x).
    Tunnel lain di mesin ini (9router, port 20128) tidak diganggu.

    Berkas ini sengaja ASCII murni. PowerShell 5.1 membaca berkas tanpa BOM
    sebagai ANSI; karakter seperti em dash akan rusak dan dapat dipakai
    sebagai tanda kutip, sehingga skrip gagal parse.
#>

$ErrorActionPreference = 'Continue'

# Tumpukan ini milik profil IT-LAPTOP. Bila tugas berjalan di konteks lain,
# $env:LOCALAPPDATA/$env:USERPROFILE bisa menunjuk profil yang salah.
$profilPemilik = 'C:\Users\IT-LAPTOP'
if ($env:USERPROFILE -ne $profilPemilik) {
    $env:USERPROFILE  = $profilPemilik
    $env:LOCALAPPDATA = Join-Path $profilPemilik 'AppData\Local'
    $env:APPDATA      = Join-Path $profilPemilik 'AppData\Roaming'
}

$proyek      = 'D:\Projects\iksanknalpot2'
$log         = Join-Path $proyek 'tools\out\jaga.log'
$kunci       = Join-Path $proyek 'tools\out\jaga.lock'
$berkasGagal = Join-Path $proyek 'tools\out\jaga-publik-gagal.txt'
$pemicu      = Join-Path $proyek 'tools\jalankan-penyalakan.cmd'

function Tulis([string]$pesan) {
    try {
        Add-Content -Path $log -Encoding utf8 -Value (
            '{0} {1}' -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $pesan)
    } catch { }
}

function CekHttp([string]$url, [int]$detik = 8) {
    try {
        $r = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec $detik
        return [int]$r.StatusCode
    } catch {
        return 0
    }
}

function TunnelHidup {
    $ada = $false
    Get-CimInstance Win32_Process -Filter "Name='cloudflared.exe'" -ErrorAction SilentlyContinue |
        ForEach-Object {
            if ($_.CommandLine -match 'run\s+bites(\s|"|$)' -or
                $_.CommandLine -match [regex]::Escape('bites.yml')) { $ada = $true }
        }
    return $ada
}

function PenyalakanBerjalan {
    $ada = $false
    Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" -ErrorAction SilentlyContinue |
        ForEach-Object {
            if ($_.CommandLine -match 'nyalakan-produksi\.ps1') { $ada = $true }
        }
    return $ada
}

# Picu restart di proses TERPISAH (di luar job object tugas).
function PicuPenyalakan {
    if (-not (Test-Path $pemicu)) {
        Tulis ('GAGAL: berkas pemicu tidak ada: ' + $pemicu)
        return $false
    }
    try {
        $hasil = ([wmiclass]'Win32_Process').Create(
            ('cmd.exe /c "{0}"' -f $pemicu), $proyek, $null)
        if ($hasil.ReturnValue -eq 0) {
            Tulis ('penyalakan dipicu (PID ' + $hasil.ProcessId + ')')
            return $true
        }
        Tulis ('GAGAL memicu penyalakan, ReturnValue=' + $hasil.ReturnValue)
        return $false
    } catch {
        Tulis ('GAGAL memicu penyalakan: ' + $_.Exception.Message)
        return $false
    }
}

# --- rotasi log ---
try {
    if (Test-Path $log) {
        $isi = @(Get-Content -Path $log -ErrorAction SilentlyContinue)
        if ($isi.Count -gt 2000) { $isi[-800..-1] | Set-Content -Path $log -Encoding utf8 }
    }
} catch { }

# --- kunci: hindari dua penjaga berjalan bersamaan ---
#
# Kunci menyimpan PID, bukan sekadar waktu. Kunci berbasis waktu murni bisa
# membuat penjaga DIAM sampai 20 menit setelah crash keras atau reboot di
# tengah putaran - persis jendela buta yang harus dihindari. Dengan PID,
# kunci dari proses yang sudah mati langsung diabaikan.
if (Test-Path $kunci) {
    $kunciUsang = $false
    try {
        $isi = Get-Content $kunci -ErrorAction Stop | Select-Object -First 1
        $pidLama = 0
        if ([int]::TryParse("$isi".Trim(), [ref]$pidLama) -and $pidLama -gt 0) {
            $proses = Get-Process -Id $pidLama -ErrorAction SilentlyContinue
            if ($proses -and $proses.ProcessName -eq 'powershell') {
                exit 0   # penjaga lain memang masih berjalan
            }
            $kunciUsang = $true
            Tulis ('kunci dari PID ' + $pidLama + ' sudah mati, diabaikan')
        } else {
            $umur = (Get-Date) - (Get-Item $kunci).LastWriteTime
            if ($umur.TotalMinutes -lt 20) { exit 0 }
            $kunciUsang = $true
            Tulis ('kunci lama ' + [int]$umur.TotalMinutes + ' menit, diabaikan')
        }
    } catch { $kunciUsang = $true }
    if (-not $kunciUsang) { exit 0 }
}
try { Set-Content -Path $kunci -Value $PID -Encoding utf8 } catch { }

try {
    $tunnelHidup = TunnelHidup

    # Lokal: 3 percobaan. Satu kegagalan sesaat bukan alasan restart.
    $lokalOk = $false
    for ($i = 1; $i -le 3; $i++) {
        if ((CekHttp 'http://127.0.0.1:8200/' 8) -eq 200) { $lokalOk = $true; break }
        if ($i -lt 3) { Start-Sleep -Seconds 3 }
    }

    # --- Kasus 1: tumpukan lokal mati atau tunnel hilang -> restart penuh ---
    if (-not $lokalOk -or -not $tunnelHidup) {
        $alasan = @()
        if (-not $lokalOk)     { $alasan += 'lokal 8200 bukan 200 (3 percobaan)' }
        if (-not $tunnelHidup) { $alasan += 'proses tunnel bites tidak ada' }
        $teks = $alasan -join '; '

        if (PenyalakanBerjalan) {
            Tulis ('TIDAK SEHAT: ' + $teks + ' - penyalakan sudah berjalan, dilewati')
            exit 0
        }

        Tulis ('TIDAK SEHAT: ' + $teks + ' -> picu penyalakan')
        if (-not (PicuPenyalakan)) { exit 1 }

        # Tunggu sampai pulih (maks 100 detik). Proses restart berjalan di
        # luar job object, jadi menunggu di sini tidak membuat tugas macet.
        $pulih = $false
        for ($i = 1; $i -le 50; $i++) {
            Start-Sleep -Seconds 2
            if ((CekHttp 'http://127.0.0.1:8200/' 6) -eq 200) { $pulih = $true; break }
        }

        # cloudflared butuh beberapa detik untuk muncul setelah dipicu. Tanpa
        # jeda ini, log mencatat "tunnel=False" padahal prosesnya baru lahir
        # sedetik kemudian - laporan palsu yang membuat penjaga tampak rusak.
        $tunnelPulih = $false
        for ($i = 1; $i -le 15; $i++) {
            if (TunnelHidup) { $tunnelPulih = $true; break }
            Start-Sleep -Seconds 2
        }

        Tulis ('selesai penyalakan: lokal200=' + $pulih + ' tunnel=' + $tunnelPulih)
        exit 0
    }

    # --- Kasus 2: lokal dan tunnel sehat -> cek publik (2 kali berturut) ---
    $cb = [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()
    $publik = CekHttp ("https://bitesexhaust.com/?jaga=$cb") 15

    if ($publik -eq 200) {
        if (Test-Path $berkasGagal) { Set-Content -Path $berkasGagal -Value '0' -Encoding utf8 }
        exit 0
    }

    $gagal = 0
    if (Test-Path $berkasGagal) {
        $teksLama = Get-Content $berkasGagal -ErrorAction SilentlyContinue | Select-Object -First 1
        [int]::TryParse("$teksLama".Trim(), [ref]$gagal) | Out-Null
    }
    $gagal++
    Set-Content -Path $berkasGagal -Value "$gagal" -Encoding utf8

    if ($gagal -lt 2) {
        Tulis ('publik=' + $publik + ' gagal ke-' + $gagal + ' (lokal 200, nginx tidak disentuh)')
        exit 0
    }

    if (PenyalakanBerjalan) {
        Tulis 'publik gagal 2x tetapi penyalakan sedang berjalan, dilewati'
        exit 0
    }

    Tulis ('publik=' + $publik + ' gagal ' + $gagal + ' kali berturut-turut -> picu penyalakan')
    [void](PicuPenyalakan)
    Set-Content -Path $berkasGagal -Value '0' -Encoding utf8
    exit 0
} finally {
    Remove-Item $kunci -Force -ErrorAction SilentlyContinue
}
