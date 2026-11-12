<#
.SYNOPSIS
    Mendaftarkan tools/jaga-produksi.ps1 sebagai Windows Scheduled Task.

.DESCRIPTION
    Kenapa pakai XML, bukan New-ScheduledTaskTrigger:
    New-ScheduledTaskTrigger -RepetitionDuration ([TimeSpan]::MaxValue)
    menghasilkan durasi P99999999DT23H59M59S yang DITOLAK Task Scheduler:
      "The task XML contains a value which is incorrectly formatted or
       out of range. (10,42):Duration:P99999999DT23H59M59S"
    Di XML, elemen <Duration> boleh TIDAK ADA sama sekali — artinya
    pengulangan berjalan tanpa batas. Itu yang dipakai di sini.

    Tiga pemicu:
      1. BootTrigger    - jalan saat komputer menyala, tanpa perlu login
      2. CalendarTrigger + Repetition PT5M - pemeriksaan tiap 5 menit
      3. LogonTrigger   - sapu bersih sekali setelah pengguna masuk

    Principal S4U dipakai supaya tugas tetap berjalan walau tidak ada sesi
    interaktif. Bila S4U ditolak (butuh hak "Log on as a batch job"),
    skrip otomatis jatuh ke Interactive — tetap jalan selama pengguna login.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools/daftarkan-penjaga.ps1
#>

$ErrorActionPreference = 'Stop'

$namaTugas = 'BitesExhaust-Watchdog'
$skrip     = 'D:\Projects\iksanknalpot2\tools\jaga-produksi.ps1'
$proyek    = 'D:\Projects\iksanknalpot2'
$pengguna  = "$env:COMPUTERNAME\$env:USERNAME"
$powershel = "$env:SystemRoot\System32\WindowsPowerShell\v1.0\powershell.exe"

if (-not (Test-Path $skrip)) { throw "Tidak ditemukan: $skrip" }

$lama = Get-ScheduledTask -TaskName $namaTugas -ErrorAction SilentlyContinue
if ($lama) { Unregister-ScheduledTask -TaskName $namaTugas -Confirm:$false }

function BuatXml([string]$logonType) {
    @"
<?xml version="1.0" encoding="UTF-16"?>
<Task version="1.2" xmlns="http://schemas.microsoft.com/windows/2004/02/mit/task">
  <RegistrationInfo>
    <Description>Penjaga produksi bitesexhaust.com: cek tiap 5 menit, saat boot, dan saat logon. Menyalakan ulang nginx + php-cgi + tunnel bila mati.</Description>
    <URI>\$namaTugas</URI>
  </RegistrationInfo>
  <Triggers>
    <BootTrigger>
      <Enabled>true</Enabled>
      <Delay>PT1M</Delay>
    </BootTrigger>
    <LogonTrigger>
      <Enabled>true</Enabled>
      <UserId>$pengguna</UserId>
      <Delay>PT30S</Delay>
    </LogonTrigger>
    <CalendarTrigger>
      <StartBoundary>2026-11-09T00:00:00</StartBoundary>
      <Enabled>true</Enabled>
      <ScheduleByDay>
        <DaysInterval>1</DaysInterval>
      </ScheduleByDay>
      <Repetition>
        <Interval>PT5M</Interval>
        <StopAtDurationEnd>false</StopAtDurationEnd>
      </Repetition>
    </CalendarTrigger>
  </Triggers>
  <Principals>
    <Principal id="Author">
      <UserId>$pengguna</UserId>
      <LogonType>$logonType</LogonType>
      <RunLevel>HighestAvailable</RunLevel>
    </Principal>
  </Principals>
  <Settings>
    <MultipleInstancesPolicy>IgnoreNew</MultipleInstancesPolicy>
    <DisallowStartIfOnBatteries>false</DisallowStartIfOnBatteries>
    <StopIfGoingOnBatteries>false</StopIfGoingOnBatteries>
    <AllowHardTerminate>true</AllowHardTerminate>
    <StartWhenAvailable>true</StartWhenAvailable>
    <RunOnlyIfNetworkAvailable>false</RunOnlyIfNetworkAvailable>
    <IdleSettings>
      <StopOnIdleEnd>false</StopOnIdleEnd>
      <RestartOnIdle>false</RestartOnIdle>
    </IdleSettings>
    <AllowStartOnDemand>true</AllowStartOnDemand>
    <Enabled>true</Enabled>
    <Hidden>false</Hidden>
    <RunOnlyIfIdle>false</RunOnlyIfIdle>
    <WakeToRun>false</WakeToRun>
    <ExecutionTimeLimit>PT15M</ExecutionTimeLimit>
    <Priority>7</Priority>
  </Settings>
  <Actions Context="Author">
    <Exec>
      <Command>$powershel</Command>
      <Arguments>-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File "$skrip"</Arguments>
      <WorkingDirectory>$proyek</WorkingDirectory>
    </Exec>
  </Actions>
</Task>
"@
}

$terdaftar = $false
foreach ($mode in @('S4U', 'Interactive')) {
    try {
        Register-ScheduledTask -TaskName $namaTugas -Xml (BuatXml $mode) -Force | Out-Null
        Write-Output "TERDAFTAR (LogonType=$mode)"
        $terdaftar = $true
        break
    } catch {
        Write-Output "GAGAL dengan LogonType=$mode : $($_.Exception.Message)"
    }
}

if (-not $terdaftar) { throw 'Pendaftaran tugas gagal untuk semua LogonType.' }

Get-ScheduledTask -TaskName $namaTugas |
    Select-Object TaskName, State |
    Format-Table -AutoSize

Get-ScheduledTaskInfo -TaskName $namaTugas |
    Select-Object LastRunTime, LastTaskResult, NextRunTime |
    Format-List
