"""Genera los .jmx de TC-P-02, 04, 05 y 08 (Kevin) en esta carpeta, con la misma base
(token de API por CSV, cabeceras JSON). Para cambiar un plan, editar aquí y volver a correr:
  python3 qa/rendimiento/jmeter/generar_planes.py
"""
from pathlib import Path
from xml.sax.saxutils import escape

DEST = Path(__file__).parent


def var(nombre, valor):
    return f'''          <elementProp name="{nombre}" elementType="Argument">
            <stringProp name="Argument.name">{nombre}</stringProp>
            <stringProp name="Argument.value">{escape(valor)}</stringProp>
          </elementProp>'''


def plan(nombre, comentario, variables, cuerpo):
    vs = "\n".join(var(k, v) for k, v in [("HOST", "${__P(host,localhost)}"), ("PUERTO", "${__P(puerto,8080)}")] + variables)
    return f'''<?xml version="1.0" encoding="UTF-8"?>
<!--
{comentario}
-->
<jmeterTestPlan version="1.2" properties="5.0" jmeter="5.6.3">
  <hashTree>
    <TestPlan guiclass="TestPlanGui" testclass="TestPlan" testname="{escape(nombre)}">
      <boolProp name="TestPlan.functional_mode">false</boolProp>
      <boolProp name="TestPlan.serialize_threadgroups">false</boolProp>
      <elementProp name="TestPlan.user_defined_variables" elementType="Arguments" guiclass="ArgumentsPanel" testclass="Arguments">
        <collectionProp name="Arguments.arguments">
{vs}
        </collectionProp>
      </elementProp>
    </TestPlan>
    <hashTree>
      <ConfigTestElement guiclass="HttpDefaultsGui" testclass="ConfigTestElement" testname="Firefly III">
        <stringProp name="HTTPSampler.domain">${{HOST}}</stringProp>
        <stringProp name="HTTPSampler.port">${{PUERTO}}</stringProp>
        <stringProp name="HTTPSampler.protocol">http</stringProp>
        <elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments">
          <collectionProp name="Arguments.arguments"/>
        </elementProp>
      </ConfigTestElement>
      <hashTree/>
{cuerpo}
    </hashTree>
  </hashTree>
</jmeterTestPlan>
'''


CSV_TOKENS = '''      <CSVDataSet guiclass="TestBeanGUI" testclass="CSVDataSet" testname="Usuarios (resultados/tokens.csv)">
        <stringProp name="filename">${__P(tokens,../resultados/tokens.csv)}</stringProp>
        <stringProp name="fileEncoding">UTF-8</stringProp>
        <stringProp name="variableNames">CORREO,TOKEN,CUENTA</stringProp>
        <boolProp name="ignoreFirstLine">false</boolProp>
        <stringProp name="delimiter">,</stringProp>
        <boolProp name="quotedData">false</boolProp>
        <boolProp name="recycle">true</boolProp>
        <boolProp name="stopThread">false</boolProp>
        <stringProp name="shareMode">shareMode.all</stringProp>
      </CSVDataSet>
      <hashTree/>'''

CABECERAS = '''      <HeaderManager guiclass="HeaderPanel" testclass="HeaderManager" testname="Token de API y JSON">
        <collectionProp name="HeaderManager.headers">
          <elementProp name="" elementType="Header">
            <stringProp name="Header.name">Authorization</stringProp>
            <stringProp name="Header.value">Bearer ${TOKEN}</stringProp>
          </elementProp>
          <elementProp name="" elementType="Header">
            <stringProp name="Header.name">Accept</stringProp>
            <stringProp name="Header.value">application/json</stringProp>
          </elementProp>
          <elementProp name="" elementType="Header">
            <stringProp name="Header.name">Content-Type</stringProp>
            <stringProp name="Header.value">application/json</stringProp>
          </elementProp>
        </collectionProp>
      </HeaderManager>
      <hashTree/>'''


