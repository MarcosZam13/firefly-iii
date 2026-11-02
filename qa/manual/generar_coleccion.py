"""Genera la colección y el entorno de Postman para ejecutar a mano los casos de Marcos.

Salida en qa/manual/postman/. Uso: python qa/manual/generar_coleccion.py

Cada carpeta de la colección es un caso. Un caso se divide en partes cuando entre dos
peticiones hay que hacer algo fuera de Postman (revisar la base o generar un token):
ese corte queda escrito en la guía y qa/manual/ensayar.py lo respeta.

Por qué las peticiones llevan más que en PHPUnit: el servidor real exige el token CSRF
en cada POST web (PHPUnit lo desactiva) y lo cambia al iniciar sesión. Por eso hay
peticiones "Preparar" que solo leen ese token. El script de la colección lo guarda en
la variable csrf cada vez que una respuesta lo trae.
"""

import json
import uuid
from pathlib import Path

AQUI = Path(__file__).parent
SALIDA = AQUI / "postman"
BASE = "{{base_url}}"
CREDENCIALES = "These credentials do not match our records."
GRACIAS = "Thank you. If an account exists with this email address, you will find instructions in your inbox."
TOKEN_INVALIDO = "This password reset token is invalid."
MFA_INVALIDO = "This MFA code is not valid."
AVISO_RESPALDO = "You have used a backup code to login to Firefly III. It can't be used again, so cross it from your list."
CLAVE_MALA = "Clave-Incorrecta-2026!"

# Corre antes de cada petición: calcula el TOTP vigente desde {{secreto_totp}}, igual que una app de autenticación.
PRE_COLECCION = r"""
const secreto = pm.environment.get('secreto_totp');
if (secreto) {
  const alfabeto = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const c of secreto) { bits += alfabeto.indexOf(c).toString(2).padStart(5, '0'); }
  let hex = '';
  for (let i = 0; i + 8 <= bits.length; i += 8) { hex += parseInt(bits.substr(i, 8), 2).toString(16).padStart(2, '0'); }
  const ventana = Math.floor(Date.now() / 1000 / 30).toString(16).padStart(16, '0');
  const firma = CryptoJS.HmacSHA1(CryptoJS.enc.Hex.parse(ventana), CryptoJS.enc.Hex.parse(hex)).toString();
  const corte = parseInt(firma.slice(-1), 16) * 2;
  const numero = (parseInt(firma.substr(corte, 8), 16) & 0x7fffffff) % 1000000;
  pm.environment.set('totp', String(numero).padStart(6, '0'));
}
""".strip().splitlines()

# Corre después de cada petición: guarda el token CSRF si la respuesta lo trae.
POST_COLECCION = r"""
const texto = pm.response.text();
const campo = texto.match(/name="_token"[^>]*value="([^"]+)"/) || texto.match(/<meta name="csrf-token" content="([^"]+)"/);
if (campo) { pm.environment.set('csrf', campo[1]); }
""".strip().splitlines()


def js(texto: str) -> str:
    return json.dumps(texto, ensure_ascii=False)


def codigo(paso: int, esperado: int) -> str:
    return f"pm.test({js(f'Paso {paso}: HTTP {esperado}')}, () => pm.response.to.have.status({esperado}));"


def error_campo(paso: int, campo: str, texto: str | None = None) -> str:
    if texto:
        return (f"pm.test({js(f'Paso {paso}: errors.{campo} dice: {texto}')}, () => "
                f"pm.expect(pm.response.json().errors.{campo}).to.include({js(texto)}));")
    return f"pm.test({js(f'Paso {paso}: hay un error en {campo}')}, () => pm.expect(pm.response.json().errors).to.have.property({js(campo)}));"


def contiene(paso: int, texto: str) -> str:
    return f"pm.test({js(f'Paso {paso}: la página muestra: {texto}')}, () => pm.expect(pm.response.text()).to.include({js(texto)}));"


