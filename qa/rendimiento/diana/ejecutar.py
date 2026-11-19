"""Ejecuta y evalua TC-P-03 o TC-P-07. --validar comprueba el plan sin acreditar el caso."""
import argparse
import csv
from datetime import datetime, timezone, timedelta
import json
from pathlib import Path
import statistics
import subprocess
import time

AQUI=Path(__file__).resolve().parent

def evaluar(filas, caso, formal, hilos, duracion, memoria=None):
    filas=[f for f in filas if f['label']=='GET presupuesto']
    if not filas: raise ValueError('No hay muestras de consulta de presupuesto en el JTL.')
    tiempos=[int(f['elapsed']) for f in filas]
    inicio=min(int(f['timeStamp']) for f in filas)
    fin=max(int(f['timeStamp'])+int(f['elapsed']) for f in filas)
    segundos=(fin-inicio)/1000
    usuarios=len({f['threadName'] for f in filas})
    promedio=statistics.mean(tiempos)
    error=sum(f['success'].lower()!='true' for f in filas)/len(filas)
    objetivo_hilos,objetivo_duracion=(50,120) if caso=='TC-P-03' else (30,1800)
    completo=formal and hilos==objetivo_hilos and duracion==objetivo_duracion and usuarios>=objetivo_hilos and segundos>=objetivo_duracion-3
    metricas={'muestras':len(filas),'promedio_ms':promedio,'error_porcentaje':100*error,
              'duracion_observada_s':segundos,'usuarios':usuarios,
              'p90_ms':sorted(tiempos)[max(0,int(len(tiempos)*.9)-1)]}
    if caso=='TC-P-03':
        cumple=promedio<=400 and error<.01
        criterio='Promedio <= 400 ms; error < 1 %; 50 usuarios; 120 s; rampa 15 s.'
    else:
        muestras=[m for m in memoria or [] if m['segundo']>=297 and (m.get('rss_kib') or 0)>0]
        completo=completo and len(muestras)>=6
        inicial=[int(f['elapsed']) for f in filas if int(f['timeStamp'])<inicio+300000]
        final=[int(f['elapsed']) for f in filas if int(f['timeStamp'])>=fin-300000]
        metricas['inicio_promedio_ms']=statistics.mean(inicial) if inicial else None
        metricas['final_promedio_ms']=statistics.mean(final) if final else None
        metricas['muestras_memoria']=muestras
        # El plan no fija tolerancia: se aplica literalmente "no empeora".
        crecimiento=(muestras[-1]['rss_kib']-muestras[0]['rss_kib']) if len(muestras)>=6 else None
        metricas['crecimiento_rss_kib']=crecimiento
        cumple=bool(inicial and final and crecimiento is not None and crecimiento<=0
                    and statistics.mean(final)<=statistics.mean(inicial) and error==0)
        criterio='30 usuarios, 1800 s, 6 lecturas de memoria; RSS final no mayor que la primera y promedio final no mayor que el inicial; sin errores HTTP.'
    estado='aprobado' if completo and cumple else ('fallido' if completo else 'bloqueado')
    return {'case':caso,'status':estado,'complete':bool(completo),'formal':bool(formal),
            'duration_ms':int(segundos*1000),'checks':3,'metrics':metricas,'criteria':criterio,
            'error_message':'' if estado=='aprobado' else ('No cumple el criterio medido.' if completo else 'Ejecucion de validacion o evidencia incompleta; no acredita el caso.'),
            'steps':{'1':[{'status':'aprobado','method':'configuracion','message':''}],
                     '2':[{'status':'aprobado' if completo else 'omitido','method':'ejecucion_y_memoria','message':''}],
                     '3':[{'status':estado,'method':'criterio','message':''}]}}

