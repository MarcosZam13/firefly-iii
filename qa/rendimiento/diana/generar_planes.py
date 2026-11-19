"""Genera los dos planes nativos de JMeter de la parte de Diana."""
from pathlib import Path
import xml.etree.ElementTree as E

def prop(parent, name, value, kind='stringProp'):
    node=E.SubElement(parent,kind,{'name':name});node.text=str(value)
    return node

def plan(codigo, hilos, duracion, rampa, nombre):
    root=E.Element('jmeterTestPlan',version='1.2',properties='5.0',jmeter='5.6.3')
    tree=E.SubElement(root,'hashTree')
    test=E.SubElement(tree,'TestPlan',guiclass='TestPlanGui',testclass='TestPlan',testname=codigo+' Consulta de presupuesto')
    prop(test,'TestPlan.functional_mode','false','boolProp')
    prop(test,'TestPlan.serialize_threadgroups','false','boolProp')
    args=E.SubElement(test,'elementProp',name='TestPlan.user_defined_variables',elementType='Arguments')
    E.SubElement(args,'collectionProp',name='Arguments.arguments')
    body=E.SubElement(tree,'hashTree')
    setup=E.SubElement(body,'SetupThreadGroup',guiclass='SetupThreadGroupGui',testclass='SetupThreadGroup',testname='Leer usuarios de prueba')
    prop(setup,'ThreadGroup.num_threads','1');prop(setup,'ThreadGroup.ramp_time','0')
    controller=E.SubElement(setup,'elementProp',name='ThreadGroup.main_controller',elementType='LoopController',guiclass='LoopControlPanel',testclass='LoopController')
    prop(controller,'LoopController.continue_forever','false','boolProp')
    prop(controller,'LoopController.loops','1','intProp')
    setup_tree=E.SubElement(body,'hashTree')
    sampler=E.SubElement(setup_tree,'JSR223Sampler',guiclass='TestBeanGUI',testclass='JSR223Sampler',testname='Preparar usuarios')
    prop(sampler,'scriptLanguage','groovy');prop(sampler,'cacheKey','true')
    prop(sampler,'script', '''def file = new File(props.getProperty('usuarios', 'usuarios.csv'))
def lines = file.readLines('UTF-8').findAll { it.trim() }
if (lines[0].replace('\\uFEFF','') != 'token,budget_id,limit_id') throw new IllegalArgumentException('Cabecera CSV incorrecta')
def rows = lines.drop(1).collect { it.split(',', -1) }
int threads = Integer.parseInt(props.getProperty('hilos', '%d'))
if (rows.size() < threads) throw new IllegalArgumentException('Faltan usuarios distintos para la carga')
props.put('qaPresupuestos', rows)
''' % hilos)
    E.SubElement(setup_tree,'hashTree')
    group=E.SubElement(body,'ThreadGroup',guiclass='ThreadGroupGui',testclass='ThreadGroup',testname=f'{hilos} usuarios durante {duracion} segundos')
    prop(group,'ThreadGroup.on_sample_error','continue')
    ctrl=E.SubElement(group,'elementProp',name='ThreadGroup.main_controller',elementType='LoopController')
    prop(ctrl,'LoopController.continue_forever','false','boolProp');prop(ctrl,'LoopController.loops','-1','intProp')
    prop(group,'ThreadGroup.num_threads',f'${{__P(hilos,{hilos})}}')
    prop(group,'ThreadGroup.ramp_time',f'${{__P(rampa,{rampa})}}')
    prop(group,'ThreadGroup.scheduler','true','boolProp')
    prop(group,'ThreadGroup.duration',f'${{__P(duracion,{duracion})}}')
    prop(group,'ThreadGroup.delay','0')
    subtree=E.SubElement(body,'hashTree')
    headers=E.SubElement(subtree,'HeaderManager',guiclass='HeaderPanel',testclass='HeaderManager',testname='Autenticacion y respuesta JSON')
    col=E.SubElement(headers,'collectionProp',name='HeaderManager.headers')
    for key,value in [('Authorization','Bearer ${TOKEN}'),('Accept','application/json')]:
        head=E.SubElement(col,'elementProp',name='',elementType='Header')
        prop(head,'Header.name',key);prop(head,'Header.value',value)
    E.SubElement(subtree,'hashTree')
    pre=E.SubElement(subtree,'JSR223PreProcessor',guiclass='TestBeanGUI',testclass='JSR223PreProcessor',testname='Un usuario fijo por hilo')
    prop(pre,'scriptLanguage','groovy');prop(pre,'cacheKey','true')
    prop(pre,'script', '''def row = props.get('qaPresupuestos')[ctx.getThreadNum()]
vars.put('TOKEN', row[0]); vars.put('BUDGET_ID', row[1]); vars.put('LIMIT_ID', row[2])''')
    E.SubElement(subtree,'hashTree')
    http=E.SubElement(subtree,'HTTPSamplerProxy',guiclass='HttpTestSampleGui',testclass='HTTPSamplerProxy',testname='GET presupuesto')
    for key,value in [('domain','${__P(host,localhost)}'),('port','${__P(puerto,8081)}'),('protocol','http'),
                      ('path','/api/v1/budgets/${BUDGET_ID}/limits/${LIMIT_ID}'),('method','GET'),
                      ('connect_timeout','5000'),('response_timeout','30000')]:prop(http,'HTTPSampler.'+key,value)
    prop(http,'HTTPSampler.follow_redirects','false','boolProp');prop(http,'HTTPSampler.use_keepalive','true','boolProp')
    arguments=E.SubElement(http,'elementProp',name='HTTPsampler.Arguments',elementType='Arguments')
    E.SubElement(arguments,'collectionProp',name='Arguments.arguments')
    checks=E.SubElement(subtree,'hashTree')
    assertion=E.SubElement(checks,'JSR223Assertion',guiclass='TestBeanGUI',testclass='JSR223Assertion',testname='HTTP 200 y limite del usuario')
    prop(assertion,'scriptLanguage','groovy');prop(assertion,'cacheKey','true')
    prop(assertion,'script', '''try {
  def json = new groovy.json.JsonSlurper().parseText(prev.getResponseDataAsString())
  boolean ok = prev.getResponseCode() == '200' && json.data.id.toString() == vars.get('LIMIT_ID') && json.data.attributes.budget_id.toString() == vars.get('BUDGET_ID') && json.data.attributes.amount != null && json.data.attributes.spent instanceof List
  if (!ok) { AssertionResult.setFailure(true); AssertionResult.setFailureMessage('Respuesta o presupuesto incorrectos') }
} catch (Exception e) { AssertionResult.setFailure(true); AssertionResult.setFailureMessage('Respuesta JSON invalida') }''')
    E.SubElement(checks,'hashTree')
    E.indent(root,space='  ')
    destination=Path(__file__).with_name(nombre)
    E.ElementTree(root).write(destination,encoding='utf-8',xml_declaration=True)

if __name__=='__main__':
    plan('TC-P-03',50,120,15,'presupuesto-carga.jmx')
    plan('TC-P-07',30,1800,0,'presupuesto-soak.jmx')
