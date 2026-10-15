<#
.SYNOPSIS
  Opens a temporary public HTTPS URL to the local backend so ZaloPay sandbox can deliver its callback.

.DESCRIPTION
  Runs `cloudflared tunnel --url http://127.0.0.1:8000` (a free "quick tunnel", no account needed),
  prints the public URL and the exact line to put in backend/.env. It never edits .env itself and never
  prints any ZaloPay key. Stop it with Ctrl+C; the URL changes on every run.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts\zalopay-tunnel.ps1
  powershell -ExecutionPolicy Bypass -File scripts\zalopay-tunnel.ps1 -Port 8001
#>
param(
    [int] $Port = 8000,
    [string] $Cloudflared = ''
)
$ErrorActionPreference = 'Stop'

if (-not $Cloudflared) {
    $found = Get-Command cloudflared -ErrorAction SilentlyContinue
    if ($found) { $Cloudflared = $found.Source }
    elseif (Test-Path 'C:\Program Files (x86)\cloudflared\cloudflared.exe') { $Cloudflared = 'C:\Program Files (x86)\cloudflared\cloudflared.exe' }
    else {
        Write-Host 'Không tìm thấy cloudflared. Cài bằng: winget install --id Cloudflare.cloudflared' -ForegroundColor Red
        exit 1
    }
}

try {
    Invoke-WebRequest -Uri "http://127.0.0.1:$Port/up" -UseBasicParsing -TimeoutSec 3 | Out-Null
} catch {
    Write-Host "Cảnh báo: backend chưa trả lời ở http://127.0.0.1:$Port. Hãy chạy backend trước (start-dev.ps1)." -ForegroundColor Yellow
}

Write-Host "Đang mở tunnel tới http://127.0.0.1:$Port ... (Ctrl+C để dừng)" -ForegroundColor Cyan
$printed = $false
# cloudflared writes its log (including the public URL) to stderr; merge it so the URL can be picked up.
& $Cloudflared tunnel --no-autoupdate --url "http://127.0.0.1:$Port" 2>&1 | ForEach-Object {
    $line = "$_"
    if (-not $printed -and $line -match '(https://[a-z0-9-]+\.trycloudflare\.com)') {
        $url = $Matches[1]
        $printed = $true
        Write-Host ''
        Write-Host "URL công khai: $url" -ForegroundColor Green
        Write-Host ''
        Write-Host '1. Mở backend\.env và đặt (chỉ dòng này, không đổi key):'
        Write-Host "   ZALOPAY_CALLBACK_URL=$url/api/v1/payments/zalopay/callback" -ForegroundColor Yellow
        Write-Host '2. Xoá cache cấu hình rồi khởi động lại backend:'
        Write-Host '   C:\Users\axyz5\.config\herd\bin\php84\php.exe artisan config:clear' -ForegroundColor Yellow
        Write-Host "3. Kiểm tra nhanh: $url/up phải trả về 200."
        Write-Host '4. Giữ cửa sổ này mở trong suốt buổi demo. Lần chạy sau URL sẽ khác: đặt lại bước 1.'
        Write-Host ''
    }
    elseif ($line -match 'ERR|error') {
        Write-Host $line -ForegroundColor DarkYellow
    }
}