def leer_memoria(contenedor):
    resultado=subprocess.run(['docker','top',contenedor,'-eo','pid,rss,args'],capture_output=True,text=True,timeout=15)
    if resultado.returncode: return None
    rss=0
    for linea in resultado.stdout.splitlines()[1:]:
        columnas=linea.strip().split(None,2)
        if len(columnas)==3 and columnas[1].isdigit() and 'php-fpm: pool' in columnas[2]:rss+=int(columnas[1])
    return rss or None

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--caso',choices=['TC-P-03','TC-P-07'],required=True)
    parser.add_argument('--jmeter',type=Path,required=True,help='carpeta apache-jmeter-5.6.3')
    parser.add_argument('--java',default='java')
    parser.add_argument('--usuarios',type=Path,default=AQUI/'usuarios.csv')
    parser.add_argument('--host',default='localhost')
    parser.add_argument('--puerto',type=int,default=8081)
    parser.add_argument('--contenedor',help='contenedor app de Firefly para medir PHP-FPM en TC-P-07')
    parser.add_argument('--validar',action='store_true',help='un usuario durante 2 s, nunca registra aprobacion formal')
    args=parser.parse_args()
    with args.usuarios.open(encoding='utf-8-sig',newline='') as f:usuarios=list(csv.DictReader(f))
    hilos,duracion,rampa=(50,120,15) if args.caso=='TC-P-03' else (30,1800,0)
    if args.validar:hilos,duracion,rampa=1,2,0
    if len(usuarios)<hilos or len({u['token'] for u in usuarios})<hilos:
        raise ValueError('Faltan usuarios con tokens distintos para la carga.')
    if any(not u['budget_id'].isdigit() or not u['limit_id'].isdigit() for u in usuarios):
        raise ValueError('Los identificadores de presupuesto y limite deben ser numericos.')
    if args.caso=='TC-P-07' and not args.validar and not args.contenedor:
        raise ValueError('TC-P-07 necesita --contenedor para registrar la memoria de PHP-FPM.')
    stamp=datetime.now(timezone(timedelta(hours=-6))).strftime('%Y%m%d-%H%M%S')
    destino=AQUI/'resultados'/(args.caso.lower()+'-'+stamp);destino.mkdir(parents=True)
    jtl=destino/'muestras.jtl'
    plan=AQUI/('presupuesto-carga.jmx' if args.caso=='TC-P-03' else 'presupuesto-soak.jmx')
    comando=[args.java,'-Xms128m','-Xmx512m','-jar',str(args.jmeter.resolve()/'bin/ApacheJMeter.jar'),
             '-n','-t',str(plan),'-l',str(jtl),'-j',str(destino/'jmeter.log'),
             '-Jusuarios='+str(args.usuarios.resolve()),'-Jhost='+args.host,'-Jpuerto='+str(args.puerto),
             '-Jhilos='+str(hilos),'-Jduracion='+str(duracion),'-Jrampa='+str(rampa),
             '-Jjmeter.save.saveservice.timestamp_format=ms','-Jjmeter.save.saveservice.output_format=csv']
    memoria=[];inicio=time.monotonic();proxima=300
    with (destino/'consola.txt').open('w',encoding='utf8') as log:
        proceso=subprocess.Popen(comando,stdout=log,stderr=subprocess.STDOUT)
        while proceso.poll() is None:
            transcurrido=time.monotonic()-inicio
            if transcurrido > duracion + (30 if args.validar else 90):
                proceso.terminate()
                try:proceso.wait(timeout=10)
                except subprocess.TimeoutExpired:proceso.kill()
                raise ValueError('JMeter excedio el tiempo de la corrida y fue detenido. Revisa sus registros.')
            if args.caso=='TC-P-07' and args.contenedor and transcurrido>=proxima:
                memoria.append({'segundo':int(transcurrido),'rss_kib':leer_memoria(args.contenedor)})
                proxima+=300
                (destino/'memoria.json').write_text(json.dumps(memoria,indent=2),encoding='utf8')
            time.sleep(1)
    if proceso.returncode:raise ValueError('JMeter termino con error. Revisa consola.txt y jmeter.log.')
    with jtl.open(encoding='utf8',newline='') as f:filas=list(csv.DictReader(f))
    resumen=evaluar(filas,args.caso,not args.validar,hilos,duracion,memoria)
    resumen['configuration']={'hilos':hilos,'duracion_s':duracion,'rampa_s':rampa,'host':args.host,'puerto':args.puerto}
    resumen['source_jtl']=str(jtl)
    (destino/'resultado.json').write_text(json.dumps(resumen,ensure_ascii=False,indent=2),encoding='utf8')
    print(json.dumps({'case':resumen['case'],'status':resumen['status'],'metricas':resumen['metrics']},ensure_ascii=False))
    print('Evidencia: '+str(destino))
    if args.validar:
        return 0 if resumen['metrics']['error_porcentaje']==0 else 1
    return 0 if resumen['status']=='aprobado' else 1

if __name__=='__main__':
    try: raise SystemExit(main())
    except (ValueError,OSError,KeyError) as error:raise SystemExit('Error: '+str(error))