def ubicacion(paso: int, ruta: str) -> str:
    return (f"pm.test({js(f'Paso {paso}: Location termina en {ruta}')}, () => "
            f"pm.expect(pm.response.headers.get('Location')).to.match(new RegExp({js(ruta + '$')})));")


def peticion(nombre: str, metodo: str, ruta: str, campos: dict | None = None, pruebas: list[str] | None = None,
             seguir: bool = False, html: bool = False, bearer: str | None = None, paso: int | None = None,
             extra: list[str] | None = None) -> dict:
    cabeceras = [{"key": "Accept", "value": "text/html" if html else "application/json"}]
    if bearer:
        cabeceras.append({"key": "Authorization", "value": f"Bearer {{{{{bearer}}}}}"})
    item = {
        "name": nombre,
        "request": {"method": metodo, "header": cabeceras, "url": f"{BASE}{ruta}"},
        "protocolProfileBehavior": {"followRedirects": seguir},
        "event": [{"listen": "test", "script": {"type": "text/javascript", "exec": (extra or []) + (pruebas or [])}}],
    }
    if campos is not None:
        item["request"]["body"] = {"mode": "urlencoded", "urlencoded": [
            {"key": k, "value": v} for k, v in {"_token": "{{csrf}}", **campos}.items()]}
    item["_paso"] = paso
    return item


def leer_csrf(ruta: str = "/login") -> dict:
    return peticion(f"Preparar: GET {ruta} (lee el token CSRF)", "GET", ruta, seguir=True, html=True)


def login(paso: int | None, esperado: int = 204, clave: str = "{{clave}}", correo: str = "{{correo}}", pruebas: list[str] | None = None) -> dict:
    nombre = f"Paso {paso}: POST /login" if paso else "Preparar: POST /login"
    base = [codigo(paso, esperado)] if paso else [f"pm.test('Sesión iniciada: HTTP {esperado}', () => pm.response.to.have.status({esperado}));"]
    return peticion(nombre, "POST", "/login", {"email": correo, "password": clave}, base + (pruebas or []), paso=paso)


def salir() -> list[dict]:
    return [leer_csrf("/profile"),
            peticion("Preparar: POST /logout", "POST", "/logout", {}, ["pm.test('Sesión cerrada: HTTP 204', () => pm.response.to.have.status(204));"]),
            leer_csrf("/login")]


def registro(paso: int, correo: str, clave: str, pruebas: list[str]) -> dict:
    return peticion(f"Paso {paso}: POST /register", "POST", "/register",
                    {"email": correo, "password": clave, "password_confirmation": clave}, pruebas, paso=paso)


def sesion_con_2fa() -> list[dict]:
    return [leer_csrf(), login(None), leer_csrf("/home"),
            peticion("Preparar: POST /two-factor/submit", "POST", "/two-factor/submit", {"one_time_password": "{{totp}}"},
                     ["pm.test('2FA completo: HTTP 302', () => pm.response.to.have.status(302));"]),
            leer_csrf("/profile")]


class Corte:
    """Algo que se hace fuera de Postman entre dos partes del caso."""

    def __init__(self, texto: str, comando: str | None = None, pasos: list[int] | None = None, espera: int = 0, variable: str | None = None):
        self.texto, self.comando, self.pasos, self.espera, self.variable = texto, comando, pasos or [], espera, variable


def ver(caso: str, texto: str, pasos: list[int], dato: str | None = None) -> Corte:
    return Corte(texto, f"sh qa/manual/qa.sh ver {caso}" + (f" {{{{{dato}}}}}" if dato else ""), pasos)


CASOS: dict[str, list] = {}

