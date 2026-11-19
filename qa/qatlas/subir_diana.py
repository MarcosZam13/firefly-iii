"""Registra exclusivamente los resultados ejecutados de Diana en QAtlas.

Sin opciones genera una simulacion local. --comprobar consulta QAtlas sin escribir.
--subir registra la corrida comprobada. Nunca incorpora los JSON de Marcos.
"""

from __future__ import annotations

import argparse
from collections import defaultdict
from datetime import datetime, timedelta, timezone
from decimal import Decimal
import hashlib
import http.client
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET

RAIZ = Path(__file__).resolve().parents[2]
BASE = "https://geotorotur.tail052473.ts.net/qatlas/api"
PROYECTO = "FF"
USUARIO_DIANA = 7
AUTOMATICOS = ({f"TC-F-{n:02}" for n in range(43, 59)} - {"TC-F-49", "TC-F-58"}) | {
    "TC-U-07", "TC-U-08", "TC-P-03", "TC-P-07"
}
MANIFIESTO = Path(__file__).with_name("diana_casos.json")


def leer_clave(archivo: Path | None = None) -> str:
    token = os.environ.get("QATLAS_TOKEN", "").strip()
    rutas = [archivo] if archivo else [
        Path(__file__).with_name(".env.diana"), RAIZ.parents[1] / "APIQATLAS.JSON"
    ]
    if token:
        return token.removeprefix("Bearer ").strip()
    for ruta in rutas:
        if ruta and ruta.is_file():
            texto = ruta.read_text(encoding="utf-8-sig")
            claves = set(re.findall(r"qat_pat_[A-Za-z0-9_-]+", texto))
            if len(claves) > 1:
                raise ValueError("El archivo de credenciales contiene mas de un token.")
            if claves:
                return claves.pop()
    raise ValueError("Falta el token personal de QAtlas en el archivo local o en QATLAS_TOKEN.")


def estado_prueba(nodo: ET.Element) -> tuple[str, str]:
    for etiqueta, estado in [("error", "bloqueado"), ("failure", "fallido"), ("skipped", "omitido")]:
        fallo = nodo.find(etiqueta)
        if fallo is not None:
            texto = fallo.get("message") or (fallo.text or etiqueta)
            return estado, texto[:1500]
    return "aprobado", ""


def combinar(estados: list[str]) -> str:
    for estado in ["bloqueado", "fallido", "omitido"]:
        if estado in estados:
            return estado
    return "aprobado" if estados else "omitido"


def pasos_de_prueba(codigo: str, nombre: str) -> list[int]:
    # Los proveedores repiten la misma asercion sobre los subcasos del plan.
    if codigo == "TC-F-43" and nombre.startswith("paso_1_"):
        return [1] if '"activo"' in nombre else [2]
    if codigo == "TC-F-47" and nombre.startswith("paso_2_tipo_invalido_responde_"):
        return [1, 2]  # HTTP 422 acredita rechazo y codigo esperado.
    if codigo == "TC-F-53" and nombre.startswith("paso_1_y_2_"):
        if '"negativo"' in nombre:
            return [1]
        if '"cero"' in nombre:
            return [2]
        return []
    match = re.match(r"paso_(\d+)_", nombre)
    return [int(match.group(1))] if match else []


