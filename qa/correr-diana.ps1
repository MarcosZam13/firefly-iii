param(
    [switch]$Preparar,
    [string]$Filtro = '',
    [ValidateRange(1, 8)][int]$Procesos = 2
)

$ErrorActionPreference = 'Stop'
$qaProyecto = Split-Path -Parent $PSScriptRoot
$qaImagen = 'qap-diana-qa:php8.5'
$qaVolumen = 'qap-diana-fase3-vendor'
$qaResultados = Join-Path $qaProyecto 'qa/resultados/diana'

function Invoke-QaDocker {
    param([string[]]$ContainerCommand)
    & docker run --rm --mount "type=bind,source=$qaProyecto,target=/app" --mount "type=volume,source=$qaVolumen,target=/app/vendor" $qaImagen @ContainerCommand
    if ($LASTEXITCODE -ne 0) { throw "El contenedor termino con codigo $LASTEXITCODE." }
}

Push-Location $qaProyecto
try {
    if ($Preparar) {
        & docker build -t $qaImagen qa/docker
        if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la imagen de pruebas.' }
        if (-not (Test-Path -LiteralPath '.env')) {
            Copy-Item -LiteralPath '.env.example' -Destination '.env'
            Invoke-QaDocker -ContainerCommand @('composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress', '--no-scripts')
            Invoke-QaDocker -ContainerCommand @('php', 'artisan', 'key:generate', '--force')
        } else {
            Invoke-QaDocker -ContainerCommand @('composer', 'install', '--no-interaction', '--prefer-dist', '--no-progress', '--no-scripts')
        }
        Invoke-QaDocker -ContainerCommand @('php', 'artisan', 'package:discover', '--env=testing')
        Invoke-QaDocker -ContainerCommand @('php', 'artisan', 'firefly-iii:laravel-passport-keys', '--env=testing')
    }

    New-Item -ItemType Directory -Path $qaResultados -Force | Out-Null
    $qaComando = @('vendor/bin/paratest', '-c', 'phpunit.qa.xml', "--processes=$Procesos", '--log-junit', 'qa/resultados/diana/junit.xml')
    if ($Filtro) { $qaComando += @('--filter', $Filtro) }
    $qaComando += 'tests/qa/Diana'
    Invoke-QaDocker -ContainerCommand $qaComando
} finally {
    Pop-Location
}
