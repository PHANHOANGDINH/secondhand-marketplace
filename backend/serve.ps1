param([string]$PhpExecutable = 'php')
$ErrorActionPreference = 'Stop'
$previousScanDirectory = $env:PHP_INI_SCAN_DIR
try {
    # Keep PHP's normal scanned config, then apply this project's upload limits.
    $env:PHP_INI_SCAN_DIR = $previousScanDirectory + [IO.Path]::PathSeparator + (Join-Path $PSScriptRoot 'dev')
    Push-Location $PSScriptRoot
    try {
        & $PhpExecutable artisan serve --host=127.0.0.1 --port=8000 --tries=1 --no-reload
    } finally {
        Pop-Location
    }
} finally {
    $env:PHP_INI_SCAN_DIR = $previousScanDirectory
}
