"""Registra en QAtlas la ejecución manual de los casos de Marcos. Lo que se marca es lo que
la persona vio: este script no decide ningún resultado.

  python qa/manual/manual_qatlas.py crear
  python qa/manual/manual_qatlas.py marcar TC-F-04 3 fallido "HTTP 500 en vez de 429"
  python qa/manual/manual_qatlas.py capturas
  python qa/manual/manual_qatlas.py estado
  python qa/manual/manual_qatlas.py cerrar

Los resultados también se pueden marcar en la pantalla de QAtlas; las dos vías escriben
en la misma ejecución. Las capturas van en qa/manual/capturas/TC-F-04/paso-3.png: el
número de paso sale del nombre del archivo. Sin número, se adjuntan al caso completo.
"""

import argparse
import json
import re
import sys
import urllib.error
import urllib.request
import uuid
from datetime import datetime
from pathlib import Path

AQUI = Path(__file__).parent
sys.path.insert(0, str(AQUI.parent / "qatlas"))
from subir_resultados import BASE, leer_token, pedir  # noqa: E402

PROYECTO_ID = 6
PROYECTO = "FF"
CASOS_DE_MARCOS = 25  # FF-TC-1 a FF-TC-25
ESTADOS = ("aprobado", "fallido", "bloqueado", "omitido")
EXTENSIONES = {".png": "image/png", ".jpg": "image/jpeg", ".jpeg": "image/jpeg", ".pdf": "application/pdf"}


def codigo_de_titulo(titulo: str) -> str:
    return titulo.split(" ", 1)[0]


def cargar(archivo: Path) -> dict:
    if not archivo.exists():
        sys.exit("Falta la ejecución: correr primero el comando crear.")
    return json.loads(archivo.read_text(encoding="utf-8"))


def crear(args, token: str) -> None:
    if args.archivo.exists():
        sys.exit(f"Ya hay una ejecución en {args.archivo}. Para otra ejecución, borrar ese archivo primero.")
    ids = [pedir("GET", f"/test-cases/by-code/{PROYECTO}-TC-{n}", token)["id"] for n in range(1, CASOS_DE_MARCOS + 1)]
    quien = pedir("GET", "/auth/me", token)["name"]
    nombre = args.nombre or f"Ejecución manual {datetime.now():%Y-%m-%d} ({quien})"
    corrida = pedir("POST", "/test-runs", token, {"project_id": PROYECTO_ID, "name": nombre, "test_case_ids": ids})
    pedir("PATCH", f"/test-runs/{corrida['id']}", token, {
        "environment": "Manual: Postman y terminal contra core:version-6.6.6 + MariaDB, Windows 11",
        "build": "Firefly III 6.6.6",
    })
    args.archivo.write_text(json.dumps({"id": corrida["id"], "code": corrida["code"], "nombre": nombre}, indent=2), encoding="utf-8")
    print(f"Ejecución {corrida['code']} creada con {len(corrida['cases'])} casos: {nombre}")


def casos_de(corrida_id: int, token: str) -> dict:
    return {codigo_de_titulo(c["test_case_title"]): c for c in pedir("GET", f"/test-runs/{corrida_id}", token)["cases"]}


def marcar(args, token: str) -> None:
    ejecucion = cargar(args.archivo)
    caso = casos_de(ejecucion["id"], token).get(args.caso)
    if caso is None:
        sys.exit(f"{args.caso} no está en {ejecucion['code']}.")
    pasos = caso["step_results"]
    if not any(p["order"] == args.paso for p in pasos):
        sys.exit(f"{args.caso} no tiene paso {args.paso}: tiene {len(pasos)}.")
    for p in pasos:
        if p["order"] == args.paso:
            p["status"] = args.estado
            p["actual_result"] = args.obtenido
    cuerpo = {"step_results": pasos}
    # La API no deriva el estado del caso: se fija cuando ya están marcados todos los pasos.
    estados = {p.get("status") for p in pasos}
    if estados <= set(ESTADOS):
        cuerpo["status"] = next(e for e in ("fallido", "bloqueado", "omitido", "aprobado") if e in estados)
    pedir("PATCH", f"/test-runs/{ejecucion['id']}/cases/{caso['id']}", token, cuerpo)
    actualizado = casos_de(ejecucion["id"], token)[args.caso]
    print(f"{args.caso} paso {args.paso}: {args.estado}. Estado del caso: {actualizado['status']}")


