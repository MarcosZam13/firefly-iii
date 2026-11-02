"""Ensayo: corre la colección completa con Newman para comprobar que la guía funciona.

NO es la ejecución manual ni sirve como evidencia: el profesor pide que cada integrante
ejecute y mire cada paso. Este script no sube nada a QAtlas. Sirve para saber, antes de
sentarse a ejecutar, que cada petición llega y qué responde el servidor real.

Uso: python qa/manual/ensayar.py [TC-F-01 TC-F-02 ...] --newman RUTA_A_NEWMAN
"""

import argparse
import json
import re
import subprocess
import sys
import tempfile
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import generar_coleccion as gc  # noqa: E402

AQUI = Path(__file__).parent
QA_SH = AQUI / "qa.sh"
COLECCION = AQUI / "postman" / "firefly-qa.postman_collection.json"
ENTORNO = AQUI / "postman" / "firefly-qa.postman_environment.json"


def shell(*args: str) -> str:
    r = subprocess.run(["sh", str(QA_SH), *args], capture_output=True, text=True, encoding="utf-8")
    if r.returncode:
        sys.exit(f"Falló qa.sh {' '.join(args)}: {r.stderr[:500]}")
    return "\n".join(l for l in r.stdout.splitlines() if "production." not in l)


def a_variables(texto: str) -> dict[str, str]:
    return dict(l.split("=", 1) for l in texto.splitlines() if "=" in l)


def fijar(ruta_entorno: Path, valores: dict[str, str]) -> None:
    entorno = json.loads(ruta_entorno.read_text(encoding="utf-8"))
    por_clave = {v["key"]: v for v in entorno["values"]}
    for clave, valor in valores.items():
        if clave in por_clave:
            por_clave[clave]["value"] = valor
    ruta_entorno.write_text(json.dumps(entorno, ensure_ascii=False), encoding="utf-8")


def leer(ruta_entorno: Path) -> dict[str, str]:
    return {v["key"]: v["value"] for v in json.loads(ruta_entorno.read_text(encoding="utf-8"))["values"]}


def correr_carpeta(newman: str, carpeta: str, dir_trabajo: Path) -> list[str]:
    entorno, jar, reporte = dir_trabajo / "entorno.json", dir_trabajo / "cookies.json", dir_trabajo / "reporte.json"
    orden = [newman, "run", str(COLECCION), "-e", str(entorno), "--folder", carpeta,
             "--export-environment", str(entorno), "--export-cookie-jar", str(jar),
             "--timeout-request", "90000", "-r", "json", "--reporter-json-export", str(reporte), "--silent"]
    if jar.exists():
        orden += ["--cookie-jar", str(jar)]
    subprocess.run(orden, capture_output=True, text=True, encoding="utf-8", shell=sys.platform == "win32")
    if not reporte.exists():
        return [f"Newman no generó reporte para {carpeta}"]
    lineas = []
    for ej in json.loads(reporte.read_text(encoding="utf-8"))["run"]["executions"]:
        respuesta = ej.get("response") or {}
        estado = respuesta.get("code", "sin respuesta")
        fallos = [a["assertion"] for a in ej.get("assertions", []) if a.get("error")]
        marca = "OK " if not fallos else "XX "
        lineas.append(f"  {marca}{estado}  {ej['item']['name']}")
        for f in fallos:
            lineas.append(f"       falla: {f}")
    reporte.unlink()
    return lineas


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("casos", nargs="*")
    parser.add_argument("--newman", default="newman")
    args = parser.parse_args()
    gc.generar()

    for caso in args.casos or list(gc.CASOS):
        with tempfile.TemporaryDirectory() as tmp:
            trabajo = Path(tmp)
            (trabajo / "entorno.json").write_text(ENTORNO.read_text(encoding="utf-8"), encoding="utf-8")
            fijar(trabajo / "entorno.json", a_variables(shell("preparar", caso)))
            print(f"\n{caso}")
            tramos = gc.partes(caso)
            for n, (tramo, corte) in enumerate(tramos, 1):
                if tramo:
                    carpeta = caso if len(tramos) == 1 else f"{caso} parte {n}"
                    print("\n".join(correr_carpeta(args.newman, carpeta, trabajo)))
                if corte is None:
                    continue
                if corte.comando:
                    valores = leer(trabajo / "entorno.json")
                    comando = re.sub(r"\{\{(\w+)\}\}", lambda m: valores.get(m.group(1), ""), corte.comando)
                    salida = shell(*comando.split()[2:])
                    print("  base: " + salida.replace("\n", " | ")[:300])
                    if corte.variable:
                        fijar(trabajo / "entorno.json", a_variables(salida))
                if corte.espera:
                    time.sleep(corte.espera)


if __name__ == "__main__":
    main()
