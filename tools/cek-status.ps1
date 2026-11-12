# Diagnostik cepat keadaan tumpukan + penjaga. Tidak mengubah apa pun.
$ErrorActionPreference = 'Continue'

'=== WAKTU ==='
Get-Date -Format 'yyyy-MM-dd HH:mm:ss zzz'

'=== TASK PENJAGA ==='
Get-ScheduledTask -TaskName 'BitesExhaust-Watchdog' -ErrorAction SilentlyContinue |
    Select-Object TaskName, State | Format-Table -AutoSize
Get-ScheduledTaskInfo -TaskName 'BitesExhaust-Watchdog' -ErrorAction SilentlyContinue |
    Select-Object LastRunTime, LastTaskResult, NextRunTime | Format-List
'ExecutionTimeLimit: ' + (Get-ScheduledTask -TaskName 'BitesExhaust-Watchdog' -ErrorAction SilentlyContinue).Settings.ExecutionTimeLimit

'=== PROSES POWERSHELL (penjaga / penyalakan) ==='
Get-CimInstance Win32_Process -Filter "Name='powershell.exe'" -ErrorAction SilentlyContinue |
    Where-Object { $_.CommandLine -match 'jaga-produksi|nyalakan-produksi' -and $_.CommandLine -notmatch 'CommandLine -match' } |
    Select-Object ProcessId, CreationDate, CommandLine | Format-List

'=== PROSES TUMPUKAN ==='
Get-Process cloudflared, nginx, php-cgi -ErrorAction SilentlyContinue |
    Group-Object ProcessName |
    Select-Object Name, Count |
    Format-Table -AutoSize

'=== PORT ==='
foreach ($p in 8200, 9300, 9301, 9302, 9303) {
    $c = Test-NetConnection -ComputerName 127.0.0.1 -Port $p -InformationLevel Quiet -WarningAction SilentlyContinue
    "$p = $c"
}

'=== HTTP LOKAL ==='
try {
    $r = Invoke-WebRequest 'http://127.0.0.1:8200/' -UseBasicParsing -TimeoutSec 8
    'lokal = ' + [int]$r.StatusCode + ' len=' + $r.RawContentLength
} catch { 'lokal = ERR ' + $_.Exception.Message }

'=== HTTP PUBLIK ==='
try {
    $u = Invoke-WebRequest ('https://bitesexhaust.com/?cek=' + (Get-Random)) -UseBasicParsing -TimeoutSec 15
    'publik = ' + [int]$u.StatusCode + ' len=' + $u.RawContentLength
} catch { 'publik = ERR ' + $_.Exception.Message }

'=== JAGA.LOG (ekor 20) ==='
$log = 'D:\Projects\iksanknalpot2\tools\out\jaga.log'
if (Test-Path $log) { Get-Content $log -Tail 20 } else { '(belum ada)' }

'=== PENGHITUNG GAGAL ==='
$g = 'D:\Projects\iksanknalpot2\tools\out\jaga-gagal.txt'
if (Test-Path $g) { Get-Content $g } else { '(tidak ada)' }
