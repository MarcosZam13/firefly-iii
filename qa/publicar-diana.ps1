param(
    [switch]$Subir,
    [string]$Python = $env:QATLAS_PYTHON,
    [string]$Junit = '',
    [string[]]$Rendimiento = @(),
    [switch]$SoloRendimiento
)

$ErrorActionPreference = 'Stop'
if (-not $Python) {
    $qaPaquete = Get-AppxPackage -Name 'OpenAI.CodexPrimaryRuntime*' | Sort-Object Version -Descending | Select-Object -First 1
    if ($qaPaquete) {
        $Python = Join-Path $qaPaquete.InstallLocation 'dependencies/python/python.exe'
    }
}
if (-not $Python -or -not (Test-Path -LiteralPath $Python)) {
    $qaInterprete = Get-Command python3,python -ErrorAction SilentlyContinue | Where-Object { $_.Source -notlike '*WindowsApps*' } | Select-Object -First 1
    if ($qaInterprete) { $Python = $qaInterprete.Source }
}
if (-not $Python -or -not (Test-Path -LiteralPath $Python)) {
    throw 'No se encontro Python. Usa -Python con la ruta de Python 3.10 o superior.'
}

$qaArgumentos = @((Join-Path $PSScriptRoot 'qatlas/subir_diana.py'))
if ($Subir) { $qaArgumentos += '--subir' } else { $qaArgumentos += '--comprobar' }
if ($Junit) { $qaArgumentos += @('--junit', $Junit) }
if ($Rendimiento.Count -gt 0) { $qaArgumentos += @('--rendimiento') + $Rendimiento }
if ($SoloRendimiento) { $qaArgumentos += '--solo-rendimiento' }
& $Python @qaArgumentos
if ($LASTEXITCODE -ne 0) { throw 'No se completo el registro de QAtlas. Revisa el mensaje anterior.' }
