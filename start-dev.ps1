$root = "D:\CN\Web\secondhand-marketplace"
# PHP 8.4 CLI: set the PHP_BIN environment variable to override; the default is the Herd path.
$php  = if ($env:PHP_BIN) { $env:PHP_BIN } else { "C:\Users\axyz5\.config\herd\bin\php84\php.exe" }
if (-not (Test-Path $php)) {
    Write-Host "Khong tim thay PHP tai $php. Dat bien moi truong PHP_BIN tro toi php.exe 8.4." -ForegroundColor Red
    Read-Host "Nhan Enter de thoat"; exit
}

# Kiểm tra MySQL đã bật chưa
if (-not (Test-NetConnection 127.0.0.1 -Port 3306 -InformationLevel Quiet)) {
    Write-Host "MySQL chua chay. Hay bat MySQL trong XAMPP truoc." -ForegroundColor Red
    Read-Host "Nhan Enter de thoat"; exit
}

function Open-Window($title, $dir, $cmd) {
    Start-Process powershell -ArgumentList "-NoExit", "-ExecutionPolicy", "Bypass", "-Command",
        "`$Host.UI.RawUI.WindowTitle='$title'; cd '$dir'; $cmd"
}

Open-Window "1-Backend"   "$root\backend"  ".\serve.ps1 -PhpExecutable '$php'"
Open-Window "2-Reverb"    "$root\backend"  "& '$php' artisan reverb:start"
Open-Window "3-Scheduler" "$root\backend"  "& '$php' artisan schedule:work"
Open-Window "4-Frontend"  "$root\frontend" "npm run dev"

Start-Sleep -Seconds 8
Start-Process "http://localhost:5173"