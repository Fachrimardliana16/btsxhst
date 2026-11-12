# Mengukur pemakaian disk. Hanya membaca, tidak menghapus apa pun.
$ErrorActionPreference = 'Continue'

'=== DRIVE ==='
Get-PSDrive -PSProvider FileSystem |
    Where-Object { $_.Name -in @('C', 'D') } |
    ForEach-Object {
        $free = if ($_.Free) { [math]::Round($_.Free / 1GB, 1) } else { 0 }
        $used = if ($_.Used) { [math]::Round($_.Used / 1GB, 1) } else { 0 }
        '{0}:  used={1} GB  free={2} GB' -f $_.Name, $used, $free
    }

function Ukur([string]$path) {
    if (-not (Test-Path -LiteralPath $path)) { return }
    $s = Get-ChildItem -LiteralPath $path -Recurse -File -Force -ErrorAction SilentlyContinue |
         Measure-Object Length -Sum
    '{0,-58} {1,10:N0} MB  {2,8:N0} file' -f $path, ($s.Sum / 1MB), $s.Count
}

''
'=== FOLDER PROYEK ==='
Ukur 'D:\Projects\iksanknalpot2'
Ukur 'D:\Projects\iksanknalpot2\vendor'
Ukur 'D:\Projects\iksanknalpot2\node_modules'
Ukur 'D:\Projects\iksanknalpot2\storage'
Ukur 'D:\Projects\iksanknalpot2\storage\app'
Ukur 'D:\Projects\iksanknalpot2\storage\app\public'
Ukur 'D:\Projects\iksanknalpot2\storage\logs'
Ukur 'D:\Projects\iksanknalpot2\storage\framework'
Ukur 'D:\Projects\iksanknalpot2\tools\out'
Ukur 'D:\Projects\iksanknalpot2\public\build'
Ukur 'D:\Projects\iksanknalpot2\.git'

''
'=== SALINAN / CADANGAN DI LUAR PROYEK ==='
Ukur 'D:\projects\bitesexhaust'
Ukur 'D:\Projects\iksanknalpot'
Ukur 'D:\backup-iksanknalpot2-20261104'
Ukur 'D:\Projects\knalpotkita'

''
'=== CACHE GLOBAL ==='
Ukur "$env:LOCALAPPDATA\Temp"
Ukur "$env:APPDATA\Composer"
Ukur "$env:LOCALAPPDATA\Composer"
Ukur "$env:APPDATA\npm-cache"
Ukur "$env:LOCALAPPDATA\npm-cache"
Ukur "$env:USERPROFILE\.composer\cache"
Ukur "$env:LOCALAPPDATA\nginx\nginx-1.28.0\logs"

''
'=== BERKAS BESAR (>= 50 MB) DI D: DAN C:\Users ==='
Get-ChildItem -Path 'D:\' -Recurse -File -Force -ErrorAction SilentlyContinue |
    Where-Object { $_.Length -ge 50MB } |
    Sort-Object Length -Descending |
    Select-Object -First 25 |
    ForEach-Object { '{0,8:N0} MB  {1}' -f ($_.Length / 1MB), $_.FullName }

Get-ChildItem -Path "$env:USERPROFILE" -Recurse -File -Force -ErrorAction SilentlyContinue |
    Where-Object { $_.Length -ge 50MB } |
    Sort-Object Length -Descending |
    Select-Object -First 25 |
    ForEach-Object { '{0,8:N0} MB  {1}' -f ($_.Length / 1MB), $_.FullName }