def grupo(nombre, hilos, rampa, duracion=None, retraso="0", vueltas="-1", tipo="ThreadGroup", gui="ThreadGroupGui"):
    programado = duracion is not None
    return f'''      <{tipo} guiclass="{gui}" testclass="{tipo}" testname="{escape(nombre)}">
        <stringProp name="ThreadGroup.on_sample_error">continue</stringProp>
        <elementProp name="ThreadGroup.main_controller" elementType="LoopController" guiclass="LoopControlPanel" testclass="LoopController">
          <boolProp name="LoopController.continue_forever">false</boolProp>
          <stringProp name="LoopController.loops">{vueltas}</stringProp>
        </elementProp>
        <stringProp name="ThreadGroup.num_threads">{hilos}</stringProp>
        <stringProp name="ThreadGroup.ramp_time">{rampa}</stringProp>
        <boolProp name="ThreadGroup.scheduler">{"true" if programado else "false"}</boolProp>
        <stringProp name="ThreadGroup.duration">{duracion or ""}</stringProp>
        <stringProp name="ThreadGroup.delay">{retraso}</stringProp>
        <boolProp name="ThreadGroup.same_user_on_next_iteration">true</boolProp>
      </{tipo}>'''


def codigo(esperado):
    return f'''        <ResponseAssertion guiclass="AssertionGui" testclass="ResponseAssertion" testname="Código {esperado}">
          <collectionProp name="Asserion.test_strings">
            <stringProp name="c{esperado}">{esperado}</stringProp>
          </collectionProp>
          <stringProp name="Assertion.test_field">Assertion.response_code</stringProp>
          <boolProp name="Assertion.assume_success">false</boolProp>
          <intProp name="Assertion.test_type">8</intProp>
        </ResponseAssertion>
        <hashTree/>'''


def muestreador(etiqueta, metodo, ruta, cuerpo_json=None, hijos=""):
    if cuerpo_json is None:
        args = '<collectionProp name="Arguments.arguments"/>'
        raw = ""
    else:
        raw = '\n        <boolProp name="HTTPSampler.postBodyRaw">true</boolProp>'
        args = f'''<collectionProp name="Arguments.arguments">
              <elementProp name="" elementType="HTTPArgument">
                <boolProp name="HTTPArgument.always_encode">false</boolProp>
                <stringProp name="Argument.value">{escape(cuerpo_json)}</stringProp>
                <stringProp name="Argument.metadata">=</stringProp>
              </elementProp>
            </collectionProp>'''
    return f'''        <HTTPSamplerProxy guiclass="HttpTestSampleGui" testclass="HTTPSamplerProxy" testname="{escape(etiqueta)}">
          <stringProp name="HTTPSampler.path">{escape(ruta)}</stringProp>
          <stringProp name="HTTPSampler.method">{metodo}</stringProp>
          <boolProp name="HTTPSampler.follow_redirects">false</boolProp>
          <boolProp name="HTTPSampler.use_keepalive">true</boolProp>{raw}
          <elementProp name="HTTPsampler.Arguments" elementType="Arguments" guiclass="HTTPArgumentsPanel" testclass="Arguments">
            {args}
          </elementProp>
        </HTTPSamplerProxy>
        <hashTree>
{hijos}
        </hashTree>'''


def gasto(etiqueta, cuenta="${CUENTA}", monto="1.00"):
    cuerpo = ('{"error_if_duplicate_hash": false, "apply_rules": false, "fire_webhooks": false, "transactions": [{'
              '"type": "withdrawal", "date": "2026-11-01", "amount": "' + monto + '", '
              '"description": "QA ' + etiqueta.split()[0] + ' ${__threadNum}-${__counter(FALSE)}", '
              '"source_id": "' + cuenta + '", "destination_name": "Comercio QA rendimiento"}]}')
    return cuerpo


def jsr223(tipo, nombre, script):
    gui = {"JSR223PostProcessor": "TestBeanGUI", "JSR223Assertion": "TestBeanGUI"}[tipo]
    return f'''        <{tipo} guiclass="{gui}" testclass="{tipo}" testname="{escape(nombre)}">
          <stringProp name="scriptLanguage">groovy</stringProp>
          <stringProp name="parameters"></stringProp>
          <stringProp name="filename"></stringProp>
          <stringProp name="cacheKey">true</stringProp>
          <stringProp name="script">{escape(script)}</stringProp>
        </{tipo}>
        <hashTree/>'''


USO = "  Uso (desde qa/rendimiento): sh correr-jmeter.sh {tc}"

# ---------- TC-P-02 ----------
tc02 = plan("TC-P-02 Carga normal en registrar transacción", f"""  TC-P-02 (FF-TC-52) Carga normal en registrar transacción.
  50 hilos, rampa de 20 s, 120 s de duración. Cada iteración: POST /api/v1/transactions
  (gasto de 1.00 desde la cuenta "QA Perf Transacciones" del usuario) con token personal
  de API, aserción de código 200. Usuarios y tokens: resultados/tokens.csv
  (preparar-transacciones.sh).
  Criterio: promedio de POST /api/v1/transactions <= 1000 ms y error < 1 %.
{USO.format(tc="tc-p-02")}""", [], f"""{CSV_TOKENS}
{CABECERAS}
{grupo("50 usuarios, rampa 20 s, 120 s", "${__P(hilos,50)}", "${__P(rampa,20)}", "${__P(duracion,120)}")}
      <hashTree>
{muestreador("POST /api/v1/transactions", "POST", "/api/v1/transactions", gasto("TC-P-02 gasto"), codigo(200))}
      </hashTree>""")

