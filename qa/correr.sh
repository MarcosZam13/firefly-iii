#!/bin/sh
# Corre la suite de QA dentro del contenedor (todas en simultáneo con paratest)
# y deja el JUnit en qa/resultados/. Uso: sh qa/correr.sh [filtro]
cd "$(dirname "$0")/.." || exit 1
mkdir -p qa/resultados
if [ -n "$1" ]; then FILTRO="--filter $1"; else FILTRO=""; fi
MSYS_NO_PATHCONV=1 docker run --rm -v "$(pwd -W 2>/dev/null || pwd):/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \
  vendor/bin/paratest -c phpunit.qa.xml --processes=4 --testsuite qa $FILTRO --log-junit qa/resultados/junit.xml
