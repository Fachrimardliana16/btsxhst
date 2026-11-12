<#
.SYNOPSIS
    Uji pemulihan otomatis: matikan nginx, biarkan penjaga menyalakannya kembali.

.DESCRIPTION
    Uji sungguhan, bukan simulasi:
      1. catat keadaan awal (harus 200)
      2. matikan HANYA nginx milik tumpukan bites (bites.conf)
      3. pastikan 8200 benar-benar mati
      4. jalankan Scheduled Task penjaga
      5. tunggu sampai 8200 kembali 200, ukur berapa detik
      6. tunggu tugas selesai, catat LastTaskResult

    ASCII murni (lihat catatan di jaga-produksi.ps1).
#>

$ErrorActionPreference = 'Continue'

$logJaga = 'D:\Projects\iksanknalpot2\tools\out\jaga.log'

function StatusLokal {
    try {
        $r = Invoke-WebRequest 'http://127.0.0.1:8200/' -UseBasicParsing -TimeoutSec 5
        return [int]$r.StatusCode
    } catch {
        return 0
    }
}

Write-Output ('=== 1. KEADAAN AWAL (' + (Get-Date -Format 'HH:mm:ss') + ') ===')
$awal = StatusLokal
Write-Output ('lokal 8200 = ' + $awal)
if ($awal -ne 200) {
    Write-Output 'Batal: tumpukan belum sehat, uji pemulihan tidak bermakna.'
    exit 1
}

Write-Output ''
Write-Output ('=== 2. MATIKAN NGINX bites (' + (Get-Date -Format 'HH:mm:ss') + ') ===')
$nginxBites = @(Get-CimInstance Win32_Process |
    Where-Object { $_.Name -eq 'nginx.exe' -and $_.CommandLine -match 'bites\.conf' })
Write-Output ('nginx bites ditemukan: ' + $nginxBites.Count + ' proses')
foreach ($p in $nginxBites) {
    Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue
    Write-Output ('  dimatikan PID ' + $p.ProcessId)
}
Start-Sleep -Seconds 2

$mati = StatusLokal
Write-Output ('lokal 8200 setelah dimatikan = ' + $mati)
if ($mati -ne 0) { Write-Output 'Peringatan: 8200 masih menjawab.' }

Write-Output ''
Write-Output ('=== 3. JALANKAN TASK PENJAGA (' + (Get-Date -Format 'HH:mm:ss') + ') ===')
$t0 = Get-Date
Start-ScheduledTask -TaskName 'BitesExhaust-Watchdog'

$pulih = $false
$detik = 0
for ($i = 1; $i -le 120; $i++) {
    Start-Sleep -Seconds 2
    if ((StatusLokal) -eq 200) {
        $pulih = $true
        $detik = [int]((Get-Date) - $t0).TotalSeconds
        break
    }
}
if (-not $pulih) { $detik = [int]((Get-Date) - $t0).TotalSeconds }

# tunggu tugas benar-benar selesai (bukan masih Running)
$tunggu = 0
while (((Get-ScheduledTask -TaskName 'BitesExhaust-Watchdog').State) -eq 'Running' -and $tunggu -lt 60) {
    Start-Sleep -Seconds 2
    $tunggu += 2
}

Write-Output ''
Write-Output ('=== 4. HASIL (' + (Get-Date -Format 'HH:mm:ss') + ') ===')
if ($pulih) {
    Write-Output ('PULIH dalam ' + $detik + ' detik, tanpa sentuhan tangan.')
} else {
    Write-Output ('GAGAL PULIH setelah ' + $detik + ' detik.')
}

Write-Output '--- info tugas ---'
Get-ScheduledTaskInfo -TaskName 'BitesExhaust-Watchdog' |
    Select-Object LastRunTime, LastTaskResult, NextRunTime |
    Format-List
Write-Output ('State tugas: ' + (Get-ScheduledTask -TaskName 'BitesExhaust-Watchdog').State)

Write-Output '--- proses sekarang ---'
Get-Process nginx, php-cgi, cloudflared -ErrorAction SilentlyContinue |
    Group-Object ProcessName |
    Select-Object Name, Count |
    Format-Table -AutoSize

Write-Output '--- jaga.log (ekor 10) ---'
if (Test-Path $logJaga) {
    Get-Content $logJaga -Tail 10
} else {
    Write-Output '(belum ada)'
}

Write-Output '--- publik ---'
try {
    $u = Invoke-WebRequest ('https://bitesexhaust.com/?uji=' + (Get-Random)) -UseBasicParsing -TimeoutSec 20
    Write-Output ('publik = ' + [int]$u.StatusCode)
    if ($u.Content -match '<title>(.*?)</title>') { Write-Output ('judul = ' + $Matches[1]) }
} catch {
    Write-Output ('publik = ERR ' + $_.Exception.Message)
}

if ($pulih) { exit 0 } else { exit 1 }
