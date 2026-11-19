# Proyecto de QA sobre Firefly III 6.6.6 (Fase 3)

Equipo: Marcos Zamora Sánchez (Auth + Profile), Kevin Reyes Quesada (Transaction + Category) y Diana Camacho Jiménez (Account + Budget). Curso Calidad de Software, TEC San Carlos, grupo 50. Los resultados y su análisis están en el informe de pruebas que se entrega junto con este código.

## Qué hay y dónde

| Parte | Pruebas automatizadas | Rendimiento (JMeter) | Ejecución manual y QAtlas |
|---|---|---|---|
| Marcos | `tests/qa/Auth`, `tests/qa/Profile`, `tests/qa/Unitarias/TcU01*` y `TcU02*` | `qa/rendimiento/jmeter/login-*.jmx` (TC-P-01, TC-P-06), resultados en `qa/rendimiento/RESULTADOS.md` | `qa/manual/` (Postman, `guia.md`, evidencias), `qa/qatlas/subir_resultados.py` |
| Kevin | `tests/qa/Transaction`, `tests/qa/Category`, `tests/qa/Unitarias/TcU03*` a `TcU06*` | `qa/rendimiento/jmeter/tc-p-0{2,4,5,8}-*.jmx`, `generar_planes.py`, `correr-jmeter.sh` | `qa/manual/manual_kevin.py` |
| Diana | `tests/qa/Diana` (TC-F-43 a 58, TC-U-07 y 08) | `qa/rendimiento/diana/` (TC-P-03, TC-P-07) | `qa/qatlas/subir_diana.py`; detalle en `qa/DIANA.md` |

Patrón común: una clase por caso de prueba, `setUp()` hace las precondiciones y los pasos, y cada método `paso_N_*` verifica un solo resultado esperado con un solo *assert*. La base de cada prueba es SQLite en memoria, así que los casos son independientes y corren en paralelo.

## Correr todas las pruebas (los tres integrantes, en simultáneo)

Después de preparar la máquina (sección siguiente):

```bash
QA_NO_SUBIR=1 sh qa/correr.sh          # todo, 4 procesos, JUnit en qa/resultados/junit.xml
QA_NO_SUBIR=1 sh qa/correr.sh TcF21    # un solo caso
```

Sin `QA_NO_SUBIR`, el script sube el resultado a QAtlas como una ejecución nueva con el token de quien lo corre.

Resultado esperado (verificado el 18 de noviembre de 2026): **299 pruebas, 297 pasan y 2 fallan**. Las dos fallas son defectos reales de Firefly, no errores de las pruebas:

- `TcF04BloqueoFuerzaBrutaTest::paso_3_...` → DEF-01: el login bloqueado responde 500 en vez de 429 (tarda hasta 60 s en fallar, a propósito).
- `TcF19DatosTrasEliminarUsuarioTest::paso_3_...` → DEF-02: una cuenta ajena responde 401 en vez de 404.

Los otros dos defectos del informe (DEF-03 y DEF-04) vienen de rendimiento y de una prueba complementaria con otra moneda, explicadas en el informe.

Las pruebas de rendimiento necesitan Firefly levantado con `qa/rendimiento/docker-compose.yml` (o `qa/rendimiento/diana/docker-compose.yml`) y JMeter 5.6.3; cada carpeta explica cómo sembrar los usuarios y correr los planes.

## Preparar la máquina

Entorno de pruebas para correr PHPUnit sobre Firefly III 6.6.6 sin instalar PHP en la computadora. Solo hace falta Docker Desktop.

### Una vez por máquina

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

### Correr con docker directamente

```bash
# Todas en simultáneo (criterio de la rúbrica), 4 procesos
docker run --rm -v "$PWD:/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \
  vendor/bin/paratest -c phpunit.qa.xml --processes=4 --testsuite qa --log-junit qa/resultados/junit.xml

# Una sola clase mientras se escribe
docker run --rm -v "$PWD:/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \
  vendor/bin/phpunit -c phpunit.qa.xml --filter LoginExitosoTest
```

### Por qué `phpunit.qa.xml` y no `phpunit.xml`

- **SQLite en memoria.** `phpunit.xml` de Firefly deja `DB_DATABASE` vacío, así que usa un archivo compartido (`storage/database/database.sqlite`). En paralelo, los procesos se pisan las migraciones (`no such table: migrations`). En memoria, cada proceso tiene su base.
- **Zona horaria fija** (`America/Costa_Rica`), para que una prueba no cambie de resultado según la hora UTC.
- **Sin cobertura ni `stopOnFailure`.** La imagen no trae driver de cobertura, y queremos el resultado de todas las pruebas, no solo hasta la primera falla.

Verificado el 2026-10-21: la suite `unit` de Firefly (373 pruebas) corre con 4 procesos sin errores de base de datos. Fallan 3 pruebas propias de Firefly (`NavigationStartOfPeriodTest`, inicio de semana) que dependen de la fecha, no del entorno.
