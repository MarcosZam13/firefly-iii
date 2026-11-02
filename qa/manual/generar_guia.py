"""Genera qa/manual/guia.md: qué hacer, en orden, para ejecutar a mano cada caso de Marcos.

Los pasos y resultados esperados salen de los casos cargados en QAtlas (cargar_casos_qatlas.py
del vault), así la guía no puede contradecir al caso. El orden de las peticiones sale de
generar_coleccion.py.

Uso: python qa/manual/generar_guia.py RUTA/A/cargar_casos_qatlas.py
"""

import json
import re
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).parent))
import generar_coleccion as gc  # noqa: E402

AQUI = Path(__file__).parent

INTRO = """# Ejecución manual de los casos de Marcos (Fase 3)

Guía generada con `qa/manual/generar_guia.py`. Los resultados esperados son los de QAtlas (versión 3).

**Esto no lo puede hacer la IA ni un script.** El profesor pidió que cada integrante ejecute sus casos a mano. La colección trae verificaciones de ayuda, pero el resultado lo decide quien mira la respuesta y la compara con el caso. Si ves algo distinto a lo que dice esta guía, se registra lo que viste.

## Antes de empezar (una sola vez)

1. Instalar Postman e importar los dos archivos de `qa/manual/postman/`. Elegir el entorno **Firefly local (QA)** arriba a la derecha.
2. En Postman, abrir **Settings** y apagar **Automatically follow redirects**. La colección ya lo pide petición por petición, pero así se evitan sorpresas.
3. Levantar Firefly y crear la ejecución en QAtlas:

```bash
sh qa/manual/qa.sh arrancar
python qa/manual/manual_qatlas.py crear
```

## Cómo se ejecuta cada caso

1. `sh qa/manual/qa.sh preparar TC-F-XX` borra y crea los datos del caso. Copiá cada valor que muestra a la variable del entorno con el mismo nombre (`correo`, `clave`, `secreto_totp`, etc.).
2. Abrí la carpeta del caso en Postman y enviá las peticiones **una por una, en orden**. Las que dicen `Preparar` no son pasos del caso: solo dejan lista la sesión o leen el token CSRF.
3. Después de cada paso, mirá el código, el cuerpo y la pestaña **Test Results**, y compará con el resultado esperado.
4. Sacá la captura y guardala como `qa/manual/capturas/TC-F-XX/paso-N.png`. Tiene que verse la petición y la respuesta.
5. Marcá el paso, en QAtlas o con el comando:

```bash
python qa/manual/manual_qatlas.py marcar TC-F-XX N aprobado "HTTP 204 sin cuerpo"
```

Al terminar todos los casos:

```bash
python qa/manual/manual_qatlas.py capturas
python qa/manual/manual_qatlas.py estado
python qa/manual/manual_qatlas.py cerrar
```

## Por qué hay peticiones de más

- **Token CSRF:** el servidor real responde 500 a un POST sin token, y PHPUnit lo desactiva. Laravel además lo cambia al iniciar sesión, por eso se vuelve a leer después del login.
- **Mensajes de sesión:** una respuesta 302 no muestra el mensaje. Se ve en la página siguiente, y por eso algunos pasos tienen una segunda petición.
- **TOTP:** la colección calcula el código con `secreto_totp`, como una app de autenticación. Queda en la variable `totp`.

## Lo que ya se sabe (ensayo del 2026-11-01)

La colección se ensayó completa con Newman contra el Firefly real para comprobar que la guía funciona. Ese ensayo **no es evidencia** ni se subió a QAtlas. Dos pasos no dieron lo esperado, y son los defectos que ya están registrados:

- **TC-F-04, paso 3:** responde HTTP 500 en vez de 429, tras esperar hasta 60 s (FF-BUG-1).
- **TC-F-19, paso 3:** responde HTTP 401 en vez de 404 (FF-BUG-2).

Si al ejecutar se repiten, el paso va como `fallido` y se indica el defecto.

**Ojo con TC-P-06, paso 4.** Tres ensayos del plan el 2026-11-01, con Firefly ya caliente, dieron 571, 637 y 617 ms en los últimos 10 s. El límite es 500 ms y la medición del 2026-10-21 dio 424 ms. Otras sesiones estaban usando la compu durante esos ensayos, así que no prueba una regresión. Medilo con la compu libre y registrá lo que salga, aunque falle.
"""

