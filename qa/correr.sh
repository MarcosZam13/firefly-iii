#!/bin/sh
# Corre la suite de QA dentro del contenedor (todas en simultaneo con paratest),
# deja el JUnit en qa/resultados/ y sube los resultados a QAtlas como ejecuciones
# nuevas (una por tipo: funcionales y unitarias): el profesor pide que cada corrida quede registrada.
# Uso: sh qa/correr.sh [filtro]
# Sin subir, mientras se escribe una prueba: QA_NO_SUBIR=1 sh qa/correr.sh [filtro]
cd "$(dirname "$0")/.." || exit 1
mkdir -p qa/resultados
# Se borra antes para no subir nunca el JUnit de una corrida anterior si paratest se cae.
rm -f qa/resultados/junit.xml
if [ -n "$1" ]; then FILTRO="--filter $1"; else FILTRO=""; fi
MSYS_NO_PATHCONV=1 docker run --rm -v "$(pwd -W 2>/dev/null || pwd):/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \
  vendor/bin/paratest -c phpunit.qa.xml --processes=4 --testsuite qa $FILTRO --log-junit qa/resultados/junit.xml
RESULTADO=$?
# En Mac no existe "python", solo "python3".
PY=$(command -v python || command -v python3)

if [ ! -s qa/resultados/junit.xml ]; then
  echo "No se genero el JUnit: no se sube nada a QAtlas." >&2
elif [ -n "$QA_NO_SUBIR" ]; then
  echo "QA_NO_SUBIR activo: no se sube a QAtlas."
else
  # Una ejecucion por tipo: funcionales y unitarias por separado (los TC-P se suben aparte).
  for TIPO in funcional unitaria; do
    if [ -n "$1" ]; then
      "$PY" qa/qatlas/subir_resultados.py --subir --tipo $TIPO --filtro "$1" || echo "AVISO: fallo la subida $TIPO a QAtlas." >&2
    else
      "$PY" qa/qatlas/subir_resultados.py --subir --tipo $TIPO || echo "AVISO: fallo la subida $TIPO a QAtlas." >&2
    fi
  done
fi

# El codigo de salida es el de las pruebas, no el de la subida.
exit $RESULTADO