def leer_resultados(junit: Path, manifiesto: dict) -> dict:
    grupos = defaultdict(list)
    for nodo in ET.parse(junit).getroot().iter("testcase"):
        clase = nodo.get("class") or nodo.get("classname", "")
        corta = re.split(r"[\\.]", clase)[-1]
        match = re.match(r"Tc([FUP])(\d+)[A-Z]", corta)
        if not match:
            continue
        codigo = f"TC-{match.group(1)}-{int(match.group(2)):02}"
        if codigo not in AUTOMATICOS:
            continue
        if codigo not in manifiesto or corta != manifiesto[codigo]["class"]:
            raise ValueError(f"{codigo}: falta declarar la clase y sus verificaciones completas en diana_casos.json.")
        grupos[codigo].append(nodo)
    if not grupos:
        raise ValueError("El JUnit no contiene casos automatizados de Diana.")
    salida = {}
    for codigo, nodos in sorted(grupos.items()):
        nombres = [n.get("name", "") for n in nodos]
        completo = len(nodos) == manifiesto[codigo]["checks"] and len(set(nombres)) == len(nombres)
        pasos = defaultdict(list)
        todos = []
        mensajes = []
        duracion = Decimal(0)
        for nodo in nodos:
            nombre = nodo.get("name", "")
            estado, mensaje = estado_prueba(nodo)
            todos.append(estado)
            if mensaje:
                mensajes.append(f"{nombre}: {mensaje}")
            indices = pasos_de_prueba(codigo, nombre)
            if not indices:
                completo = False
            for indice in indices:
                pasos[indice].append({"method": nombre, "status": estado, "message": mensaje})
            duracion += Decimal(nodo.get("time", "0"))
        estado = combinar(todos) if completo else "bloqueado"
        if not completo:
            mensajes.insert(0, "Corrida parcial o pasos sin identificar: no acredita el caso completo.")
        salida[codigo] = {
            "status": estado,
            "duration_ms": int(duracion * 1000),
            "checks": len(nodos),
            "complete": completo,
            "steps": dict(pasos),
            "error_message": " | ".join(mensajes)[:2000],
        }
    return salida


def git(*argumentos: str) -> str:
    p = subprocess.run(["git", *argumentos], cwd=RAIZ, capture_output=True, text=True)
    return p.stdout.strip() if p.returncode == 0 else ""


def construir_envio(junit: Path, resultados: dict, extras=()) -> dict:
    evidencia = junit.read_bytes() + b''.join(p.read_bytes() for p in extras)
    huella = hashlib.sha256(evidencia).hexdigest()
    fecha = datetime.fromtimestamp(junit.stat().st_mtime, timezone(timedelta(hours=-6)))
    filas = []
    for codigo, resultado in resultados.items():
        fila = {"test_case_code": codigo, "status": resultado["status"],
                "duration_ms": resultado["duration_ms"]}
        if resultado["error_message"]:
            mensaje = resultado["error_message"]
            if "metrics" in resultado:
                mensaje += " Criterio: " + resultado["criteria"] + " Medicion: " + json.dumps(resultado["metrics"], ensure_ascii=False)
            fila["error_message"] = mensaje[:2000]
        filas.append(fila)
    return {
        "project": PROYECTO,
        "run": {
            "name": "Diana - Account y Budget - " + fecha.strftime("%Y-%m-%d %H:%M:%S"),
            "external_id": "fase3-diana-" + huella[:20],
            "environment": "PHP 8.5 CLI; PHPUnit y ParaTest; SQLite en memoria; Windows 11",
            "build": "Firefly III 6.6.6",
            "branch": git("branch", "--show-current"),
            "commit_sha": git("rev-parse", "HEAD"),
        },
        "results": filas,
        "create_missing_cases": False,
        "create_bug_on_failure": False,
    }


class Cliente:
    def __init__(self, clave: str):
        self.clave = clave

    def pedir(self, metodo: str, ruta: str, cuerpo=None):
        datos = json.dumps(cuerpo, ensure_ascii=False).encode("utf8") if cuerpo is not None else None
        req = urllib.request.Request(BASE + ruta, data=datos, method=metodo, headers={
            "Authorization": "Bearer " + self.clave, "Accept": "application/json", "Content-Type": "application/json"
        })
        for intento in range(3):
            try:
                with urllib.request.urlopen(req, timeout=35) as respuesta:
                    return json.load(respuesta)
            except urllib.error.HTTPError as e:
                if metodo == 'GET' and e.code in {429,502,503,504} and intento<2:
                    time.sleep(intento+1)
                    continue
                raise ValueError(f"QAtlas respondio HTTP {e.code} en {metodo} {ruta}.") from None
            except (urllib.error.URLError, http.client.HTTPException, OSError):
                if metodo == 'GET' and intento<2:
                    time.sleep(intento+1)
                    continue
                raise ValueError("No se pudo completar la conexion con QAtlas. El envio local queda guardado para reintentar.") from None


