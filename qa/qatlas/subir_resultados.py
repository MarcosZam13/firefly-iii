"""Sube a QAtlas los resultados de la Fase 3 (una ejecución con un resultado por caso y por paso).

Fuentes:
  - qa/resultados/junit.xml: lo genera qa/correr.sh (paratest). Cada clase es un caso
    (TcF01... -> TC-F-01) y cada método paso_N_... es un resultado del paso N.
  - qa/qatlas/resultados-rendimiento.json: los TC-P, medidos con JMeter.

Por defecto SOLO SIMULA: muestra qué subiría. Para subir de verdad:
  python qa/qatlas/subir_resultados.py --subir

Cada subida crea una ejecución nueva (el profesor pide que todas queden registradas).
qa/correr.sh lo llama solo al terminar cada corrida. Una corrida automática sube solo lo
que acaba de correr: los TC-P no se vuelven a medir con PHPUnit, así que solo entran con
--con-rendimiento o --solo-rendimiento, nombrando la fecha real de la medición.

Primera ejecución de la Fase 3 (JUnit y JMeter del 2026-10-21):
  python qa/qatlas/subir_resultados.py --subir --con-rendimiento --id-externo fase3-marcos-principal \
      --nombre "Fase 3: Auth, Profile, unitarias y rendimiento, ejecutada 2026-10-21 (Marcos Zamora)"

Re-ejecución de TC-P-06 con la versión 3 del caso (después de la ejecución principal):
  python qa/qatlas/subir_resultados.py --subir --solo-rendimiento \
      --rendimiento qa/qatlas/reejecucion-tc-p-06-v3.json \
      --nombre "Re-ejecución TC-P-06 v3 (Marcos Zamora)" --id-externo fase3-marcos-tc-p-06-v3
El token se toma de QATLAS_TOKEN o se pide al correr (no se ve al escribirlo). Cada
integrante sube SUS casos con SU token: QAtlas registra quién ejecutó cada cosa.

Reglas de agregación:
  - Un paso falla si falla cualquiera de sus métodos (paso_N_*).
  - Si falla un método precondicion_* o control_*, el caso queda "bloqueado": la
    prueba no pudo verificar lo que el caso pide.
  - Si la cantidad de pasos no coincide con la del caso en QAtlas, se avisa y se
    sube solo el resultado del caso (QAtlas lo aplica a todos los pasos).
"""

import argparse
from datetime import datetime
import getpass
import json
import os
import re
import subprocess
import sys
import urllib.error
import urllib.request
import xml.etree.ElementTree as ET
from collections import defaultdict
from pathlib import Path

BASE = "https://geotorotur.tail052473.ts.net/qatlas/api"
PROYECTO = "FF"
RAIZ = Path(__file__).resolve().parents[2]
JUNIT = RAIZ / "qa" / "resultados" / "junit.xml"
RENDIMIENTO = Path(__file__).with_name("resultados-rendimiento.json")
# La API acepta máximo 100 caracteres en environment. El detalle completo está en qa/README.md.
ENTORNO_PHPUNIT = "PHPUnit 13.2 + paratest 7.23 en php:8.5-cli, SQLite en memoria; Windows 11, Docker"
ENTORNO_JMETER = "JMeter 5.6.3 contra core:version-6.6.6 + MariaDB; Windows 11, Docker"
ENTORNO_AMBOS = "PHPUnit + paratest en php:8.5-cli; JMeter contra core:6.6.6 + MariaDB; Windows 11"
assert max(map(len, (ENTORNO_PHPUNIT, ENTORNO_JMETER, ENTORNO_AMBOS))) <= 100


def leer_token() -> str:
    """QATLAS_TOKEN del proceso, luego el guardado en el usuario de Windows, y si no se pide."""
    token = os.environ.get("QATLAS_TOKEN")
    if not token and sys.platform == "win32":
        import winreg
        try:
            with winreg.OpenKey(winreg.HKEY_CURRENT_USER, "Environment") as clave:
                token = winreg.QueryValueEx(clave, "QATLAS_TOKEN")[0]
        except OSError:
            token = None
    return token or getpass.getpass("Token de QAtlas (qat_pat_...): ")


