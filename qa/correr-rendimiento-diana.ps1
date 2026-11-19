param(
    [Parameter(Mandatory=$true)][ValidateSet('TC-P-03','TC-P-07')][string]$Caso,
    [switch]$Validar,
    [string]$Jmeter = '',
    [string]$Java = 'java',
    [string]$Contenedor = '',
    [string]$Python = $env:QATLAS_PYTHON
)
$ErrorActionPreference = 'Stop'
$qaRaiz = Split-Path -Parent $PSScriptRoot
if ($Java -eq 'java') {
    $qaHerramientas = Split-Path -Parent (Split-Path -Parent $qaRaiz)
    $qaJre = Get-ChildItem -LiteralPath (Join-Path $qaHerramientas 'tmp/java') -Directory -Filter 'jdk-17*' -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($qaJre) { $Java = Join-Path $qaJre.FullName 'bin/java.exe' }
}
if (-not $Python) {
    $qaPaquete = Get-AppxPackage -Name 'OpenAI.CodexPrimaryRuntime*' | Sort-Object Version -Descending | Select-Object -First 1
    if ($qaPaquete) { $Python = Join-Path $qaPaquete.InstallLocation 'dependencies/python/python.exe' }
}
if (-not $Python -or -not (Test-Path -LiteralPath $Python)) { throw 'Usa -Python con la ruta de Python 3.10 o superior.' }
if (-not $Jmeter) {
    $qaCarpeta = Split-Path -Parent (Split-Path -Parent $qaRaiz)
    $Jmeter = Join-Path $qaCarpeta 'tmp/jmeter/apache-jmeter-5.6.3'
}
if (-not (Test-Path -LiteralPath (Join-Path $Jmeter 'bin/ApacheJMeter.jar'))) { throw 'Usa -Jmeter con la carpeta de Apache JMeter.' }
if ($Caso -eq 'TC-P-07' -and -not $Validar -and -not $Contenedor) {
    $Contenedor = & docker compose --env-file (Join-Path $PSScriptRoot 'rendimiento/diana/.env') -p qap-diana-perf -f (Join-Path $PSScriptRoot 'rendimiento/diana/docker-compose.yml') ps -q app
    if (-not $Contenedor) { throw 'No se encontro el contenedor de Firefly para medir memoria.' }
}
$qaArgumentos = @((Join-Path $PSScriptRoot 'rendimiento/diana/ejecutar.py'), '--caso', $Caso, '--jmeter', $Jmeter, '--java', $Java)
if ($Validar) { $qaArgumentos += '--validar' }
if ($Contenedor) { $qaArgumentos += @('--contenedor', $Contenedor) }
& $Python @qaArgumentos
if ($LASTEXITCODE -ne 0) { throw 'La ejecucion no se completo o no cumplio el criterio; revisa los resultados.' }