# ---------- TC-P-04 ----------
tc04 = plan("TC-P-04 Carga en listado paginado de transacciones", f"""  TC-P-04 (FF-TC-53) Carga en listado paginado de transacciones.
  50 hilos, rampa de 20 s, 120 s de duración. Cada iteración: GET
  /api/v1/transactions?limit=50&page=N (N al azar entre 1 y 3; cada usuario tiene 120 gastos
  de historial sembrados) con token personal de API, aserción de código 200 y de que la
  respuesta trae la paginación.
  Criterio: promedio de GET /api/v1/transactions <= 1000 ms y error < 1 %.
{USO.format(tc="tc-p-04")}""", [], f"""{CSV_TOKENS}
{CABECERAS}
{grupo("50 usuarios, rampa 20 s, 120 s", "${__P(hilos,50)}", "${__P(rampa,20)}", "${__P(duracion,120)}")}
      <hashTree>
{muestreador("GET /api/v1/transactions (página)", "GET", "/api/v1/transactions?limit=50&page=${__Random(1,3)}", None, codigo(200) + '''
        <ResponseAssertion guiclass="AssertionGui" testclass="ResponseAssertion" testname="Trae paginación">
          <collectionProp name="Asserion.test_strings">
            <stringProp name="pag">"pagination"</stringProp>
          </collectionProp>
          <stringProp name="Assertion.test_field">Assertion.response_data</stringProp>
          <boolProp name="Assertion.assume_success">false</boolProp>
          <intProp name="Assertion.test_type">2</intProp>
        </ResponseAssertion>
        <hashTree/>''')}
      </hashTree>""")

# ---------- TC-P-05 ----------
ESCALONES = [25, 50, 100, 150, 200]
PASO_S = 30
total = PASO_S * len(ESCALONES)
grupos = []
previo = 0
for k, nivel in enumerate(ESCALONES):
    nuevos = nivel - previo
    previo = nivel
    inicio = k * PASO_S
    grupos.append(f"""{grupo(f"Escalón {k + 1}: +{nuevos} hilos desde el segundo {inicio} (total {nivel})", str(nuevos), "5", str(total - inicio), str(inicio))}
      <hashTree>
{muestreador("POST /api/v1/transactions", "POST", "/api/v1/transactions", gasto("TC-P-05 gasto"), codigo(200))}
      </hashTree>""")
tc05 = plan("TC-P-05 Estrés escalonado en registrar transacción", f"""  TC-P-05 (FF-TC-54) Estrés escalonado en registrar transacción.
  Carga que sube por escalones de {PASO_S} s: {" -> ".join(map(str, ESCALONES))} hilos ({total} s en total).
  Cada escalón es un grupo de hilos que arranca {PASO_S} s después del anterior y sigue
  hasta el final, así la carga se acumula. Cada iteración: POST /api/v1/transactions
  (gasto de 1.00) con token personal de API, aserción de código 200.
  Se analiza por ventana de {PASO_S} s (analizar.py con ventana) para ubicar el escalón
  donde el promedio pasa de 2000 ms o el error llega a 1 % (punto de quiebre), y se
  comprueba que no haya errores 5xx ni transacciones perdidas.
{USO.format(tc="tc-p-05")}""", [], f"""{CSV_TOKENS}
{CABECERAS}
""" + "\n".join(grupos))