def adjuntar(token: str, caso_id: int, ruta: Path, paso: int | None) -> None:
    separador = uuid.uuid4().hex
    partes = [("entity_type", "test_run_case"), ("entity_id", str(caso_id))]
    if paso is not None:
        partes.append(("step_order", str(paso)))
    cuerpo = b"".join(f'--{separador}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k, v in partes)
    cuerpo += (f'--{separador}\r\nContent-Disposition: form-data; name="file"; filename="{ruta.name}"\r\n'
               f"Content-Type: {EXTENSIONES[ruta.suffix.lower()]}\r\n\r\n").encode() + ruta.read_bytes() + f"\r\n--{separador}--\r\n".encode()
    pedido = urllib.request.Request(f"{BASE}/attachments", data=cuerpo, method="POST", headers={
        "Authorization": f"Bearer {token}", "Content-Type": f"multipart/form-data; boundary={separador}", "Accept": "application/json"})
    try:
        with urllib.request.urlopen(pedido, timeout=120):
            pass
    except urllib.error.HTTPError as e:
        sys.exit(f"QAtlas respondió {e.code} al subir {ruta.name}: {e.read().decode()[:300]}")


def capturas(args, token: str) -> None:
    ejecucion = cargar(args.archivo)
    casos = casos_de(ejecucion["id"], token)
    subidas = saltadas = 0
    for carpeta in sorted(p for p in args.capturas.iterdir() if p.is_dir()):
        caso = casos.get(carpeta.name)
        if caso is None:
            print(f"AVISO: {carpeta.name} no es un caso de {ejecucion['code']}; se ignora.")
            continue
        for ruta in sorted(carpeta.iterdir()):
            if ruta.suffix.lower() not in EXTENSIONES:
                continue
            numero = re.search(r"paso-?(\d+)", ruta.stem)
            paso = int(numero.group(1)) if numero else None
            # El listado filtra por paso: sin step_order solo trae los adjuntos del caso completo.
            filtro = f"&step_order={paso}" if paso is not None else ""
            existentes = pedir("GET", f"/attachments?entity_type=test_run_case&entity_id={caso['id']}{filtro}", token)
            if ruta.name in {a["filename"] for a in existentes}:
                saltadas += 1
                continue
            adjuntar(token, caso["id"], ruta, paso)
            subidas += 1
            print(f"  {carpeta.name}/{ruta.name}")
    print(f"Capturas: {subidas} subidas, {saltadas} ya estaban.")


def estado(args, token: str) -> None:
    ejecucion = cargar(args.archivo)
    casos = casos_de(ejecucion["id"], token)
    print(f"{ejecucion['code']}: {ejecucion['nombre']}")
    for codigo, caso in sorted(casos.items()):
        pasos = "".join({"aprobado": "v", "fallido": "X", "bloqueado": "B", "omitido": "-"}.get(p.get("status"), ".") for p in caso["step_results"])
        print(f"  {codigo:8} {caso['status']:13} [{pasos}]")


def cerrar(args, token: str) -> None:
    ejecucion = cargar(args.archivo)
    pendientes = [c for c, d in casos_de(ejecucion["id"], token).items() if d["status"] == "sin_ejecutar"]
    if pendientes and not args.forzar:
        sys.exit(f"Faltan por ejecutar: {', '.join(sorted(pendientes))}. Para cerrar igual: --forzar")
    pedir("POST", f"/test-runs/{ejecucion['id']}/complete", token)
    print(f"{ejecucion['code']} cerrada.")


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--archivo", type=Path, default=AQUI / "ejecucion-manual.json", help="dónde se guarda el id de la ejecución")
    parser.add_argument("--capturas", type=Path, default=AQUI / "capturas")
    sub = parser.add_subparsers(dest="comando", required=True)
    sub.add_parser("crear").add_argument("--nombre")
    m = sub.add_parser("marcar")
    m.add_argument("caso")
    m.add_argument("paso", type=int)
    m.add_argument("estado", choices=ESTADOS)
    m.add_argument("obtenido", help="lo que se vio de verdad")
    sub.add_parser("capturas")
    sub.add_parser("estado")
    sub.add_parser("cerrar").add_argument("--forzar", action="store_true")
    args = parser.parse_args()
    {"crear": crear, "marcar": marcar, "capturas": capturas, "estado": estado, "cerrar": cerrar}[args.comando](args, leer_token())


if __name__ == "__main__":
    main()