def codigo_desde_clase(nombre_clase: str) -> str | None:
    """Tests\\qa\\Auth\\TcF01LoginExitosoTest -> TC-F-01 ; TcU02SecretoTotpTest -> TC-U-02."""
    m = re.search(r"Tc([FUP])(\d+)[A-Z]", nombre_clase.split("\\")[-1])
    return f"TC-{m.group(1)}-{int(m.group(2)):02d}" if m else None


def mensaje_de_falla(texto: str) -> str:
    """La línea útil de una falla de PHPUnit: la de la aserción, no el nombre de la prueba."""
    for linea in texto.splitlines():
        if re.search(r"Expected|Failed asserting", linea):
            return linea.strip()[:300]
    return texto.strip().splitlines()[0][:300] if texto.strip() else ""


def leer_junit() -> dict:
    """Devuelve {TC-X-NN: {"pasos": {N: [(metodo, ok, mensaje)]}, "previos": [...]}}."""
    casos = defaultdict(lambda: {"pasos": defaultdict(list), "previos": []})
    for tc in ET.parse(JUNIT).getroot().iter("testcase"):
        codigo = codigo_desde_clase(tc.get("class") or tc.get("classname", ""))
        if not codigo:
            continue
        falla = tc.find("failure") if tc.find("failure") is not None else tc.find("error")
        ok = falla is None
        mensaje = "" if ok else mensaje_de_falla(falla.text or falla.get("message") or "")
        metodo = tc.get("name", "")
        paso = re.match(r"paso_(\d+)_", metodo)
        if paso:
            casos[codigo]["pasos"][int(paso.group(1))].append((metodo, ok, mensaje))
        else:
            casos[codigo]["previos"].append((metodo, ok, mensaje))
    return casos


def armar_resultado(codigo: str, datos: dict, pasos_en_qatlas: int | None) -> dict:
    pasos = []
    for n in sorted(datos["pasos"]):
        fallas = [m for m in datos["pasos"][n] if not m[1]]
        pasos.append({
            "title": ", ".join(m[0] for m in datos["pasos"][n]),
            "status": "fallido" if fallas else "aprobado",
            "error_message": "; ".join(f"{m[0]}: {m[2]}" for m in fallas) or None,
        })
    previos_fallidos = [m for m in datos["previos"] if not m[1]]
    if previos_fallidos:
        estado = "bloqueado"
    elif any(p["status"] == "fallido" for p in pasos):
        estado = "fallido"
    else:
        estado = "aprobado"
    resultado = {"test_case_code": codigo, "status": estado}
    errores = [f"{m[0]}: {m[2]}" for m in previos_fallidos] + [p["error_message"] for p in pasos if p["error_message"]]
    if errores:
        resultado["error_message"] = " | ".join(errores)[:1000]
    if pasos_en_qatlas is None or pasos_en_qatlas == len(pasos):
        resultado["steps"] = pasos
    else:
        print(f"  AVISO {codigo}: {len(pasos)} pasos en la prueba y {pasos_en_qatlas} en QAtlas; se sube solo el resultado del caso.")
    return resultado


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


def git(*args: str) -> str:
    return subprocess.run(["git", *args], cwd=RAIZ, capture_output=True, text=True).stdout.strip()


