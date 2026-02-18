param(
    [int]$Port = 8000,
    [string]$NgrokDomain = ""
)

$ErrorActionPreference = "Stop"

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

function Write-Info([string]$message) {
    Write-Host "[INFO] $message"
}

function Resolve-NgrokPath() {
    $cmd = Get-Command "ngrok" -ErrorAction SilentlyContinue
    if ($cmd -and $cmd.Source) {
        return $cmd.Source
    }

    $candidates = @(
        "$env:LOCALAPPDATA\ngrok\ngrok.exe",
        "$env:USERPROFILE\ngrok.exe",
        "$env:USERPROFILE\Downloads\ngrok-v3-stable-windows-amd64\ngrok.exe",
        "C:\ProgramData\chocolatey\bin\ngrok.exe"
    )
    foreach ($candidate in $candidates) {
        if (Test-Path $candidate) {
            return $candidate
        }
    }

    return $null
}

function Is-PortListening([int]$localPort) {
    try {
        $conn = Get-NetTCPConnection -LocalPort $localPort -State Listen -ErrorAction Stop
        return $null -ne $conn
    } catch {
        return $false
    }
}

if (-not (Is-PortListening -localPort $Port)) {
    Write-Info "Demarrage du serveur Symfony (php -S 127.0.0.1:$Port -t public)..."
    Start-Process -FilePath "php" -ArgumentList @("-S", "127.0.0.1:$Port", "-t", "public") -WorkingDirectory $projectRoot | Out-Null
    Start-Sleep -Seconds 2
} else {
    Write-Info "Un serveur ecoute deja sur le port $Port."
}

$existingNgrok = Get-Process -Name "ngrok" -ErrorAction SilentlyContinue
if ($existingNgrok) {
    Write-Info "Arret des anciens processus ngrok..."
    $existingNgrok | Stop-Process -Force
    Start-Sleep -Milliseconds 800
}

$ngrokExe = Resolve-NgrokPath
if (-not $ngrokExe) {
    throw "ngrok introuvable. Ajoutez ngrok au PATH ou placez ngrok.exe dans un emplacement standard."
}

$ngrokArgs = @("http", "127.0.0.1:$Port")
if ($NgrokDomain -ne "") {
    $ngrokArgs += "--domain=$NgrokDomain"
}

Write-Info "Demarrage de ngrok..."
Start-Process -FilePath $ngrokExe -ArgumentList $ngrokArgs | Out-Null

$publicUrl = $null
for ($i = 0; $i -lt 25; $i++) {
    Start-Sleep -Milliseconds 800
    try {
        $api = Invoke-RestMethod -Uri "http://127.0.0.1:4040/api/tunnels" -TimeoutSec 2
        $httpsTunnel = $api.tunnels | Where-Object { $_.public_url -like "https://*" } | Select-Object -First 1
        if ($httpsTunnel) {
            $publicUrl = $httpsTunnel.public_url
            break
        }
    } catch {
        # ngrok API pas encore pret
    }
}

if (-not $publicUrl) {
    throw "Impossible de recuperer l'URL publique ngrok."
}

$envLocalPath = Join-Path $projectRoot ".env.local"
$existingLines = @()
if (Test-Path $envLocalPath) {
    $existingLines = Get-Content $envLocalPath
}

$newLines = @($existingLines | Where-Object { $_ -notmatch "^APP_URL=" })
$newLines += "APP_URL=$publicUrl"
Set-Content -Path $envLocalPath -Value $newLines

Write-Info "APP_URL mis a jour dans .env.local => $publicUrl"
Write-Host ""
Write-Host "Liens email utilises:"
Write-Host "  $publicUrl/verify-email/{token}"
Write-Host ""
Write-Host "Conseil: generez un NOUVEL email de verification apres ce script."
