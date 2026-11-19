"""Descarga de QAtlas la definición (pasos, datos, resultado esperado) de los casos indicados
y la guarda en qa/qatlas/casos/<CODIGO>.json, para escribir sus pruebas automatizadas.
Solo lee: no modifica nada en QAtlas.

  python3 qa/qatlas/descargar_casos.py TC-F-34 TC-P-02 TC-P-04 TC-P-05 TC-P-08
"""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
from subir_resultados import PROYECTO, leer_token, pedir  # noqa: E402

DESTINO = Path(__file__).with_name("casos")


def main() -> None:
    pedidos = sys.argv[1:]
    if not pedidos:
        sys.exit(__doc__)
    token = leer_token()
    codigo_ff = {}
    for item in pedir("GET", f"/test-cases/automation-ids?project={PROYECTO}", token):
        m = re.match(r"(TC-[FUP]-\d+)", item["title"])
        if m:
            codigo_ff[m.group(1)] = item["code"]
    DESTINO.mkdir(exist_ok=True)
    for tc in pedidos:
        if tc not in codigo_ff:
            print(f"  {tc}: no está en QAtlas")
            continue
        caso = pedir("GET", f"/test-cases/by-code/{codigo_ff[tc]}", token)
        (DESTINO / f"{tc}.json").write_text(json.dumps(caso, indent=2, ensure_ascii=False), encoding="utf-8")
        print(f"  {tc} ({codigo_ff[tc]}): {len(caso.get('steps', []))} pasos -> qa/qatlas/casos/{tc}.json")


if __name__ == "__main__":
    main()
