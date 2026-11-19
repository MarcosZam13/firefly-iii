"""Crea un panel personal con la foto comprobada de FF-RUN-9 y los dos casos manuales pendientes."""
import json
from pathlib import Path
import sys
from collections import Counter

from subir_diana import Cliente, leer_clave, RAIZ, USUARIO_DIANA


def main():
    c=Cliente(leer_clave())
    if c.pedir('GET','/auth/me')['id']!=USUARIO_DIANA:raise ValueError('La cuenta no es la de Diana.')
    run=c.pedir('GET','/test-runs/by-code/FF-RUN-9')
    if run['created_by_id']!=USUARIO_DIANA or len(run['cases'])!=18:
        raise ValueError('La corrida no corresponde al bloque de Diana.')
    casos=c.pedir('GET','/test-cases?project_id=6&owner_id=7&page%5Bsize%5D=100')['data']
    ids={x['test_case_code']:x['status'] for x in run['cases']}
    if len(casos)!=20 or Counter(ids.values())!={'aprobado':17,'fallido':1}:
        raise ValueError('El alcance actual no coincide con la foto esperada.')
    nombres={x['code'] for x in casos}
    if set(ids)-nombres or nombres-set(ids)!={'FF-TC-62','FF-TC-71'}:
        raise ValueError('Los casos no coinciden con cuentas, presupuestos, unitarias y rendimiento de Diana.')

    carpeta=RAIZ/'qa/resultados/diana/dashboard';carpeta.mkdir(parents=True,exist_ok=True)
    previo={'tabs':c.pedir('GET','/dashboard-tabs?project_id=6'),
        'widgets':c.pedir('GET','/dashboard-widgets?project_id=6'),
        'filters':c.pedir('GET','/saved-filters?project_id=6&entity_type=test_case'),
        'definitions':c.pedir('GET','/custom-fields/definitions?project_id=6&entity_type=test_case')}
    if not (carpeta/'estado_previo.json').exists():
        (carpeta/'estado_previo.json').write_text(json.dumps(previo,ensure_ascii=False,indent=2),encoding='utf8')
    nombre='Diana Fase 3 · FF-RUN-9'
    tab=next((x for x in previo['tabs'] if x['name']==nombre),None)
    if tab is None:tab=c.pedir('POST','/dashboard-tabs',{'project_id':6,'name':nombre})
    definicion=next((x for x in previo['definitions'] if x['name']=='Resultado Diana FF-RUN-9'),None)
    if definicion is None:
        definicion=c.pedir('POST','/custom-fields/definitions',{'project_id':6,'entity_type':'test_case',
            'name':'Resultado Diana FF-RUN-9','field_type':'seleccion_unica',
            'options':['Aprobado','Fallido','Pendiente manual']})
    valores=[]
    for caso in casos:
        valor={'aprobado':'Aprobado','fallido':'Fallido'}.get(ids.get(caso['code']),'Pendiente manual')
        existentes=c.pedir('GET',f"/custom-fields/values?entity_type=test_case&entity_id={caso['id']}")
        if not any(v['definition_id']==definicion['id'] and v['value']==valor for v in existentes):
            c.pedir('PUT','/custom-fields/values',{'entity_type':'test_case','entity_id':caso['id'],
                'definition_id':definicion['id'],'value':valor})
        valores.append({'case':caso['code'],'snapshot':valor})

    filtros={}
    for clave,filtros_obj in [('alcance',{'owner_id':'7'}),('automaticas',{'owner_id':'7','execution_type':'automatizado'})]:
        nombre_filtro='Diana Fase 3 '+('20 casos' if clave=='alcance' else '18 automatizados FF-RUN-9')
        filtro=next((x for x in previo['filters'] if x['name']==nombre_filtro),None)
        if filtro is not None and filtro['filters']!=filtros_obj:raise ValueError('Ya existe un filtro distinto con el mismo nombre.')
        if filtro is None:
            filtro=c.pedir('POST','/saved-filters',{'project_id':6,'entity_type':'test_case','name':nombre_filtro,'filters':filtros_obj})
        filtros[clave]=filtro
    grupo='custom:'+str(definicion['id'])
    configuraciones=[
        ('Resultados automatizados FF-RUN-9','automaticas',grupo,None,'pie',18,{'Aprobado':17,'Fallido':1}),
        ('Alcance Diana · automatizadas y manuales','alcance','execution_type',None,'pie',20,{'automatizado':18,'manual':2}),
        ('Corte 09 nov · resultados y manuales pendientes','alcance',grupo,None,'bar',20,{'Aprobado':17,'Fallido':1,'Pendiente manual':2}),
        ('Casos de Diana por módulo','alcance','parent_folder_id',None,'bar',20,{'Account':9,'Budget':7,'Unitarias':2,'Rendimiento':2}),
        ('Resultado por módulo · foto FF-RUN-9 y pendientes','alcance','parent_folder_id',grupo,'crosstab',20,None)
    ]
    widgets=[]
    existentes=c.pedir('GET',f"/dashboard-widgets?project_id=6&tab_id={tab['id']}")
    for i,(nombre,fil,group,group2,tipo,total,esperado) in enumerate(configuraciones):
        widget=next((x for x in existentes if x['name']==nombre),None)
        if widget is None:
            widget=c.pedir('POST','/dashboard-widgets',{'project_id':6,'entity_type':'test_case','name':nombre,
                'saved_filter_id':filtros[fil]['id'],'tab_id':tab['id'],'group_by_field':group,
                'group_by_field_2':group2,'chart_type':tipo,'color_palette':'categorico'})
        posicion={'x':0 if i==4 else (i%2)*6,'y':14 if i==4 else (i//2)*7,'w':12 if i==4 else 6,'h':6,'auto_height':True}
        c.pedir('PATCH',f"/dashboard-widgets/{widget['id']}",posicion)
        datos=c.pedir('GET',f"/dashboard-widgets/{widget['id']}/data")
        if datos['total']!=total:raise ValueError(f"{nombre}: el filtro no da el total esperado ({datos['total']}).")
        conteos={x['label']:x['count'] for x in datos['items']}
        if esperado is not None and conteos!=esperado:
            raise ValueError(f'{nombre}: las agrupaciones no coinciden: {conteos}')
        widgets.append({'widget':widget,'data':datos})
        print(nombre+': '+json.dumps(datos,ensure_ascii=True),flush=True)
    final={'tab':tab,'definition':definicion,'filters':filtros,'values':valores,'widgets':widgets,
        'url':f"https://geotorotur.tail052473.ts.net/qatlas/p/FF/dashboard?tab={tab['id']}",
        'note':'Foto de FF-RUN-9 del 9 de noviembre de 2026. Los manuales no se ejecutaron. El campo personalizado no cambia resultados de ejecucion.'}
    (carpeta/'dashboard_confirmado.json').write_text(json.dumps(final,ensure_ascii=False,indent=2),encoding='utf8')
    print('Panel creado y datos comprobados: '+final['url'])


if __name__=='__main__':
    try:main()
    except (ValueError,OSError,KeyError) as e:raise SystemExit('Error: '+str(e))