def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--subir", action="store_true", help="subir de verdad (sin esto solo simula)")
    parser.add_argument("--nombre", help="nombre de la ejecución (por defecto: fecha, hora y quién la subió)")
    parser.add_argument("--rendimiento", default=str(RENDIMIENTO), help="JSON con los resultados de JMeter")
    parser.add_argument("--con-rendimiento", action="store_true", help="agregar los TC-P del JSON a los del JUnit")
    parser.add_argument("--solo-rendimiento", action="store_true", help="no leer el JUnit (re-ejecución de un TC-P)")
    parser.add_argument("--filtro", help="la corrida fue parcial: se indica en el nombre")
    parser.add_argument("--id-externo", help="external_id fijo: volver a subir actualiza esa misma ejecución (por defecto cada subida crea una nueva)")
    args = parser.parse_args()

    casos = {} if args.solo_rendimiento else leer_junit()
    usa_rendimiento = args.con_rendimiento or args.solo_rendimiento
    rendimiento = json.loads(Path(args.rendimiento).read_text(encoding="utf-8"))["casos"] if usa_rendimiento else {}
    token = None
    quien = "simulación"
    pasos_qatlas: dict[str, int] = {}
    codigo_ff: dict[str, str] = {}

    if args.subir:
        token = leer_token()
        quien = pedir("GET", "/auth/me", token)["name"]
        for item in pedir("GET", f"/test-cases/automation-ids?project={PROYECTO}", token):
            m = re.match(r"(TC-[FUP]-\d+)", item["title"])
            if m:
                codigo_ff[m.group(1)] = item["code"]
        for tc in list(casos) + list(rendimiento):
            if tc in codigo_ff:
                pasos_qatlas[tc] = len(pedir("GET", f"/test-cases/by-code/{codigo_ff[tc]}", token)["steps"])

    resultados = []
    for codigo in sorted(casos):
        r = armar_resultado(codigo, casos[codigo], pasos_qatlas.get(codigo))
        resultados.append(r)
    for codigo, datos in sorted(rendimiento.items()):
        r = {"test_case_code": codigo, "status": "fallido" if any(p["status"] == "fallido" for p in datos["pasos"]) else "aprobado",
             "duration_ms": datos["duration_ms"]}
        if pasos_qatlas.get(codigo, len(datos["pasos"])) == len(datos["pasos"]):
            r["steps"] = datos["pasos"]
        errores = [p.get("error_message") for p in datos["pasos"] if p.get("error_message")]
        if errores:
            r["error_message"] = " | ".join(errores)
        resultados.append(r)

    ahora = datetime.now()
    parcial = f" parcial ({args.filtro})" if args.filtro else ""
    nombre = args.nombre or f"Ejecución automática{parcial} {ahora:%Y-%m-%d %H:%M} ({quien})"
    usuario = re.sub(r"[^a-z0-9]+", "-", quien.lower()).strip("-")
    entorno = ENTORNO_JMETER if args.solo_rendimiento else ENTORNO_AMBOS if rendimiento else ENTORNO_PHPUNIT
    envio = {
        "project": PROYECTO,
        "run": {
            "name": nombre,
            "external_id": args.id_externo or f"fase3-{usuario}-{ahora:%Y%m%d-%H%M%S}",
            "environment": entorno,
            "build": "Firefly III 6.6.6",
            "branch": git("rev-parse", "--abbrev-ref", "HEAD"),
            "commit_sha": git("rev-parse", "HEAD"),
        },
        "results": resultados,
        "create_missing_cases": False,
        "create_bug_on_failure": False,
    }

    print(f"Ejecución: {envio['run']['name']} (external_id {envio['run']['external_id']})")
    for r in resultados:
        pasos = "".join("v" if p["status"] == "aprobado" else "X" for p in r.get("steps", []))
        print(f"  {r['test_case_code']:8} {r['status']:10} pasos [{pasos}]  {r.get('error_message', '')[:110]}")
    resumen = defaultdict(int)
    for r in resultados:
        resumen[r["status"]] += 1
    print(f"Total {len(resultados)} casos: {dict(resumen)}")

    if not args.subir:
        print("\nSimulación: no se subió nada. Para subir: --subir")
        return

    for r in resultados:
        r["test_case_code"] = codigo_ff.get(r["test_case_code"], r["test_case_code"])
    respuesta = pedir("POST", "/test-runs/ingest", token, envio)
    print(f"\nEjecución {respuesta['test_run']['code']} ({'creada' if respuesta['created_run'] else 'actualizada'}): {respuesta['summary']}")
    for fila in respuesta["rows"]:
        if not fila["matched"]:
            print(f"  SIN CASO: {fila['key']}: {fila['message']}")
    ids = {fila["test_case_code"]: fila["test_run_case_id"] for fila in respuesta["rows"] if fila["matched"]}
    try:
        pedir("POST", f"/test-runs/{respuesta['test_run']['id']}/complete", token)
        print("Ejecución cerrada.")
    except SystemExit as e:
        print(f"AVISO: los resultados quedaron subidos, pero no se pudo cerrar la ejecución. {e}")
    if args.solo_rendimiento:
        return  # la re-ejecución no reemplaza los ids que usa crear_defectos.py
    Path(__file__).with_name("ultima-ejecucion.json").write_text(
        json.dumps({"test_run": respuesta["test_run"]["code"], "test_run_id": respuesta["test_run"]["id"],
                    "test_run_case_ids": ids, "codigos": codigo_ff}, indent=2), encoding="utf-8")
    print("IDs de cada caso en la ejecución guardados en qa/qatlas/ultima-ejecucion.json (los usa crear_defectos.py).")


if __name__ == "__main__":
    main()
