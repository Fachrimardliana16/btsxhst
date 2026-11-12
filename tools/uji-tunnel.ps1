<#
.SYNOPSIS
    Uji pemulihan pada skenario yang sebenarnya: TUNNEL mati, bukan nginx.

.DESCRIPTION
    Situs ini mati pada 8-9 Nov karena proses cloudflared tunnel "bites"
    berhenti - bukan karena nginx. Uji ini meniru persis kejadian itu:

      1. catat keadaan awal (publik harus 200)
      2. matikan HANYA cloudflared tunnel "bites" (tunnel 9router tidak disentuh)
      3. pastikan situs publik benar-benar mati
      4. jalankan Scheduled Task penjaga
      5. tunggu sampai publik 200 lagi, ukur berapa detik
      6. pastikan tugas selesai dengan hasil 0 (tidak menggantung)

    ASCII murni.
#>

$ErrorActionPreference = 'Continue'

$logJaga = 'D:\Projects\iksanknalpot2\tools\out\jaga.log'

function StatusPublik {
    try {
        $u = Invoke-WebRequest ('https://bitesexhaust.com/?uji=' + (Get-Random)) `
                -UseBasicParsing -TimeoutSec 15
        return [int]$u.StatusCode
    } catch {
        return 0
    }
}

function TunnelBites {
    @(Get-CimInstance Win32_Process -Filter "Name='cloudflared.exe'" -ErrorAction SilentlyContinue |
        Where-Object {
            $_.CommandLine -match 'run\s+bites(\s|"|$)' -or
            $_.CommandLine -match [regex]::Escape('bites.yml')
        })
}

Write-Output ('=== 1. KEADAAN AWAL (' + (Get-Date -Format 'HH:mm:ss') + ') ===')
$awal = StatusPublik
Write-Output ('publik = ' + $awal)
if ($awal -ne 200) {
    Write-Output 'Batal: situs belum sehat, uji pemulihan tidak bermakna.'
    exit 1
}

Write-Output ''
Write-Output ('=== 2. MATIKAN TUNNEL bites (' + (Get-Date -Format 'HH:mm:ss') + ') ===')
$t = TunnelBites
Write-Output ('proses tunnel bites: ' + $t.Count)
foreach ($p in $t) {
    Stop-Process -Id $p.ProcessId -Force -ErrorAction SilentlyContinue
    Write-Output ('  dimatikan PID ' + $p.ProcessId)
}
Start-Sleep -Seconds 5

$mati = StatusPublik
Write-Output ('publik setelah tunnel dimatikan = ' + $mati)
if ($mati -eq 200) {
    Write-Output 'Peringatan: publik masih 200 - mungkin cache Cloudflare. Uji tetap lanjut.'
}

Write-Output ''
Write-Output ('=== 3. JALANKAN TASK PENJAGA (' + (Get-Date -Format 'HH:mm:ss') + ') ===')
$t0 = Get-Date
Start-ScheduledTask -TaskName 'BitesExhaust-Watchdog'

$pulih = $false
$detik = 0
for ($i = 1; $i -le 120; $i++) {
    Start-Sleep -Seconds 2
    if ((StatusPublik) -eq 200) {
        $pulih = $true
        $detik = [int]((Get-Date) - $t0).TotalSeconds
        break
    }
}
if (-not $pulih) { $detik = [int]((Get-Date) - $t0).TotalSeconds }

$tunggu = 0
while (((Get-ScheduledTask -TaskName 'BitesExhaust-Watchdog').State) -eq 'Running' -and $tunggu -lt 90) {
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

Get-ScheduledTaskInfo -TaskName 'BitesExhaust-Watchdog' |
    Select-Object LastRunTime, LastTaskResult, NextRunTime | Format-List
Write-Output ('State tugas: ' + (Get-ScheduledTask -TaskName 'BitesExhaust-Watchdog').State)
Write-Output ('Tunnel bites hidup: ' + ((TunnelBites).Count -gt 0))

Write-Output '--- jaga.log (ekor 8) ---'
if (Test-Path $logJaga) { Get-Content $logJaga -Tail 8 } else { Write-Output '(belum ada)' }

if ($pulih) { exit 0 } else { exit 1 }