CASOS["TC-F-01"] = [
    leer_csrf(),
    login(1, pruebas=["pm.test('Paso 1: sin cuerpo', () => pm.expect(pm.response.text()).to.eql(''));"]),
    peticion("Paso 2: GET /profile con la misma sesión", "GET", "/profile", pruebas=[
        codigo(2, 200), contiene(2, "qa-login@firefly.test")], html=True, paso=2),
]
CASOS["TC-F-02"] = [
    leer_csrf(),
    login(1, 422, CLAVE_MALA, pruebas=[error_campo(2, "email", CREDENCIALES)]),
    peticion("Paso 3: GET /profile con la misma sesión", "GET", "/profile", pruebas=[codigo(3, 401)], paso=3),
]
CASOS["TC-F-03"] = [
    leer_csrf(),
    login(1, 422, "Clave-Cualquiera-2026", "noexiste@firefly.test", [error_campo(2, "email", CREDENCIALES)]),
]
CASOS["TC-F-04"] = [
    leer_csrf(),
    login(1, 422, CLAVE_MALA, pruebas=[error_campo(1, "email", CREDENCIALES)]),
    login(2, 422, CLAVE_MALA, pruebas=[error_campo(2, "email", CREDENCIALES)]),
    login(3, 429),
    login(4, 204),
]
CASOS["TC-F-05"] = [
    leer_csrf("/register"),
    registro(1, "qa-dup@firefly.test", "{{clave}}", [codigo(1, 422), error_campo(1, "email")]),
    ver("TC-F-05", "Contar las filas con ese correo: tiene que dar 1.", [2]),
]
CASOS["TC-F-06"] = [
    leer_csrf("/register"),
    registro(1, "qa-15@firefly.test", "QaFirefly-2026!", [codigo(1, 422), error_campo(1, "password")]),
    ver("TC-F-06", "qa-15@firefly.test tiene que dar 0 filas.", [2]),
    registro(3, "qa-16@firefly.test", "QaFirefly-2026!!", [codigo(3, 302)]),
    ver("TC-F-06", "qa-16@firefly.test tiene que dar 1 fila.", [3]),
]
CASOS["TC-F-07"] = [
    leer_csrf("/register"),
    registro(1, "qatestfirefly.test", "{{clave}}", [codigo(1, 422), error_campo(1, "email")]),
    ver("TC-F-07", "El correo mal formado tiene que dar 0 filas.", [2]),
]
CASOS["TC-F-08"] = [
    leer_csrf("/register"),
    registro(1, "qa-nuevo@firefly.test", "{{clave}}", [codigo(1, 302)]),
    ver("TC-F-08", "qa-nuevo@firefly.test tiene que dar 1 fila.", [1]),
    leer_csrf("/profile"),
    peticion("Paso 2: POST /logout", "POST", "/logout", {}, [codigo(2, 204)], paso=2),
    leer_csrf(),
    login(3, 204, correo="qa-nuevo@firefly.test"),
]
CASOS["TC-F-09"] = [
    leer_csrf("/password/reset"),
    peticion("Paso 1: POST /password/email", "POST", "/password/email", {"email": "{{correo}}"}, [codigo(1, 302)], paso=1),
    peticion("Paso 2: GET /password/reset (mensaje de la sesión)", "GET", "/password/reset", pruebas=[contiene(2, GRACIAS)], html=True, paso=2),
    ver("TC-F-09", "Tiene que haber 1 token para qa-reset@firefly.test.", [3]),
]
CASOS["TC-F-10"] = [
    leer_csrf("/password/reset"),
    peticion("Paso 1: POST /password/email", "POST", "/password/email", {"email": "noexiste@firefly.test"}, [codigo(1, 302)], paso=1),
    peticion("Paso 2: GET /password/reset (mensaje de la sesión)", "GET", "/password/reset", pruebas=[contiene(2, GRACIAS)], html=True, paso=2),
    ver("TC-F-10", "Tiene que haber 0 tokens para noexiste@firefly.test.", [3]),
]


def reseteo(paso: int, token: str, clave: str, pruebas: list[str]) -> dict:
    return peticion(f"Paso {paso}: POST /password/reset", "POST", "/password/reset",
                    {"token": f"{{{{{token}}}}}", "email": "{{correo}}", "password": clave, "password_confirmation": clave}, pruebas, paso=paso)