# ---------- TC-P-08 ----------
verificar = """import java.math.BigDecimal
def inicial = new BigDecimal(props.get('saldo_inicial') ?: 'NaN')
def exitos = (props.get('exitos') ?: new java.util.concurrent.atomic.AtomicInteger()).get()
def esperadas = Integer.parseInt(vars.get('ESPERADAS'))
def finalSaldo = new BigDecimal(new groovy.json.JsonSlurper().parseText(prev.getResponseDataAsString()).data.attributes.current_balance)
def esperado = inicial.subtract(new BigDecimal(exitos))
log.info("TC-P-08 saldo inicial ${inicial}, gastos aceptados ${exitos} de ${esperadas}, saldo final ${finalSaldo}, esperado ${esperado}")
if (exitos != esperadas) {
    AssertionResult.setFailure(true)
    AssertionResult.setFailureMessage("Solo ${exitos} de ${esperadas} gastos fueron aceptados")
} else if (finalSaldo.compareTo(esperado) != 0) {
    AssertionResult.setFailure(true)
    AssertionResult.setFailureMessage("Saldo final ${finalSaldo}, esperado ${esperado} (inicial ${inicial} - ${exitos} x 1.00)")
}"""
contar = """if (prev.getResponseCode() == '200') {
    props.putIfAbsent('exitos', new java.util.concurrent.atomic.AtomicInteger())
    props.get('exitos').incrementAndGet()
}"""
leer_inicial = """props.put('saldo_inicial', new groovy.json.JsonSlurper().parseText(prev.getResponseDataAsString()).data.attributes.current_balance)
props.put('exitos', new java.util.concurrent.atomic.AtomicInteger())
log.info('TC-P-08 saldo inicial ' + props.get('saldo_inicial'))"""
consulta = "/api/v1/accounts/${CUENTA_C}"
tc08 = plan("TC-P-08 Transacciones concurrentes sobre la misma cuenta", f"""  TC-P-08 (FF-TC-55) Transacciones concurrentes sobre la misma cuenta.
  Un mismo usuario (qa-perf-001) y una misma cuenta ("QA Perf Concurrencia"):
  1. setUp: GET /api/v1/accounts/{{id}} guarda el saldo inicial.
  2. 50 hilos x 10 gastos de 1.00 en simultáneo (Synchronizing Timer libera los 50 juntos
     en cada vuelta): POST /api/v1/transactions, aserción de código 200.
  3. tearDown: GET /api/v1/accounts/{{id}} y aserción de que el saldo final es exactamente
     saldo inicial - (gastos aceptados x 1.00) y de que se aceptaron los 500.
  Token y cuenta: resultados/concurrencia.properties (preparar-transacciones.sh).
  Criterio: 0 % de error, saldo final exacto (sin actualizaciones perdidas ni duplicadas).
{USO.format(tc="tc-p-08")}""", [
    ("TOKEN", "${__P(token,SIN_TOKEN)}"), ("CUENTA_C", "${__P(cuenta,0)}"),
    ("ESPERADAS", "${__jexl3(${__P(hilos,50)} * ${__P(vueltas,10)})}"),
], f"""{CABECERAS}
{grupo("setUp: saldo inicial", "1", "0", vueltas="1", tipo="SetupThreadGroup", gui="SetupThreadGroupGui")}
      <hashTree>
{muestreador("GET cuenta (saldo inicial)", "GET", consulta, None, codigo(200) + chr(10) + jsr223("JSR223PostProcessor", "Guardar saldo inicial", leer_inicial))}
      </hashTree>
{grupo("50 hilos x 10 gastos sobre la misma cuenta", "${__P(hilos,50)}", "1", vueltas="${__P(vueltas,10)}")}
      <hashTree>
        <SyncTimer guiclass="TestBeanGUI" testclass="SyncTimer" testname="Liberar los hilos juntos">
          <stringProp name="groupSize">${{__P(hilos,50)}}</stringProp>
          <stringProp name="timeoutInMs">10000</stringProp>
        </SyncTimer>
        <hashTree/>
{muestreador("POST /api/v1/transactions (misma cuenta)", "POST", "/api/v1/transactions", gasto("TC-P-08 gasto", "${CUENTA_C}"), codigo(200) + chr(10) + jsr223("JSR223PostProcessor", "Contar gastos aceptados", contar))}
      </hashTree>
{grupo("tearDown: verificar saldo final", "1", "0", vueltas="1", tipo="PostThreadGroup", gui="PostThreadGroupGui")}
      <hashTree>
{muestreador("GET cuenta (saldo final)", "GET", consulta, None, codigo(200) + chr(10) + jsr223("JSR223Assertion", "Saldo final = inicial - aceptados", verificar))}
      </hashTree>""")

for nombre, xml in [("tc-p-02-registrar-carga.jmx", tc02), ("tc-p-04-listado-paginado.jmx", tc04),
                    ("tc-p-05-registrar-estres.jmx", tc05), ("tc-p-08-concurrencia-misma-cuenta.jmx", tc08)]:
    (DEST / nombre).write_text(xml, encoding="utf-8")
    print(nombre)
