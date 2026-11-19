"""Adjunta comentarios y evidencia a una corrida propia de Diana sin modificar sus resultados."""
import argparse
import csv
import hashlib
import io
import json
from pathlib import Path
import re
import sys
import urllib.parse
import urllib.request
import uuid

from subir_diana import Cliente, leer_clave, USUARIO_DIANA, RAIZ


def notas_caso(caso, observaciones, evidencia):
    match = re.match(r'(TC-[FUP]-\d+)\b', caso['test_case_title'])
    if not match or match.group(1) not in observaciones:
        raise ValueError('La corrida contiene un caso ajeno al alcance automatico de Diana.')
    codigo = match.group(1)
    fuente = observaciones[codigo]
    r = evidencia[codigo]
    if caso['status'] != r['status']:
        raise ValueError(f'{codigo}: el estado publicado no coincide con la evidencia.')
    if r['status'] == 'aprobado':
        resultado = fuente.get('resultado', 'Los criterios de la medicion se cumplieron.')
    else:
        resultado = r.get('error_message') or 'El caso no cumplio sus criterios.'
    partes = [f"{codigo} | Estado ejecutado: {r['status']}.",
        f"Resultado observado: {resultado}", f"Observaciones: {fuente['observacion']}",
        f"Evidencia: {r['checks']} verificaciones; duracion acumulada de verificaciones {r['duration_ms']} ms."]
    if 'metrics' in r:
        partes[-1] = 'Metricas medidas: ' + json.dumps(r['metrics'], ensure_ascii=False)
        partes.append('Criterio aplicado: ' + r['criteria'])
    else:
        partes.append('Entorno: PHP 8.5.12, PHPUnit 13.2.2, ParaTest 7.23.0; SQLite en memoria. No es una prueba visual del navegador.')
    return codigo, '\n'.join(partes)