UNITARIAS = """
Se ejecuta por comando porque prueba un método interno, sin pantalla ni API. Queda pendiente confirmar con el profesor si esta forma le sirve.

```bash
MSYS_NO_PATHCONV=1 docker run --rm -v "$(pwd -W):/app" -v firefly-qa-vendor:/app/vendor firefly-qa:php8.5 \\
  vendor/bin/phpunit -c phpunit.qa.xml --filter {filtro} --testdox
```

Captura: la terminal con el comando y el resultado, en `qa/manual/capturas/{caso}/`.
"""

RENDIMIENTO = """
Se ejecuta con JMeter en modo sin interfaz. **Antes de medir:** cerrá lo que esté consumiendo la compu y corré una vez el plan sin registrar el resultado, para que Firefly no arranque en frío. El ensayo en frío y con la compu ocupada dio tiempos varias veces peores.

```bash
sh qa/manual/qa.sh arrancar
cd qa/rendimiento
"$HOME/tools/apache-jmeter-5.6.3/bin/jmeter.bat" -n -t jmeter/{plan} -l resultados/{jtl}
python analizar.py resultados/{jtl} {ventanas}
```

Captura: la terminal con el resumen de `analizar.py`, en `qa/manual/capturas/{caso}/`.
"""


def casos_de_marcos(ruta: Path) -> list[dict]:
    fuente = ruta.read_text(encoding="utf-8")
    casos = json.loads(re.search(r"CASOS = json.loads\(r'''(.*?)'''\)", fuente, re.S).group(1))
    return [c for c in casos if c["dueno"] == "M"]


def seccion_tcf(caso: dict) -> list[str]:
    codigo = caso["codigo"]
    lineas = [f"```bash\nsh qa/manual/qa.sh preparar {codigo}\n```", "", "| Paso | Acción | Resultado esperado |", "|---|---|---|"]
    for p in caso["steps"]:
        lineas.append(f"| {p['order']} | {p['action'].replace('|', '/')} | {p['expected_result'].replace('|', '/')} |")
    lineas += ["", "**Orden en Postman:**", ""]
    numero = 1
    tramos = gc.partes(codigo)
    for n, (tramo, corte) in enumerate(tramos, 1):
        if len(tramos) > 1 and tramo:
            lineas.append(f"Carpeta *{codigo} parte {n}*:")
            lineas.append("")
        for peticion in tramo:
            lineas.append(f"{numero}. {peticion['name']}")
            numero += 1
        if corte:
            lineas.append("")
            pasos = f" (paso {', '.join(map(str, corte.pasos))})" if corte.pasos else ""
            lineas.append(f"**Fuera de Postman{pasos}:** {corte.texto}")
            if corte.comando:
                lineas += ["", f"```bash\n{corte.comando}\n```"]
            lineas.append("")
    return lineas


def generar(ruta_casos: Path) -> None:
    casos = casos_de_marcos(ruta_casos)
    salida = [INTRO]
    salida.append("## Casos\n")
    for caso in casos:
        codigo = caso["codigo"]
        salida.append(f"### {caso['title']}\n")
        if codigo in gc.CASOS:
            salida += seccion_tcf(caso)
        else:
            salida += ["| Paso | Acción | Resultado esperado |", "|---|---|---|"]
            salida += [f"| {p['order']} | {p['action'].replace('|', '/')} | {p['expected_result'].replace('|', '/')} |" for p in caso["steps"]]
            if codigo.startswith("TC-U"):
                salida.append(UNITARIAS.format(caso=codigo, filtro=Path(caso["automation_id"]).stem.removesuffix("Test")))
            elif codigo == "TC-P-01":
                salida.append(RENDIMIENTO.format(caso=codigo, plan="login-carga.jmx", jtl="manual-tc-p-01.jtl", ventanas=""))
            else:
                salida.append(RENDIMIENTO.format(caso=codigo, plan="login-spike.jmx", jtl="manual-tc-p-06.jtl", ventanas="0 10") +
                              "\nPara el paso 4, repetir `analizar.py` con la ventana `20 30`.\n")
        salida.append("")
    (AQUI / "guia.md").write_text("\n".join(salida), encoding="utf-8", newline="\n")
    print(f"guia.md: {len(casos)} casos")


if __name__ == "__main__":
    generar(Path(sys.argv[1]))
