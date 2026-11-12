# Diagnostik ruang disk + verifikasi salinan duplikat. HANYA MEMBACA.
$ErrorActionPreference = 'Continue'

'=== DRIVE ==='
Get-PSDrive -PSProvider FileSystem | Where-Object { $_.Name -in @('C','D') } | ForEach-Object {
    $u = if ($_.Used) { [math]::Round($_.Used/1GB,1) } else { 0 }
    $f = if ($_.Free) { [math]::Round($_.Free/1GB,1) } else { 0 }
    '{0}: used={1} GB free={2} GB' -f $_.Name, $u, $f
}

function Ukur([string]$p) {
    if (-not (Test-Path -LiteralPath $p)) { '{0,-52} TIDAK ADA' -f $p; return }
    $s = Get-ChildItem -LiteralPath $p -Recurse -File -Force -ErrorAction SilentlyContinue | Measure-Object Length -Sum
    '{0,-52} {1,8:N0} MB {2,7:N0} file' -f $p, ($s.Sum/1MB), $s.Count
}

''
'=== UKURAN ==='
Ukur 'D:\Projects\iksanknalpot2'
Ukur 'D:\Projects\iksanknalpot2\vendor'
Ukur 'D:\Projects\iksanknalpot2\node_modules'
Ukur 'D:\Projects\iksanknalpot2\storage'
Ukur 'D:\Projects\iksanknalpot2\storage\app\public'
Ukur 'D:\Projects\iksanknalpot2\storage\logs'
Ukur 'D:\Projects\iksanknalpot2\storage\framework'
Ukur 'D:\Projects\iksanknalpot2\tools\out'
Ukur 'D:\Projects\iksanknalpot2\public\build'
Ukur 'D:\Projects\iksanknalpot2\.git'
Ukur 'D:\projects\bitesexhaust'
Ukur 'D:\projects\bitesexhaust\storage\app\public'
Ukur 'D:\backup-iksanknalpot2-20261104'

''
'=== APAKAH DUPLIKAT ITU REPO GIT? ==='
$dup = 'D:\projects\bitesexhaust'
if (Test-Path "$dup\.git") {
    'ADA .git'
    Push-Location $dup
    'commit: ' + (git rev-parse --short HEAD 2>&1)
    'branch: ' + (git branch --show-current 2>&1)
    $st = git status --porcelain 2>&1
    if ($st) { 'ADA PERUBAHAN BELUM DI-COMMIT:'; $st | Select-Object -First 15 } else { 'working tree bersih' }
    'jumlah commit: ' + (git rev-list --count HEAD 2>&1)
    Pop-Location
} else { 'TIDAK ada .git' }

''
'=== BANDINGKAN HEAD GIT KEDUA FOLDER ==='
Push-Location 'D:\Projects\iksanknalpot2'
'utama: ' + (git rev-parse --short HEAD 2>&1) + ' | ' + (git log -1 --format='%ci %s' 2>&1)
Pop-Location
if (Test-Path "$dup\.git") {
    Push-Location $dup
    'duplikat: ' + (git rev-parse --short HEAD 2>&1) + ' | ' + (git log -1 --format='%ci %s' 2>&1)
    Pop-Location
}

''
'=== SIApa YANG MERUJUK D:\projects\bitesexhaust ? ==='
$pola = 'projects[\\/]bitesexhaust'
$target = @(
    'C:\Users\IT-LAPTOP\AppData\Local\nginx\nginx-1.28.0\conf',
    'D:\Projects\iksanknalpot2\.env.production',
    'D:\Projects\iksanknalpot2\.env',
    'D:\Projects\iksanknalpot2\tools',
    'D:\Projects\iksanknalpot2\docs'
)
foreach ($t in $target) {
    if (Test-Path -LiteralPath $t) {
        $hits = Get-ChildItem -LiteralPath $t -Recurse -File -Include *.conf,*.ps1,*.md,*.env*,*.php,*.cmd,*.json -ErrorAction SilentlyContinue |
                Select-String -Pattern $pola -ErrorAction SilentlyContinue
        if ($hits) { $hits | ForEach-Object { $_.Path + ':' + $_.LineNumber + ': ' + $_.Line.Trim() } }
    }
}
'(selesai)'

''
'=== LOG YANG BISA DIBUANG ==='
foreach ($f in @(
    'D:\Projects\iksanknalpot2\storage\logs\laravel-2026-11-04.log',
    'D:\Projects\iksanknalpot2\storage\logs\laravel-2026-11-05.log',
    'D:\Projects\iksanknalpot2\storage\logs\laravel-2026-11-06.log',
    'D:\Projects\iksanknalpot2\storage\logs\laravel-lama-arsip.log.gz',
    'D:\Projects\iksanknalpot2\storage\logs\laravel.log',
    'C:\Users\IT-LAPTOP\AppData\Local\nginx\nginx-1.28.0\logs\access.log',
    'C:\Users\IT-LAPTOP\AppData\Local\nginx\nginx-1.28.0\logs\error.log',
    'C:\Users\IT-LAPTOP\AppData\Local\nginx\nginx-1.28.0\logs\iksan2-access.log',
    'C:\Users\IT-LAPTOP\AppData\Local\nginx\nginx-1.28.0\logs\iksan2-error.log',
    'D:\Projects\iksanknalpot2\tools\out\tunnel-bites.log',
    'D:\Projects\iksanknalpot2\tools\out\tunnel-bites.out.log',
    'D:\Projects\iksanknalpot2\tools\out\tunnel.log',
    'D:\Projects\iksanknalpot2\tools\out\tunnel.out.log',
    'D:\Projects\iksanknalpot2\tools\out\jaga.log'
)) {
    if (Test-Path -LiteralPath $f) {
        $i = Get-Item -LiteralPath $f
        '{0,-70} {1,8:N1} MB  {2}' -f $i.Name, ($i.Length/1MB), $i.LastWriteTime.ToString('yyyy-MM-dd HH:mm')
    }
}

''
'=== BERKAS BESAR >= 50 MB (D:) ==='
Get-ChildItem -Path 'D:\' -Recurse -File -Force -ErrorAction SilentlyContinue |
    Where-Object { $_.Length -ge 50MB } |
    Sort-Object Length -Descending | Select-Object -First 20 |
    ForEach-Object { '{0,8:N0} MB  {1}' -f ($_.Length/1MB), $_.FullName }
