"""Crea en QAtlas los defectos de qa/qatlas/defectos.json, ligados al caso de la última ejecución.

Correr DESPUÉS de subir_resultados.py --subir (usa qa/qatlas/ultima-ejecucion.json para
ligar cada defecto con su caso dentro de la ejecución). Idempotente: si ya existe un
defecto con el mismo título, no lo crea otra vez.

Por defecto SOLO SIMULA. Para crear: python qa/qatlas/crear_defectos.py --subir
"""

import argparse
import getpass
import json
import os
import sys
import urllib.error
import urllib.parse
import urllib.request
from pathlib import Path

BASE = "https://geotorotur.tail052473.ts.net/qatlas/api"
PROYECTO_ID = 6
AQUI = Path(__file__).parent


def pedir(metodo: str, ruta: str, token: str, cuerpo: dict | None = None):
    datos = json.dumps(cuerpo).encode() if cuerpo is not None else None
    req = urllib.request.Request(f"{BASE}{ruta}", data=datos, method=metodo, headers={
        "Authorization": f"Bearer {token}", "Content-Type": "application/json", "Accept": "application/json",
    })
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return json.loads(r.read() or b"null")
    except urllib.error.HTTPError as e:
        sys.exit(f"QAtlas respondió {e.code} en {metodo} {ruta}: {e.read().decode()[:500]}")


def cuerpo_del_defecto(d: dict, ejecucion: dict | None) -> dict:
    descripcion = (f"{d['descripcion']}\n\nSeveridad según el curso: {d['severidad_curso']}.\n"
                   f"Entorno: {d['entorno']}\n\nNotas: {d['notas']}")
    cuerpo = {
        "project_id": PROYECTO_ID,
        "title": d["titulo"],
        "description": descripcion,
        "severity": d["severidad"],
        "priority": d["prioridad"],
        "tags": d["etiquetas"],
        "preconditions": d["precondiciones"],
        "steps": d["pasos"],
        "expected_result": d["resultado_esperado"],
        "actual_result": d["resultado_obtenido"],
    }
    if ejecucion:
        codigo_ff = ejecucion["codigos"].get(d["caso"])
        cuerpo["source_test_run_case_id"] = ejecucion["test_run_case_ids"].get(codigo_ff)
    return cuerpo


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--subir", action="store_true")
    args = parser.parse_args()

    defectos = json.loads((AQUI / "defectos.json").read_text(encoding="utf-8"))["defectos"]
    archivo_ejecucion = AQUI / "ultima-ejecucion.json"
    ejecucion = json.loads(archivo_ejecucion.read_text(encoding="utf-8")) if archivo_ejecucion.exists() else None
    if args.subir and not ejecucion:
        sys.exit("Falta qa/qatlas/ultima-ejecucion.json: correr primero subir_resultados.py --subir")

    token = (os.environ.get("QATLAS_TOKEN") or getpass.getpass("Token de QAtlas (qat_pat_...): ")) if args.subir else None
    for d in defectos:
        cuerpo = cuerpo_del_defecto(d, ejecucion)
        print(f"{d['id']} [{d['severidad_curso']} -> severidad {d['severidad']}, prioridad {d['prioridad']}] caso {d['caso']}"
              f" -> ejecución-caso {cuerpo.get('source_test_run_case_id')}")
        if not args.subir:
            continue
        existentes = pedir("GET", f"/bugs?project_id={PROYECTO_ID}&q={urllib.parse.quote(d['id'])}", token)
        if any(b["title"] == d["titulo"] for b in existentes.get("data", [])):
            print("   ya existe, no se crea")
            continue
        creado = pedir("POST", "/bugs", token, cuerpo)
        print(f"   creado {creado['code']}")
    if not args.subir:
        print("\nSimulación: no se creó nada. Para crear: --subir")


if __name__ == "__main__":
    main()
