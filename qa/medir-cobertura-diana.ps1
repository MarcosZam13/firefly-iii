$ErrorActionPreference = 'Stop'
$qaProyecto = Split-Path -Parent $PSScriptRoot
New-Item -ItemType Directory -Path (Join-Path $qaProyecto 'qa/resultados/diana') -Force | Out-Null
& docker run --rm --mount "type=bind,source=$qaProyecto,target=/app" --mount 'type=volume,source=qap-diana-fase3-vendor,target=/app/vendor' qap-diana-qa:php8.5 php -d pcov.enabled=1 vendor/bin/phpunit -c phpunit.diana-coverage.xml --coverage-clover qa/resultados/diana/cobertura-unitarias.xml --coverage-text=qa/resultados/diana/cobertura-unitarias.txt
if ($LASTEXITCODE -ne 0) { throw 'No se completo la medicion de cobertura. Revisa el mensaje anterior.' }
