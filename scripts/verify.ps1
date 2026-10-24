[CmdletBinding()]
param(
    [switch]$Local
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Set-Location -LiteralPath $root

function Assert-Command {
    param([string]$Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "No se encontró '$Name' en PATH."
    }
}

if ($Local) {
    Assert-Command 'php'
    Assert-Command 'python'

    Write-Host '[scitrace] Laravel: tests y estilo'
    Push-Location -LiteralPath (Join-Path $root 'src')
    try {
        php artisan test --compact
        if ($LASTEXITCODE -ne 0) { throw 'Los tests de Laravel fallaron.' }
        php vendor/bin/pint --test
        if ($LASTEXITCODE -ne 0) { throw 'Pint encontró problemas de estilo.' }
    } finally {
        Pop-Location
    }

    Write-Host '[scitrace] Motor IA: ruff y pytest'
    Push-Location -LiteralPath (Join-Path $root 'ai-service')
    try {
        # Tesseract es opcional: si está instalado, se añade al PATH para que
        # la prueba real de OCR no se omita por accidente.
        if (-not (Get-Command 'tesseract' -ErrorAction SilentlyContinue)) {
            $tesseract = 'C:\Program Files\Tesseract-OCR\tesseract.exe'
            if (Test-Path -LiteralPath $tesseract) {
                $env:Path = (Split-Path -Parent $tesseract) + ';' + $env:Path
            }
        }

        if (-not $env:TESSDATA_PREFIX) {
            $localTessdata = Join-Path $env:TEMP 'scitrace-tessdata'
            if (Test-Path -LiteralPath $localTessdata) {
                $env:TESSDATA_PREFIX = $localTessdata
            }
        }

        python -m ruff check .
        if ($LASTEXITCODE -ne 0) { throw 'ruff encontró problemas.' }
        python -m pytest -q
        if ($LASTEXITCODE -ne 0) { throw 'Los tests del motor IA fallaron.' }
    } finally {
        Pop-Location
    }
} else {
    Assert-Command 'docker'

    Write-Host '[scitrace] Laravel: tests y estilo (contenedor app)'
    docker compose exec -T app php artisan test --compact
    if ($LASTEXITCODE -ne 0) { throw 'Los tests de Laravel fallaron.' }
    docker compose exec -T app php vendor/bin/pint --test
    if ($LASTEXITCODE -ne 0) { throw 'Pint encontró problemas de estilo.' }

    Write-Host '[scitrace] Motor IA: pytest y ruff (contenedor ai-tests)'
    docker compose --profile tools run --rm --build ai-tests
    if ($LASTEXITCODE -ne 0) { throw 'Los tests del motor IA fallaron.' }
}

Write-Host '[scitrace] Verificación completada.'
