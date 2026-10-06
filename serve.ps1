# Laravel Dev Server - PHP 8.3 (Scoop)
# Jalankan: .\serve.ps1

$phpExe    = "$env:USERPROFILE\scoop\apps\php83\current\php.exe"
$phpIni    = "$env:USERPROFILE\scoop\apps\php83\current\php.ini"
$publicDir = "C:\Users\R\Documents\project baru\laravel-project\public"
$router    = "C:\Users\R\Documents\project baru\laravel-project\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"

Write-Host ""
Write-Host "  Laravel Dev Server - PHP 8.3" -ForegroundColor Green
Write-Host "  URL : http://127.0.0.1:8000" -ForegroundColor Cyan
Write-Host "  Stop: Ctrl+C" -ForegroundColor Yellow
Write-Host ""

# Jalankan PHP dari dalam folder public/ (diperlukan oleh router Laravel)
Push-Location $publicDir
try {
    & $phpExe -c $phpIni -S 127.0.0.1:8000 $router
} finally {
    Pop-Location
}
