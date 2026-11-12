@echo off
REM Dipanggil oleh tools/jaga-produksi.ps1 lewat WMI (Win32_Process.Create),
REM sehingga nginx/php-cgi/cloudflared TIDAK menjadi anak dari proses tugas
REM penjaga — kalau menjadi anak, Task Scheduler menahan tugas berstatus
REM "Running" sampai ExecutionTimeLimit lalu menerminasinya (kode 267014).
REM
REM Lingkungan dikunci ke profil IT-LAPTOP: proses yang lahir lewat WMI
REM mewarisi environment WmiPrvSE (SYSTEM), bukan environment pengguna.

set "USERPROFILE=C:\Users\IT-LAPTOP"
set "LOCALAPPDATA=C:\Users\IT-LAPTOP\AppData\Local"
set "APPDATA=C:\Users\IT-LAPTOP\AppData\Roaming"
set "HOMEDRIVE=C:"
set "HOMEPATH=\Users\IT-LAPTOP"

powershell.exe -NoProfile -ExecutionPolicy Bypass -File "D:\Projects\iksanknalpot2\tools\nyalakan-produksi.ps1" >> "D:\Projects\iksanknalpot2\tools\out\nyalakan-jaga.log" 2>&1
