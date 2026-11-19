#!/bin/sh
# Prepara Firefly (docker compose de esta carpeta) para TC-P-02, 04, 05 y 08:
# usuarios qa-perf, cuenta y token de API por usuario, cuenta de concurrencia e historial.
# Uso (desde qa/rendimiento, con docker compose up -d ya corriendo):
#   sh preparar-transacciones.sh [usuarios=50] [historial=120]
cd "$(dirname "$0")" || exit 1
mkdir -p resultados
PY=$(command -v python || command -v python3)
docker compose cp sembrar-transacciones.php app:/tmp/sembrar-transacciones.php || exit 1
# Solo la última línea es el JSON (Laravel puede escribir avisos antes).
MSYS_NO_PATHCONV=1 docker compose exec -T app php /tmp/sembrar-transacciones.php "${1:-50}" | tail -n 1 > resultados/siembra.json || exit 1
"$PY" preparar_transacciones.py resultados/siembra.json "${2:-120}"
