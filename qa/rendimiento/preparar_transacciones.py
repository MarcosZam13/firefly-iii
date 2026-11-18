"""Prepara los datos de TC-P-02, 04, 05 y 08 a partir del JSON de sembrar-transacciones.php.

- Por usuario, la cuenta de activo "QA Perf Transacciones" (EUR, saldo inicial 100000.00), y
  para qa-perf-001 la cuenta "QA Perf Concurrencia" de TC-P-08. Se crean por la API como lo
  haría el usuario; si ya existen se reutilizan.
- resultados/tokens.csv: correo,token,cuenta (lo leen los planes de JMeter; no se sube a git).
- resultados/concurrencia.properties: token y cuenta de TC-P-08.
- Historial para TC-P-04: HISTORIAL gastos por usuario por la API (POST /api/v1/transactions),
  así el listado paginado tiene varias páginas. Si el usuario ya los tiene, no los repite.

Uso (lo llama preparar-transacciones.sh): python3 preparar_transacciones.py siembra.json [historial]
"""

from __future__ import annotations

import json
import sys
import urllib.request
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

AQUI = Path(__file__).parent
RESULTADOS = AQUI / "resultados"
BASE = "http://localhost:8080/api/v1"


def pedir(metodo: str, ruta: str, token: str, cuerpo: dict | None = None) -> dict:
    req = urllib.request.Request(f"{BASE}{ruta}", method=metodo, data=json.dumps(cuerpo).encode() if cuerpo else None,
                                 headers={"Authorization": f"Bearer {token}", "Accept": "application/json",
                                          "Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read() or b"{}")


def cuenta(token: str, nombre: str) -> int:
    for c in pedir("GET", "/accounts?type=asset&limit=200", token)["data"]:
        if c["attributes"]["name"] == nombre:
            if c["attributes"].get("object_has_currency_setting"):
                return int(c["id"])
            # Cuenta creada directo en la base (sin moneda): su saldo no se calcula. Se reemplaza.
            pedir("DELETE", f"/accounts/{c['id']}", token)
    return int(pedir("POST", "/accounts", token, {
        "name": nombre, "type": "asset", "account_role": "defaultAsset", "currency_code": "EUR",
        "opening_balance": "100000.00", "opening_balance_date": "2026-01-01",
    })["data"]["id"])


def sembrar_historial(usuario: dict, cantidad: int) -> int:
    ya = pedir("GET", f"/accounts/{usuario['cuenta']}/transactions?limit=1", usuario["token"])["meta"]["pagination"]["total"]
    for n in range(ya, cantidad):
        pedir("POST", "/transactions", usuario["token"], {
            "error_if_duplicate_hash": False, "apply_rules": False, "fire_webhooks": False,
            "transactions": [{"type": "withdrawal", "date": f"2026-10-{n % 28 + 1:02d}", "amount": "1.00",
                              "description": f"QA historial {n + 1}", "source_id": str(usuario["cuenta"]),
                              "destination_name": "Comercio QA rendimiento"}],
        })
    return max(cantidad - ya, 0)


def main() -> None:
    siembra = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
    historial = int(sys.argv[2]) if len(sys.argv) > 2 else 120
    RESULTADOS.mkdir(exist_ok=True)
    usuarios = siembra["usuarios"]
    with ThreadPoolExecutor(max_workers=10) as grupo:
        for u, id_cuenta in zip(usuarios, grupo.map(lambda u: cuenta(u["token"], "QA Perf Transacciones"), usuarios)):
            u["cuenta"] = id_cuenta
    c = {"token": usuarios[0]["token"], "cuenta": cuenta(usuarios[0]["token"], "QA Perf Concurrencia")}
    (RESULTADOS / "tokens.csv").write_text(
        "".join(f"{u['correo']},{u['token']},{u['cuenta']}\n" for u in usuarios), encoding="utf-8")
    (RESULTADOS / "concurrencia.properties").write_text(f"token={c['token']}\ncuenta={c['cuenta']}\n", encoding="utf-8")
    print(f"tokens.csv: {len(usuarios)} usuarios; cuenta de concurrencia {c['cuenta']}")
    with ThreadPoolExecutor(max_workers=10) as grupo:
        creados = sum(grupo.map(lambda u: sembrar_historial(u, historial), usuarios))
    print(f"Historial: {historial} gastos por usuario ({creados} creados ahora).")


if __name__ == "__main__":
    main()
