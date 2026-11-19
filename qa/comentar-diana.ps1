param(
    [Parameter(Mandatory=$true)][string]$Carpeta,
    [switch]$Subir,
    [string[]]$Adjunto = @(),
    [string]$Python = $env:QATLAS_PYTHON
)
$ErrorActionPreference = 'Stop'
if (-not $Python) {
    $qaPaquete = Get-AppxPackage -Name 'OpenAI.CodexPrimaryRuntime*' | Sort-Object Version -Descending | Select-Object -First 1
    if ($qaPaquete) { $Python = Join-Path $qaPaquete.InstallLocation 'dependencies/python/python.exe' }
}
if (-not $Python -or -not (Test-Path -LiteralPath $Python)) { throw 'Usa -Python con la ruta de Python 3.10 o superior.' }
$qaArgumentos = @((Join-Path $PSScriptRoot 'qatlas/anotar_diana.py'), '--carpeta', $Carpeta)
if ($Subir) { $qaArgumentos += '--subir' }
foreach ($qaArchivo in $Adjunto) { $qaArgumentos += @('--adjunto', $qaArchivo) }
& $Python @qaArgumentos
if ($LASTEXITCODE -ne 0) { throw 'No se completo el registro de comentarios y adjuntos. Revisa el mensaje anterior.' }
