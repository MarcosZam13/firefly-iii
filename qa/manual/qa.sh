#!/bin/sh
# Atajo para la ejecucion manual. Corre qa/manual/ayudante.php dentro del Firefly local.
#   sh qa/manual/qa.sh arrancar            levanta Firefly en http://localhost:8080
#   sh qa/manual/qa.sh preparar TC-F-14    deja los datos del caso y muestra sus valores
#   sh qa/manual/qa.sh ver TC-F-14 [dato]  muestra lo que el caso pide revisar en la base
#   sh qa/manual/qa.sh token-vencido       TC-F-11 paso 3
#   sh qa/manual/qa.sh apagar              detiene los contenedores sin borrar datos
cd "$(dirname "$0")/../rendimiento" || exit 1
case "$1" in
  arrancar)
    docker compose up -d || exit 1
    i=0
    until [ "$(curl -s -m 5 -o /dev/null -w '%{http_code}' http://localhost:8080/login)" = "200" ]; do
      i=$((i + 1))
      [ "$i" -ge 60 ] && { echo "Firefly no respondio en 5 minutos." >&2; exit 1; }
      sleep 5
    done
    echo "Firefly listo en http://localhost:8080"
    ;;
  apagar)
    docker compose stop
    ;;
  preparar|ver|token-vencido)
    docker compose cp ../manual/ayudante.php app:/tmp/ayudante.php >/dev/null 2>&1 || exit 1
    # Los avisos de Firefly (canal Pushover sin configurar) salen por la misma salida: se filtran.
    MSYS_NO_PATHCONV=1 docker compose exec -T app php /tmp/ayudante.php "$@" | grep -v "production."
    ;;
  *)
    sed -n 2,7p "../manual/qa.sh"
    exit 2
    ;;
esac
