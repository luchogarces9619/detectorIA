[CmdletBinding()]
param(
    [switch]$EnvOnly,
    [switch]$SkipBuild,
    [switch]$SkipAssets
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $root

function New-RandomBytes {
    param([int]$Count)

    $bytes = New-Object byte[] $Count
    $rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    try {
        $rng.GetBytes($bytes)
    } finally {
        $rng.Dispose()
    }

    return $bytes
}

function New-Base64Secret {
    param([int]$Bytes = 32)

    return [Convert]::ToBase64String((New-RandomBytes -Count $Bytes))
}

function New-HexSecret {
    param([int]$Bytes = 24)

    return ([BitConverter]::ToString((New-RandomBytes -Count $Bytes))).Replace('-', '').ToLowerInvariant()
}

$envPath = Join-Path $root '.env'
if (-not (Test-Path -LiteralPath $envPath)) {
    Copy-Item -LiteralPath (Join-Path $root '.env.example') -Destination $envPath
    Write-Host '[scitrace] .env creado a partir de .env.example'
}

$script:envLines = [System.Collections.Generic.List[string]]::new()
Get-Content -LiteralPath $envPath -Encoding UTF8 | ForEach-Object { $script:envLines.Add($_) }

function Get-EnvValue {
    param([string]$Name)

    foreach ($line in $script:envLines) {
        if ($line -match "^\s*$([regex]::Escape($Name))=(.*)$") {
            return $Matches[1].Trim().Trim('"').Trim("'")
        }
    }

    return ''
}

function Set-EnvValue {
    param([string]$Name, [string]$Value)

    $pattern = "^\s*$([regex]::Escape($Name))="
    $newLine = "$Name=$Value"

    for ($i = 0; $i -lt $script:envLines.Count; $i++) {
        if ($script:envLines[$i] -match $pattern) {
            $script:envLines[$i] = $newLine
            return
        }
    }

    $script:envLines.Add($newLine)
}

if (-not (Get-EnvValue 'APP_KEY')) {
    Set-EnvValue 'APP_KEY' ('base64:' + (New-Base64Secret -Bytes 32))
}

if (-not (Get-EnvValue 'DB_PASSWORD')) {
    Set-EnvValue 'DB_PASSWORD' (New-HexSecret -Bytes 18)
}

if (-not (Get-EnvValue 'REDIS_PASSWORD')) {
    Set-EnvValue 'REDIS_PASSWORD' (New-HexSecret -Bytes 18)
}

$aiToken = Get-EnvValue 'AI_INTERNAL_TOKEN'
if ($aiToken.Length -lt 32) {
    Set-EnvValue 'AI_INTERNAL_TOKEN' (New-HexSecret -Bytes 32)
}

$adminPassword = Get-EnvValue 'SCITRACE_ADMIN_PASSWORD'
if (-not $adminPassword) {
    $adminPassword = 'Scitrace-' + (New-HexSecret -Bytes 6) + '!7'
    Set-EnvValue 'SCITRACE_ADMIN_PASSWORD' $adminPassword
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllLines($envPath, $script:envLines, $utf8NoBom)

Write-Host '[scitrace] Secretos locales generados en .env'

if ($EnvOnly) {
    Write-Host '[scitrace] -EnvOnly: no se ha iniciado Docker.'
    exit 0
}

if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw 'Docker no está instalado o no está en PATH. Instala Docker Desktop con WSL2 y vuelve a ejecutar el script.'
}

docker compose version | Out-Null
if ($LASTEXITCODE -ne 0) {
    throw 'El comando "docker compose" no está disponible. Actualiza Docker Desktop.'
}

if (-not $SkipBuild) {
    Write-Host '[scitrace] Construyendo imágenes...'
    docker compose build
}

Write-Host '[scitrace] Iniciando servicios...'
docker compose up -d --wait --wait-timeout 300

if (-not $SkipAssets) {
    Write-Host '[scitrace] Compilando assets con Vite...'
    docker compose --profile tools run --rm assets
}

Write-Host '[scitrace] Ejecutando migraciones...'
docker compose exec -T app php artisan migrate --force

Write-Host '[scitrace] Creando administrador inicial (si está configurado)...'
docker compose exec -T app php artisan db:seed --force

$adminEmail = Get-EnvValue 'SCITRACE_ADMIN_EMAIL'

Write-Host ''
Write-Host 'SciTrace AI está disponible:'
Write-Host '  Aplicación:  http://localhost:8080'
Write-Host '  Mailpit:     http://localhost:8025'
Write-Host "  Administrador: $adminEmail"
if ($adminPassword) {
    Write-Host "  Contraseña:    $adminPassword"
}
Write-Host ''
Write-Host 'Verificación: powershell -ExecutionPolicy Bypass -File scripts/verify.ps1'
