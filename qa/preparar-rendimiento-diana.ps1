$ErrorActionPreference = 'Stop'
$qaDirectorio = Join-Path $PSScriptRoot 'rendimiento/diana'
$qaEnv = Join-Path $qaDirectorio '.env'
if (-not (Test-Path -LiteralPath $qaEnv)) {
    $qaBytes = New-Object byte[] 32
    $qaGenerador = [System.Security.Cryptography.RandomNumberGenerator]::Create()
    $qaGenerador.GetBytes($qaBytes)
    $qaGenerador.Dispose()
    $qaClave = 'base64:' + [Convert]::ToBase64String($qaBytes)
    $qaPassword = [Guid]::NewGuid().ToString('N')
    @("QAP_APP_KEY=$qaClave", "QAP_DB_PASSWORD=$qaPassword") | Set-Content -LiteralPath $qaEnv -Encoding utf8
}
$qaCompose = @('compose', '--env-file', $qaEnv, '-p', 'qap-diana-perf', '-f', (Join-Path $qaDirectorio 'docker-compose.yml'))
& docker @qaCompose up -d
if ($LASTEXITCODE -ne 0) { throw 'No se pudo levantar el entorno local de rendimiento.' }
$qaDisponible = $false
for ($qaIntento = 0; $qaIntento -lt 60; $qaIntento++) {
    try {
        $qaRespuesta = Invoke-WebRequest 'http://localhost:8081/login' -TimeoutSec 5 -UseBasicParsing
        if ($qaRespuesta.StatusCode -eq 200) { $qaDisponible = $true; break }
    } catch { }
    Start-Sleep -Seconds 3
}
if (-not $qaDisponible) { throw 'Firefly no esta listo. Revisa docker compose logs del proyecto qap-diana-perf.' }
& docker @qaCompose cp (Join-Path $qaDirectorio 'sembrar-presupuestos.php') app:/tmp/sembrar-presupuestos.php
if ($LASTEXITCODE -ne 0) { throw 'No se pudo copiar la preparacion de datos.' }
& docker @qaCompose exec -T -e QAP_DIANA_SEED=1 app php /tmp/sembrar-presupuestos.php
if ($LASTEXITCODE -ne 0) { throw 'No se pudo preparar la lista de usuarios y presupuestos.' }
& docker @qaCompose cp app:/tmp/presupuestos-diana.csv (Join-Path $qaDirectorio 'usuarios.csv')
if ($LASTEXITCODE -ne 0) { throw 'No se pudo recuperar el CSV de usuarios de prueba.' }
Write-Host 'Entorno local listo en http://localhost:8081; CSV de 50 usuarios preparado.'
