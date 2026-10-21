# Pruebas del proyecto de QA (Fase 3)

Entorno de pruebas para correr PHPUnit sobre Firefly III 6.6.6 sin instalar PHP en la computadora. Solo hace falta Docker Desktop.

## Una vez por máquina

```bash
# 1. Imagen con PHP 8.5 + Composer + extensiones de Firefly
docker build -t firefly-qa:php8.5 qa/docker

# 2. Dependencias (incluye PHPUnit, Mockery y paratest). vendor/ vive en un
#    volumen de Docker: montado desde Windows sería muy lento.
docker run --rm -v "$PWD:/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 composer install

# 3. .env con APP_KEY
cp .env.example .env
docker run --rm -v "$PWD:/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 php artisan key:generate --force
```

En Git Bash de Windows, anteponer `MSYS_NO_PATHCONV=1` a cada `docker run` y usar `$(pwd -W)` en vez de `$PWD`.

## Correr las pruebas

```bash
# Todas en simultáneo (criterio de la rúbrica), 4 procesos
docker run --rm -v "$PWD:/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \
  vendor/bin/paratest -c phpunit.qa.xml --processes=4 --testsuite qa --log-junit qa/resultados/junit.xml

# Una sola clase mientras se escribe
docker run --rm -v "$PWD:/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \
  vendor/bin/phpunit -c phpunit.qa.xml --filter LoginExitosoTest
```

## Por qué `phpunit.qa.xml` y no `phpunit.xml`

- **SQLite en memoria.** `phpunit.xml` de Firefly deja `DB_DATABASE` vacío, así que usa un archivo compartido (`storage/database/database.sqlite`). En paralelo, los procesos se pisan las migraciones (`no such table: migrations`). En memoria, cada proceso tiene su base.
- **Zona horaria fija** (`America/Costa_Rica`), para que una prueba no cambie de resultado según la hora UTC.
- **Sin cobertura ni `stopOnFailure`.** La imagen no trae driver de cobertura, y queremos el resultado de todas las pruebas, no solo hasta la primera falla.

Verificado el 2026-10-21: la suite `unit` de Firefly (373 pruebas) corre con 4 procesos sin errores de base de datos. Fallan 3 pruebas propias de Firefly (`NavigationStartOfPeriodTest`, inicio de semana) que dependen de la fecha, no del entorno.
