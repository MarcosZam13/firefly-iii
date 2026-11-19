#!/bin/sh
# Corre un plan de JMeter (TC-P-02, 04, 05 o 08) sin instalar JMeter: usa la imagen
# alpine/jmeter:5.6.3 conectada a la red del docker compose de esta carpeta (host "app").
# Deja resultados/<tc>.jtl, el reporte HTML resultados/<tc>-reporte/ y el resumen de analizar.py.
# Antes: docker compose up -d  y  sh preparar-transacciones.sh
# Uso (desde qa/rendimiento): sh correr-jmeter.sh tc-p-02 [-Jhilos=10 -Jduracion=30 ...]
cd "$(dirname "$0")" || exit 1
TC=$1
[ -n "$TC" ] || { echo "Uso: sh correr-jmeter.sh tc-p-02|tc-p-04|tc-p-05|tc-p-08 [-Jpropiedad=valor ...]" >&2; exit 1; }
shift
PLAN=$(ls jmeter/"$TC"-*.jmx 2>/dev/null | head -n 1)
[ -n "$PLAN" ] || { echo "No hay plan jmeter/$TC-*.jmx" >&2; exit 1; }
[ -s resultados/tokens.csv ] || { echo "Falta resultados/tokens.csv: correr primero sh preparar-transacciones.sh" >&2; exit 1; }
PY=$(command -v python || command -v python3)
RED=$(docker compose ps -q app | xargs docker inspect -f '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{end}}')
[ -n "$RED" ] || { echo "Firefly no está corriendo: docker compose up -d" >&2; exit 1; }

rm -rf "resultados/$TC.jtl" "resultados/$TC-reporte"
EXTRA=""
[ "$TC" = "tc-p-08" ] && EXTRA="-q /pruebas/resultados/concurrencia.properties"
MSYS_NO_PATHCONV=1 docker run --rm --network "$RED" -v "$(pwd -W 2>/dev/null || pwd):/pruebas" -w /pruebas/jmeter alpine/jmeter:5.6.3 \
  -n -t "/pruebas/$PLAN" -Jhost=app -Jpuerto=8080 -Jtokens=/pruebas/resultados/tokens.csv $EXTRA "$@" \
  -l "/pruebas/resultados/$TC.jtl" -j "/pruebas/resultados/$TC.log" -e -o "/pruebas/resultados/$TC-reporte" || exit 1

echo "== Resumen $TC (completo)"
"$PY" analizar.py "resultados/$TC.jtl"
if [ "$TC" = "tc-p-05" ]; then
  for INICIO in 0 30 60 90 120; do
    echo "== Ventana $INICIO-$((INICIO + 30)) s"
    "$PY" analizar.py "resultados/$TC.jtl" "$INICIO" "$((INICIO + 30))"
  done
fi
if [ "$TC" = "tc-p-08" ]; then
  grep "TC-P-08" "resultados/$TC.log" | sed 's/.*INFO [^:]*: //'
fi