CASOS["TC-F-11"] = [
    leer_csrf("/login"),
    reseteo(1, "token_reseteo", "Primera-Clave-P1-2026", [codigo(1, 200)]),
    *salir(),
    reseteo(2, "token_reseteo", "Segunda-Clave-P2-2026", [codigo(2, 422), error_campo(2, "email", TOKEN_INVALIDO)]),
    Corte("Generar T2 con created_at de hace 61 minutos. Copiar token_vencido a la variable del entorno.",
          "sh qa/manual/qa.sh token-vencido", [3], variable="token_vencido"),
    reseteo(4, "token_vencido", "Segunda-Clave-P2-2026", [codigo(4, 422), error_campo(4, "email", TOKEN_INVALIDO)]),
    login(5, 422, "Segunda-Clave-P2-2026"),
]
CASOS["TC-F-12"] = [
    leer_csrf(), login(None), leer_csrf("/profile"),
    peticion("Paso 1: POST /logout", "POST", "/logout", {}, [codigo(1, 204)], paso=1),
    peticion("Paso 2: GET /", "GET", "/", pruebas=[codigo(2, 401)], paso=2),
]
CASOS["TC-F-13"] = [
    peticion("Paso 1: GET /api/v1/accounts con el token vencido", "GET", "/api/v1/accounts", bearer="token_vencido", paso=1, pruebas=[
        codigo(1, 401),
        "pm.test('Paso 2: trae message', () => pm.expect(pm.response.json()).to.have.property('message'));",
        "pm.test('Paso 2: no trae data', () => pm.expect(pm.response.json()).to.not.have.property('data'));",
    ]),
]
CASOS["TC-F-14"] = [
    leer_csrf(), login(1), leer_csrf("/home"),
    peticion("Paso 2: POST /two-factor/submit con el código vigente", "POST", "/two-factor/submit", {"one_time_password": "{{totp}}"},
             [codigo(2, 302), ubicacion(2, "/home")], paso=2),
    ver("TC-F-14", "mfa_history contiene el código enviado y mfa_failure_count vale 0.", [3]),
]
CASOS["TC-F-15"] = [
    leer_csrf(), login(1), leer_csrf("/home"),
    peticion("Paso 2: POST /two-factor/submit con 000000", "POST", "/two-factor/submit", {"one_time_password": "000000"}, [codigo(2, 302)], paso=2),
    peticion("Paso 2: GET /home (mensaje de la sesión)", "GET", "/home", pruebas=[contiene(2, MFA_INVALIDO)], seguir=True, html=True, paso=2),
    ver("TC-F-15", "mfa_failure_count tiene que valer 1.", [2]),
    peticion("Paso 3: POST /two-factor/submit con el código vigente C", "POST", "/two-factor/submit", {"one_time_password": "{{totp}}"},
             [codigo(3, 302)], paso=3, extra=["pm.environment.set('codigo_c', pm.environment.get('totp'));"]),
    ver("TC-F-15", "mfa_history contiene C y mfa_failure_count vuelve a 0.", [3]),
    leer_csrf("/profile"),
    peticion("Paso 4: POST /logout", "POST", "/logout", {}, [codigo(4, 204)], paso=4),
    leer_csrf(), login(4), leer_csrf("/home"),
    peticion("Paso 5: POST /two-factor/submit con el mismo C", "POST", "/two-factor/submit", {"one_time_password": "{{codigo_c}}"}, [codigo(5, 302)], paso=5),
    peticion("Paso 5: GET /home (mensaje de la sesión)", "GET", "/home", pruebas=[contiene(5, MFA_INVALIDO)], seguir=True, html=True, paso=5),
]
CASOS["TC-F-16"] = [
    *sesion_con_2fa(),
    peticion("Paso 1: POST /mfa/disableMFA solo con code", "POST", "/mfa/disableMFA", {"code": "{{totp}}"}, [codigo(1, 422), error_campo(1, "password")], paso=1),
    peticion("Paso 2: POST /mfa/disableMFA solo con password", "POST", "/mfa/disableMFA", {"password": "{{clave}}"}, [codigo(2, 422), error_campo(2, "code")], paso=2),
    Corte("Revisar que 2FA sigue activo (users.mfa_secret con valor). Esperar 31 s para que el código cambie: el paso 4 pide un código nuevo.",
          "sh qa/manual/qa.sh ver TC-F-16", [3], espera=31),
    peticion("Paso 4: POST /mfa/disableMFA con password y código nuevo", "POST", "/mfa/disableMFA", {"password": "{{clave}}", "code": "{{totp}}"}, [codigo(4, 302)], paso=4),
    ver("TC-F-16", "users.mfa_secret tiene que quedar en NULL.", [4]),
]
CASOS["TC-F-17"] = [
    leer_csrf(), login(None), leer_csrf("/profile"),
    peticion("Paso 1: POST /profile/delete-account sin password", "POST", "/profile/delete-account", {}, [codigo(1, 422), error_campo(1, "password")], paso=1),
    peticion("Paso 2: POST /profile/delete-account con password incorrecta", "POST", "/profile/delete-account", {"password": CLAVE_MALA},
             [codigo(2, 302), ubicacion(2, "/profile/delete-account")], paso=2),
    peticion("Paso 2: GET /profile/delete-account (mensaje de la sesión)", "GET", "/profile/delete-account", pruebas=[contiene(2, "Invalid password!")], html=True, paso=2),
    ver("TC-F-17", "qa-borrar@firefly.test tiene que seguir existiendo: 1 fila.", [3]),
]
CASOS["TC-F-18"] = [
    leer_csrf(), login(None), leer_csrf("/profile"),
    peticion("Paso 1: POST /profile/delete-account con la password correcta", "POST", "/profile/delete-account", {"password": "{{clave}}"}, [codigo(1, 302)], paso=1),
    ver("TC-F-18", "qa-eliminado@firefly.test tiene que dar 0 filas.", [1]),
    leer_csrf(),
    login(2, 422, pruebas=[error_campo(2, "email", CREDENCIALES)]),
]
CASOS["TC-F-19"] = [
    leer_csrf(), login(None), leer_csrf("/profile"),
    peticion("Paso 1: POST /profile/delete-account de A", "POST", "/profile/delete-account", {"password": "{{clave}}"}, [codigo(1, 302)], paso=1),
    ver("TC-F-19", "A tiene que dar 0 filas en users y 0 filas en accounts.", [1, 2], "id_usuario_a"),
    peticion("Paso 3: GET /api/v1/accounts/{id de A} con el token de B", "GET", "/api/v1/accounts/{{id_cuenta_a}}", bearer="token_b", pruebas=[codigo(3, 404)], paso=3),
]
CASOS["TC-F-20"] = [
    leer_csrf(), login(None),
    peticion("Paso 1: GET /mfa/enableMFA", "GET", "/mfa/enableMFA", pruebas=[codigo(1, 200)], html=True, paso=1, extra=[
        "const s = pm.response.text().match(/\\b[A-Z2-7]{32}\\b/); if (s) { pm.environment.set('secreto_totp', s[0]); }"]),
    ver("TC-F-20", "temp-mfa-secret: 32 caracteres, todos de A-Z y 2-7.", [2]),
    peticion("Paso 3: POST /mfa/enableMFA con password y código", "POST", "/mfa/enableMFA", {"password": "{{clave}}", "code": "{{totp}}"},
             [codigo(3, 302), ubicacion(3, "/mfa/backup-codes")], paso=3),
    ver("TC-F-20", "users.mfa_secret tiene que ser igual al secreto del paso 2.", [3]),
]
CASOS["TC-F-59"] = [
    leer_csrf(), login(1), leer_csrf("/home"),
    peticion("Paso 2: POST /two-factor/submit con el código de respaldo R", "POST", "/two-factor/submit", {"one_time_password": "{{codigo_respaldo}}"}, [codigo(2, 302)], paso=2),
    peticion("Paso 2: GET /profile (aviso de la sesión)", "GET", "/profile", pruebas=[contiene(2, AVISO_RESPALDO)], seguir=True, html=True, paso=2),
    ver("TC-F-59", "mfa_recovery tiene 7 códigos y R ya no está.", [3], "codigo_respaldo"),
    leer_csrf("/profile"),
    peticion("Paso 4: POST /logout", "POST", "/logout", {}, [codigo(4, 204)], paso=4),
    leer_csrf(), login(4), leer_csrf("/home"),
    peticion("Paso 5: POST /two-factor/submit con el mismo R", "POST", "/two-factor/submit", {"one_time_password": "{{codigo_respaldo}}"}, [codigo(5, 302)], paso=5),
    peticion("Paso 5: GET /home (mensaje de la sesión)", "GET", "/home", pruebas=[contiene(5, MFA_INVALIDO)], seguir=True, html=True, paso=5),
]


