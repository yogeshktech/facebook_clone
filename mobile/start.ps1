# Newbook mobile — one-command start (fixes Node + deps + port issues)
$ErrorActionPreference = "Stop"

Write-Host "=== Newbook Mobile Start ===" -ForegroundColor Cyan

# 1. Node version check
$nodeVer = (node -v) -replace 'v', ''
$nodeParts = $nodeVer.Split('.')
$major = [int]$nodeParts[0]
$minor = [int]$nodeParts[1]
$patch = [int]$nodeParts[2]

$ok = ($major -gt 20) -or ($major -eq 20 -and $minor -gt 19) -or ($major -eq 20 -and $minor -eq 19 -and $patch -ge 4) -or ($major -eq 22) -or ($major -ge 24)

if (-not $ok) {
    Write-Host ""
    Write-Host "ERROR: Node.js v$nodeVer is too old. Need >= 20.19.4" -ForegroundColor Red
    Write-Host "Fix: Close ALL terminals, then run:" -ForegroundColor Yellow
    Write-Host "  winget install OpenJS.NodeJS.LTS" -ForegroundColor Yellow
    Write-Host "Or download: https://nodejs.org/en/download" -ForegroundColor Yellow
    exit 1
}

Write-Host "Node.js: v$nodeVer OK" -ForegroundColor Green

Set-Location $PSScriptRoot

# 2. Install deps if missing or wrong expo version
$needInstall = $false
if (-not (Test-Path "node_modules\expo\package.json")) {
    $needInstall = $true
} else {
    $expoPkg = Get-Content "node_modules\expo\package.json" | ConvertFrom-Json
    if ($expoPkg.version -notlike "52.*") {
        Write-Host "Wrong Expo version ($($expoPkg.version)). Reinstalling..." -ForegroundColor Yellow
        Remove-Item -Recurse -Force node_modules -ErrorAction SilentlyContinue
        Remove-Item package-lock.json -ErrorAction SilentlyContinue
        $needInstall = $true
    }
}

if ($needInstall) {
    Write-Host "Installing dependencies..." -ForegroundColor Cyan
    npm install
    npx expo install expo-asset expo-font expo-constants
}

# 3. Free Metro ports if stuck
foreach ($port in @(8081, 8082, 8083, 19000, 19001)) {
    $conns = Get-NetTCPConnection -LocalPort $port -ErrorAction SilentlyContinue
    foreach ($conn in $conns) {
        Write-Host "Port $port in use — stopping PID $($conn.OwningProcess)..." -ForegroundColor Yellow
        Stop-Process -Id $conn.OwningProcess -Force -ErrorAction SilentlyContinue
    }
}
Start-Sleep -Seconds 2

Write-Host ""
Write-Host "Starting Expo (SDK 52)..." -ForegroundColor Green
Write-Host "Phone: Install 'Expo Go' from Play Store, scan QR code" -ForegroundColor Cyan
Write-Host "If QR fails: press 's' then try tunnel, or run: npm run start:tunnel" -ForegroundColor Cyan
Write-Host ""

npx expo start --clear