def vincular(cliente, envio: dict, resultados: dict) -> None:
    usuario = cliente.pedir("GET", "/auth/me")
    if usuario.get("id") != USUARIO_DIANA:
        raise ValueError("El token no corresponde a la cuenta de Diana; no se registrara la corrida.")
    print("Cuenta comprobada: " + str(usuario.get("name", "Diana")))
    seleccion = cliente.pedir("GET", "/test-cases/automation-ids?project=" + PROYECTO)
    por_codigo = {}
    for caso in seleccion:
        match = re.match(r"(TC-[FUP]-\d+)\b", caso.get("title", ""))
        if match and match.group(1) in resultados:
            codigo = match.group(1)
            if codigo in por_codigo:
                raise ValueError(f"{codigo}: hay mas de un caso de QAtlas con ese codigo del plan.")
            por_codigo[codigo] = caso["code"]
    for fila in envio["results"]:
        codigo = fila["test_case_code"]
        if not resultados[codigo]["complete"]:
            raise ValueError(f"{codigo}: corrida parcial; ejecuta el caso completo antes de registrarlo.")
        if codigo not in por_codigo:
            raise ValueError(f"{codigo}: no se encontro su caso automatizado en el proyecto FF.")
        detalle = cliente.pedir("GET", "/test-cases/by-code/" + por_codigo[codigo])
        pasos = sorted(detalle["steps"], key=lambda x: x["order"])
        if [p["order"] for p in pasos] != list(range(1, len(pasos)+1)):
            raise ValueError(f"{codigo}: orden de pasos ambiguo en QAtlas.")
        locales = resultados[codigo]["steps"]
        if set(locales) - {p["order"] for p in pasos}:
            raise ValueError(f"{codigo}: las verificaciones no coinciden con los pasos de QAtlas.")
        fila["steps"] = []
        for paso in pasos:
            pruebas = locales.get(paso["order"], [])
            estado = combinar([p["status"] for p in pruebas])
            registro = {"title": paso["action"], "status": estado}
            errores = [p["message"] for p in pruebas if p["message"]]
            if errores:
                registro["error_message"] = " | ".join(errores)[:2000]
            fila["steps"].append(registro)
        if any(p["status"] == "omitido" for p in fila["steps"]) and fila["status"] == "aprobado":
            fila["status"] = "bloqueado"
            fila["error_message"] = "Falta evidencia para uno o mas pasos del caso de QAtlas."
        fila["test_case_code"] = por_codigo[codigo]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--junit", type=Path, default=RAIZ / "qa/resultados/diana/junit.xml")
    parser.add_argument("--credenciales", type=Path)
    parser.add_argument("--rendimiento", type=Path, nargs='*', default=[], help="resultado.json de corridas formales de TC-P-03 o TC-P-07")
    parser.add_argument("--solo-rendimiento", action="store_true")
    modo = parser.add_mutually_exclusive_group()
    modo.add_argument("--comprobar", action="store_true", help="consulta los casos sin modificar QAtlas")
    modo.add_argument("--subir", action="store_true", help="registra los resultados en QAtlas")
    args = parser.parse_args()
    manifiesto = json.loads(MANIFIESTO.read_text(encoding="utf8"))
    resultados = {} if args.solo_rendimiento else leer_resultados(args.junit, manifiesto)
    for archivo in args.rendimiento:
        datos = json.loads(archivo.read_text(encoding='utf8'))
        codigo = datos.get('case')
        if codigo not in {'TC-P-03', 'TC-P-07'} or not datos.get('formal') or not datos.get('complete'):
            raise ValueError('Solo se registran mediciones formales completas de TC-P-03 o TC-P-07.')
        if datos.get('status') not in {'aprobado','fallido'}:
            raise ValueError('El estado del resultado de rendimiento no es valido.')
        datos['steps'] = {int(k): v for k,v in datos['steps'].items()}
        resultados[codigo] = datos
    if not resultados:
        raise ValueError('No hay resultados para registrar.')
    origen = args.rendimiento[0] if args.solo_rendimiento else args.junit
    envio = construir_envio(origen, resultados, args.rendimiento)
    if args.rendimiento:
        envio['run']['environment'] = ('JMeter 5.6.3; Java 17; Firefly 6.6.6; MariaDB; Docker; Windows 11'
            if args.solo_rendimiento else 'PHPUnit y Apache JMeter; consultar archivos de evidencia de cada caso')
    cliente = None
    if args.subir or args.comprobar:
        cliente = Cliente(leer_clave(args.credenciales))
        vincular(cliente, envio, resultados)
    destino = RAIZ / "qa/resultados/diana/qatlas" / envio["run"]["external_id"]
    destino.mkdir(parents=True, exist_ok=True)
    (destino / "envio.json").write_text(json.dumps(envio, ensure_ascii=False, indent=2), encoding="utf8")
    (destino / "resultados_observados.json").write_text(json.dumps(resultados, ensure_ascii=False, indent=2), encoding="utf8")
    if not args.solo_rendimiento:
        (destino / "junit.xml").write_bytes(args.junit.read_bytes())
    for archivo in args.rendimiento:
        codigo = json.loads(archivo.read_text(encoding='utf8'))['case']
        (destino / (codigo + '.json')).write_bytes(archivo.read_bytes())
    archivos_fuente = set()
    for patron in ['tests/qa/Diana/*.php','qa/*diana.ps1','qa/qatlas/*.py',
            'qa/qatlas/diana_casos.json','qa/qatlas/observaciones_diana.json',
            'qa/rendimiento/diana/*.jmx','qa/rendimiento/diana/*.py',
            'qa/rendimiento/diana/*.php','qa/rendimiento/diana/docker-compose.yml',
            'phpunit.qa.xml','composer.lock']:
        archivos_fuente.update(RAIZ.glob(patron))
    fuentes = {str(p.relative_to(RAIZ)): hashlib.sha256(p.read_bytes()).hexdigest()
               for p in sorted(archivos_fuente)}
    (destino / "fuentes.json").write_text(json.dumps(fuentes, indent=2), encoding="utf8")
    for fila in envio["results"]:
        print(f"{fila['test_case_code']}: {fila['status']}")
    print(f"Casos incluidos: {len(envio['results'])}. Solo resultados presentes en la evidencia indicada.")
    if not args.subir:
        print("Simulacion guardada. QAtlas no fue modificado.")
        return 0
    respuesta = cliente.pedir("POST", "/test-runs/ingest", envio)
    (destino / "respuesta.json").write_text(json.dumps(respuesta, ensure_ascii=False, indent=2), encoding="utf8")
    if respuesta["summary"]["unmatched"]:
        raise ValueError("QAtlas devolvio casos sin vincular. Revisa la respuesta guardada.")
    codigo_run = respuesta["test_run"]["code"]
    confirmacion = cliente.pedir("GET", "/test-runs/by-code/" + codigo_run)
    (destino / "confirmacion.json").write_text(json.dumps(confirmacion, ensure_ascii=False, indent=2), encoding="utf8")
    if confirmacion.get("created_by_id") != USUARIO_DIANA:
        raise ValueError("La corrida no quedo atribuida a Diana. Revisa la confirmacion guardada.")
    print("Corrida registrada y consultada: " + codigo_run)
    print(json.dumps(respuesta["summary"], ensure_ascii=False))
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except (ValueError, OSError, ET.ParseError) as error:
        print("Error: " + str(error), file=sys.stderr)
        sys.exit(1)