def partes(caso: str) -> list[tuple[list[dict], Corte | None]]:
    """Divide el caso en tramos de peticiones, cada uno con el corte que le sigue."""
    tramos, actual = [], []
    for elemento in CASOS[caso]:
        if isinstance(elemento, Corte):
            tramos.append((actual, elemento))
            actual = []
        else:
            actual.append(elemento)
    if actual:
        tramos.append((actual, None))
    return tramos


def limpiar(item: dict) -> dict:
    return {k: v for k, v in item.items() if not k.startswith("_")}


def generar() -> None:
    SALIDA.mkdir(exist_ok=True)
    carpetas = []
    for caso in CASOS:
        tramos = partes(caso)
        if len(tramos) == 1:
            carpetas.append({"name": caso, "item": [limpiar(i) for i in tramos[0][0]]})
            continue
        subcarpetas = [{"name": f"{caso} parte {n}", "item": [limpiar(i) for i in tramo]}
                       for n, (tramo, _) in enumerate(tramos, 1) if tramo]
        carpetas.append({"name": caso, "item": subcarpetas})
    coleccion = {
        "info": {
            "name": "Firefly III - ejecución manual Fase 3 (Marcos Zamora)",
            "_postman_id": str(uuid.uuid5(uuid.NAMESPACE_URL, "firefly-qa-manual-coleccion")),
            "description": "Casos TC-F de Marcos. Antes de cada carpeta: sh qa/manual/qa.sh preparar CASO. Ver qa/manual/guia.md.",
            "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        },
        "event": [
            {"listen": "prerequest", "script": {"type": "text/javascript", "exec": PRE_COLECCION}},
            {"listen": "test", "script": {"type": "text/javascript", "exec": POST_COLECCION}},
        ],
        "item": carpetas,
    }
    (SALIDA / "firefly-qa.postman_collection.json").write_text(json.dumps(coleccion, ensure_ascii=False, indent=1), encoding="utf-8", newline="\n")
    variables = ["correo", "clave", "csrf", "secreto_totp", "totp", "codigo_c", "codigo_respaldo", "token_reseteo",
                 "token_vencido", "token_b", "id_usuario_a", "id_cuenta_a"]
    entorno = {
        "id": str(uuid.uuid5(uuid.NAMESPACE_URL, "firefly-qa-manual-entorno")),
        "name": "Firefly local (QA)",
        "values": [{"key": "base_url", "value": "http://localhost:8080", "enabled": True}]
        + [{"key": v, "value": "", "enabled": True} for v in variables],
        "_postman_variable_scope": "environment",
    }
    (SALIDA / "firefly-qa.postman_environment.json").write_text(json.dumps(entorno, ensure_ascii=False, indent=1), encoding="utf-8", newline="\n")
    print(f"Generado: {len(CASOS)} casos en {SALIDA}")


if __name__ == "__main__":
    generar()