def subir_archivo(cliente, entidad, archivo):
    huella = hashlib.sha256(archivo.read_bytes()).hexdigest()[:12]
    nombre = archivo.stem + '-' + huella + archivo.suffix
    ruta = f'/attachments?entity_type=test_run&entity_id={entidad}'
    existentes = cliente.pedir('GET', ruta)
    if any(x['filename'] == nombre and x['size_bytes'] == archivo.stat().st_size for x in existentes):
        return next(x for x in existentes if x['filename'] == nombre)
    frontera = 'qatlas-' + uuid.uuid4().hex
    partes = []
    for k,v in [('entity_type','test_run'),('entity_id',str(entidad))]:
        partes.append(f'--{frontera}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
    tipos = {'.md':'text/markdown', '.csv':'text/csv', '.json':'application/json', '.xml':'application/xml',
        '.docx':'application/vnd.openxmlformats-officedocument.wordprocessingml.document'}
    partes.append((f'--{frontera}\r\nContent-Disposition: form-data; name="file"; filename="{nombre}"\r\n'
        f'Content-Type: {tipos.get(archivo.suffix,"application/octet-stream")}\r\n\r\n').encode())
    partes += [archivo.read_bytes(), f'\r\n--{frontera}--\r\n'.encode()]
    req = urllib.request.Request(cliente_base()+'/attachments', data=b''.join(partes), method='POST', headers={
        'Authorization':'Bearer '+cliente.clave, 'Accept':'application/json',
        'Content-Type':'multipart/form-data; boundary='+frontera})
    # Sin reintento ciego de una escritura: al reejecutar se comprueba primero su existencia.
    with urllib.request.urlopen(req, timeout=60) as respuesta:
        r = json.load(respuesta)
    if r['uploaded_by_id'] != USUARIO_DIANA or r['entity_id'] != entidad:
        raise ValueError('La atribucion del adjunto no coincide con la corrida de Diana.')
    return r


def cliente_base():
    from subir_diana import BASE
    return BASE


def comentario_breve(codigo, fuente, resultado):
    texto = f"{codigo}: {resultado['status']}. "
    if 'metrics' in resultado:
        m=resultado['metrics']
        texto += f"{m['usuarios']} usuarios; {m['duracion_observada_s']:.1f} s; promedio {m['promedio_ms']:.2f} ms; errores {m['error_porcentaje']:.2f} %. "
        if codigo=='TC-P-07':
            texto += f"Cambio RSS: {m['crecimiento_rss_kib']} KiB. "
    else:
        texto += fuente['observacion']
    if len(texto)>205: texto=texto[:202].rsplit(' ',1)[0]+'...'
    return texto+' Ver detalle adjunto.'


def main():
    p = argparse.ArgumentParser(description=__doc__)
    p.add_argument('--carpeta', type=Path, required=True, help='carpeta de evidencia de la corrida publicada')
    p.add_argument('--subir', action='store_true')
    p.add_argument('--adjunto', type=Path, action='append', default=[])
    a = p.parse_args()
    carpeta = a.carpeta.resolve()
    evidencia = json.loads((carpeta/'resultados_observados.json').read_text(encoding='utf8'))
    original = json.loads((carpeta/'confirmacion.json').read_text(encoding='utf8'))
    cliente = Cliente(leer_clave())
    if cliente.pedir('GET','/auth/me')['id'] != USUARIO_DIANA:
        raise ValueError('La cuenta no corresponde a Diana.')
    corrida = cliente.pedir('GET','/test-runs/by-code/'+original['code'])
    if corrida['created_by_id'] != USUARIO_DIANA:
        raise ValueError('La corrida no pertenece a Diana.')
    fuentes = json.loads(Path(__file__).with_name('observaciones_diana.json').read_text(encoding='utf8'))
    notas = [(*notas_caso(c, fuentes, evidencia), c['id']) for c in corrida['cases']]
    if len(notas) != len(evidencia):
        raise ValueError('La corrida y la evidencia no contienen los mismos casos.')
    resumen = '# Comentarios y observaciones de la ejecucion\n\n'
    resumen += f"Responsable: Diana Carolina Camacho Jimenez. Corrida: {corrida['code']}.\n\n"
    resumen += ('Se ejecutaron los casos automatizados de cuentas y presupuestos. Los estados proceden de la evidencia real; '
        'las pruebas manuales TC-F-49 y TC-F-58 no se ejecutaron en este bloque.\n\n')
    for codigo,nota,_ in notas:
        resumen += '## '+codigo+'\n\n'+nota+'\n\n'
    resumen += ('## Observaciones generales\n\n'
        'La cobertura unitaria previamente medida en las tres clases completas es 49/401 lineas (12.22 %); '
        'no alcanza el 80 %. El resultado funcional no demuestra cobertura global ni sustituye pruebas visuales. '
        'Los resultados de rendimiento pertenecen a un equipo local compartido y requieren confirmacion en un entorno controlado. '
        'No se modifico el producto para conseguir resultados aprobados.\n')
    archivo = carpeta/'Comentarios_y_observaciones_Diana.md'
    archivo.write_text(resumen, encoding='utf8')
    with (carpeta/'Resultados_Diana.csv').open('w',encoding='utf-8-sig',newline='') as f:
        w=csv.writer(f);w.writerow(['Caso','Estado','Verificaciones','Duracion_ms','Comentarios y observaciones'])
        for codigo,nota,_ in notas:
            r=evidencia[codigo];w.writerow([codigo,r['status'],r['checks'],r['duration_ms'],nota])
    if not a.subir:
        print('Observaciones locales creadas; QAtlas no fue modificado.');return
    comentarios_por_caso = {}
    # QAtlas admite comentarios breves de test_run. El servidor devuelve
    # HTTP 500 con las notas extensas; el detalle completo va como adjunto.
    existentes=cliente.pedir('GET',f"/comments?entity_type=test_run&entity_id={corrida['id']}")
    for codigo,nota,identificador in notas:
        breve=comentario_breve(codigo,fuentes[codigo],evidencia[codigo])
        comentario=next((c for c in existentes if c['body']==breve),None)
        if comentario is None:
            comentario=cliente.pedir('POST','/comments',{'entity_type':'test_run','entity_id':corrida['id'],'body':breve})
        if comentario['author_id'] != USUARIO_DIANA:
            raise ValueError('El comentario no quedo atribuido a Diana.')
        comentarios_por_caso[codigo] = comentario
        print(codigo+': comentario y observaciones guardados')
    cuerpo = (f"Ejecucion {corrida['code']} de Diana: {len(notas)} casos automatizados. "
        'Se adjuntan resultados, comentarios por caso y evidencia. Los dos casos manuales quedan pendientes. '
        'Detalle y criterios en los documentos adjuntos.')
    comentarios=cliente.pedir('GET',f"/comments?entity_type=test_run&entity_id={corrida['id']}")
    if not any(c['body']==cuerpo for c in comentarios):
        cliente.pedir('POST','/comments',{'entity_type':'test_run','entity_id':corrida['id'],'body':cuerpo})
    archivos = [archivo, carpeta/'Resultados_Diana.csv', *a.adjunto]
    adjuntos = [subir_archivo(cliente,corrida['id'],f) for f in archivos]
    final=cliente.pedir('GET','/test-runs/by-code/'+corrida['code'])
    confirmados=cliente.pedir('GET',f"/comments?entity_type=test_run&entity_id={corrida['id']}")
    for codigo,nota,identificador in notas:
        actualizado=next(c for c in final['cases'] if c['id']==identificador)
        anterior=next(c for c in corrida['cases'] if c['id']==identificador)
        for campo in ['status','step_results','duration_ms','executed_via','executed_at','executed_by_id']:
            if actualizado.get(campo) != anterior.get(campo):
                raise ValueError('Un resultado de ejecucion cambio mientras se adjuntaban las observaciones.')
        breve=comentario_breve(codigo,fuentes[codigo],evidencia[codigo])
        if not any(c['body']==breve and c['author_id']==USUARIO_DIANA for c in confirmados):
            raise ValueError('No se encontro el comentario en la comprobacion final.')
    (carpeta/'observaciones_confirmadas.json').write_text(json.dumps({'run':final,'comments':comentarios_por_caso,'attachments':adjuntos},ensure_ascii=False,indent=2),encoding='utf8')
    print(f"{corrida['code']}: {len(notas)} comentarios y {len(adjuntos)} adjuntos comprobados.")


if __name__=='__main__':
    try:main()
    except (ValueError,OSError,KeyError) as e:raise SystemExit('Error: '+str(e))
