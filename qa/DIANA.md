# Fase 3: cuentas y presupuestos de Diana

Repositorio del equipo: https://github.com/MarcosZam13/firefly-iii.git.
Rama local `fase3-diana`, basada en `origin/fase3`, commit `a58e3d9`.
No se ha hecho commit ni push. Se conserva la base de pruebas del equipo.

## Resultado comprobado: 9 de noviembre de 2026

Hay scripts para los **18 casos automatizados** del alcance de Diana.
Los dos casos manuales se conservan para su ejecucion en la interfaz.

| Casos del plan | Estado | Evidencia |
| --- | --- | --- |
| TC-F-43 a TC-F-48, TC-F-50 a TC-F-57 (14 funcionales) | Aprobados | FF-RUN-9 |
| TC-U-07 y TC-U-08 (2 unitarios) | Aprobados | FF-RUN-9 |
| TC-P-03, carga con 50 usuarios durante 120 s | Aprobado en la nueva corrida | FF-RUN-9 |
| TC-P-07, resistencia con 30 usuarios durante 30 min | Fallido por criterio de tiempos y memoria | FF-RUN-9 |
| TC-F-49 y TC-F-58 | Manuales pendientes | Cancelar eliminacion de cuenta/presupuesto en la interfaz |

La corrida completa funcional/unitaria paso **68 verificaciones y 68
aserciones**, con dos procesos, en 59.375 segundos en la nueva ejecucion.
Esas verificaciones corresponden a 16 casos documentales. FF-RUN-5 es la
primera corrida de cuatro casos; FF-RUN-6 registra la primera ejecucion
de los 16 completos (53.883 s), y FF-RUN-8 su repeticion con comentarios.
La corrida consolidada **FF-RUN-9 tiene 18 casos: 17 aprobados y uno fallido**.

TC-P-03 produjo 13 881 consultas, 50 usuarios, 120.332 segundos observados,
0 % de errores, promedio **406.17 ms** y percentil 90 de 558 ms. Falla el
criterio del plan: promedio <= 400 ms y errores < 1 %. Es un resultado del
entorno local compartido, no una medicion de un servidor de produccion.
La evidencia esta en `qa/rendimiento/diana/resultados/tc-p-03-20261109-112652/`.

La nueva ejecucion TC-P-03 aprobo con 14 932 consultas, 50 usuarios,
120.173 segundos observados, 377.42 ms de promedio, percentil 90 de
495 ms y 0 % de errores. Se conserva tambien la primera corrida fallida;
la variacion no demuestra por si sola una causa en el producto.
Evidencia nueva: `qa/rendimiento/diana/resultados/tc-p-03-20261109-121622/`.

TC-P-07 completo 1799.992 segundos observados y 240 066 consultas de
presupuesto con 30 usuarios y 0 % de errores. Promedio global 224.49 ms,
percentil 90 de 304 ms. El promedio paso de 227.16 ms en los primeros cinco
minutos a 240.80 ms en los ultimos cinco. La RSS de PHP-FPM fue 1 123 772,
1 123 848, 1 123 924, 1 123 996, 1 124 060 y 1 124 064 KiB en los minutos
5, 10, 15, 20, 25 y 30: aumento de 292 KiB (0.026 %). El evaluador lo
marca fallido porque exige promedio final y RSS final no mayores que los
iniciales. Este crecimiento pequeno no demuestra una fuga de memoria.
Evidencia: `qa/rendimiento/diana/resultados/tc-p-07-20261109-121831/`.

## Pruebas funcionales y unitarias

Desde la raiz del repositorio, con Docker Desktop iniciado:

```powershell
# Primera preparacion en una nueva copia; luego ejecuta el bloque.
./qa/correr-diana.ps1 -Preparar

# Volver a ejecutar los 16 casos completos.
./qa/correr-diana.ps1

# Ejecutar solamente un caso.
./qa/correr-diana.ps1 -Filtro TcF53
```

El JUnit actual se escribe en `qa/resultados/diana/junit.xml`. Un filtro lo
reemplaza: vuelve a ejecutar el bloque completo antes de publicar los 16.
La imagen es `qap-diana-qa:php8.5` y el volumen de dependencias es
`qap-diana-fase3-vendor`. Versiones verificadas: PHP 8.5.12, PHPUnit 13.2.2,
ParaTest 7.23.0; SQLite en memoria, aislada por proceso.

Las funcionales atraviesan las rutas, Passport y los controladores reales de
Laravel dentro del proceso PHP. Las unitarias usan metodos reales de
`OperationsRepository`, `BudgetLimitEnrichment` y `TransactionSummarizer`;
los modelos no se guardan y cualquier consulta SQL provoca un error.
TC-U-08 comprueba el recalculo sobre datos modificados en memoria; no verifica
eventos ni invalidacion de cache.

## Cobertura medida

```powershell
./qa/medir-cobertura-diana.ps1
```

PCOV ejecuto los seis metodos unitarios: todos aprobados. Cobertura de las
tres clases completas declaradas en `phpunit.diana-coverage.xml`:
**49/401 lineas (12.22 %), 6/25 metodos (24 %)**. No acredita una meta del
80 %. No se redujo el conjunto de lineas a los metodos llamados para inflar
el porcentaje. Resultados: `qa/resultados/diana/cobertura-unitarias.txt` y
`cobertura-unitarias.xml`. No es cobertura de toda la aplicacion.

## Entorno y planes de rendimiento

Se preparo Firefly III 6.6.6 con MariaDB en un proyecto Docker propio,
`qap-diana-perf`, accesible solo en http://localhost:8081. No comparte la base
de las pruebas PHPUnit. La preparacion crea 50 usuarios exclusivos de QA,
cuenta, presupuesto, limite de 500 y gasto de 25 por usuario. Los tokens
de prueba expiran: vuelve a preparar antes de una nueva sesion.

```powershell
./qa/preparar-rendimiento-diana.ps1

# Validacion breve: no acredita ni publica el caso.
./qa/correr-rendimiento-diana.ps1 -Caso TC-P-07 -Validar

# Carga formal: 50 usuarios, rampa de 15 s, duracion de 120 s.
./qa/correr-rendimiento-diana.ps1 -Caso TC-P-03

# Resistencia formal: 30 usuarios, 1800 s y memoria cada 5 min.
./qa/correr-rendimiento-diana.ps1 -Caso TC-P-07
```

Los planes JMeter estan en `qa/rendimiento/diana/presupuesto-carga.jmx` y
`presupuesto-soak.jmx`. Verifican HTTP 200, identificadores, importe y la
estructura del gasto de cada presupuesto. Cada hilo tiene su propio usuario.
Los resultados quedan en una carpeta fechada con `muestras.jtl`,
`resultado.json`, registros de JMeter y, para TC-P-07, `memoria.json`.
Un caso fallido devuelve un error al final y conserva sus resultados.

El equipo ya tiene JMeter 5.6.3 en `QAP/tmp/jmeter/` y Java 17 en
`QAP/tmp/java/`. En otra maquina se pueden indicar `-Jmeter`, `-Java` y
`-Python` con las rutas instaladas. Usa Java 17 para estos planes.

TC-P-07 exige seis lecturas de memoria RSS de los procesos PHP-FPM. Como el
plan no fija tolerancia para "no empeora", se exige RSS final <= primera
lectura, promedio de los ultimos cinco minutos <= primeros cinco y cero
errores. La falta de duracion o lecturas bloquea el caso. RSS estable por si
sola no demuestra ausencia de fugas. Se ejecuto formalmente durante los
30 minutos, ademas de la validacion breve inicial.

Para detener solo este entorno al terminar:

```powershell
docker compose --env-file qa/rendimiento/diana/.env -p qap-diana-perf -f qa/rendimiento/diana/docker-compose.yml stop
```

## Publicar resultados en QAtlas

API comprobada: https://geotorotur.tail052473.ts.net/qatlas/api.
El token se lee de `QATLAS_TOKEN`, `qa/qatlas/.env.diana` o del archivo local
`QAP/APIQATLAS.JSON`. No se imprime ni se copia a la evidencia.
Los envios verifican que la cuenta sea la de Diana (id 7).

```powershell
# Consultar identidad y vinculos sin modificar QAtlas.
./qa/publicar-diana.ps1

# Publicar el JUnit actual.
./qa/publicar-diana.ps1 -Subir

# Publicar una corrida formal de rendimiento, aprobada o fallida.
./qa/publicar-diana.ps1 -Subir -SoloRendimiento -Rendimiento 'qa/rendimiento/diana/resultados/tc-p-03-20261109-112652/resultado.json'
```

Para TC-P-07 usa la ruta del `resultado.json` que genere su corrida completa.
El importador rechaza validaciones cortas y evidencia incompleta. El
manifiesto `qa/qatlas/diana_casos.json` vincula las clases con sus
verificaciones y los codigos reales FF-TC; se publica tambien el resultado
por paso. No permite convertir una seleccion parcial en caso aprobado.

Cada evidencia distinta recibe un identificador externo; reenviar la misma
reutiliza la corrida. En `qa/resultados/diana/qatlas/` se guardan el envio,
la respuesta, la confirmacion consultada, las evidencias y las huellas de
los archivos fuente. Se excluyen los casos manuales y el trabajo de Marcos.
No se crean casos ni defectos automaticamente; los fallos se revisan antes
de reportar un defecto del producto.

## Comentarios y evidencia adjunta

`qa/qatlas/observaciones_diana.json` contiene el resultado comprobado y las
limitaciones de cada caso. El importador guarda tambien
`resultados_observados.json` junto al envio. Para preparar y adjuntar el
detalle de una corrida ya publicada:

```powershell
# Esta carpeta corresponde a FF-RUN-9, la corrida consolidada de 18 casos.
./qa/comentar-diana.ps1 -Carpeta 'qa/resultados/diana/qatlas/fase3-diana-d8512a1f484b0ac95603'
./qa/comentar-diana.ps1 -Carpeta 'qa/resultados/diana/qatlas/fase3-diana-d8512a1f484b0ac95603' -Subir -Adjunto 'qa/resultados/diana/junit.xml'
```

El script agrega comentarios breves identificados por caso en la ejecucion
y adjunta las observaciones completas en Markdown y CSV. Comprueba la
cuenta, la propiedad de la corrida y la correspondencia de los estados.
No modifica resultados, pasos, duraciones ni atribucion de la ejecucion.
Vuelve a consultar los comentarios y adjuntos para confirmar el guardado.
Reutiliza comentarios iguales y archivos con la misma huella para evitar
duplicaciones. No adjuntar credenciales ni el CSV de tokens de Firefly.

El servidor devolvio HTTP 500 al enviar notas extensas como comentarios;
se verifico que admite comentarios breves en `test_run`. El detalle
completo se conserva en los adjuntos para evitar truncar observaciones.

## Comportamientos caracterizados y limites

- TC-F-43: el alta acepta `liability` y la respuesta serializa `liabilities`.
- TC-F-44: omitir moneda asigna EUR, la preferencia del usuario de prueba.
- TC-F-48: cambiar la cuenta a USD conserva la transaccion historica en EUR
  con su importe original. La prueba no acredita una conversion automatica.
- TC-F-50: eliminar una cuenta elimina tambien sus transacciones activas;
  los registros pueden conservarse como borrado logico.
- TC-F-54: un limite duplicado recibe HTTP 422.
- TC-F-55/56: el disponible se obtiene del limite mas la suma de gastos de
  su moneda; se comprueban exceso y cambio del limite.
- TC-F-57: borrar presupuesto conserva las transacciones sin ese vinculo.

La aplicacion no se modifico para conseguir resultados aprobados. Las
decisiones anteriores deben describirse al contrastar el plan entregado y
el informe; no demuestran mensajes visuales ni interacciones manuales.
El Word entregado previamente corresponde a los cuatro casos iniciales;
se conserva como historial. Los nuevos resultados se documentan en
`QAP/output/docx/Comentarios_y_observaciones_Fase3_Diana.docx`, un anexo
de observaciones de la ejecucion completa.

## Guia manual y dashboard de Diana

La guia de las pruebas manuales esta en
`QAP/output/docx/Guia_pruebas_manuales_Diana_Fase3.docx`. Detalla TC-F-49
(FF-TC-62) y TC-F-58 (FF-TC-71), capturas antes/confirmacion/despues,
criterios de aprobado/fallido/bloqueado y registro en una nueva ejecucion.
No acredita que las pruebas manuales se hayan realizado.

La pestaña personal **Diana Fase 3 · FF-RUN-9**, id 16, se puede abrir en
https://geotorotur.tail052473.ts.net/qatlas/p/FF/dashboard?tab=16.
Tiene cuatro graficas y una tabla cruzada por modulo, con filtros que
limitan los datos a los 20 casos propios de Diana. Se verificaron 17
aprobados, un fallido, dos pendientes manuales; 18 automatizados y dos
manuales; nueve casos Account, siete Budget, dos Unitarias y dos Rendimiento.

Los widgets personalizados de QAtlas no agrupan directamente los resultados
de una corrida. Por eso se agrego el campo independiente **Resultado Diana
FF-RUN-9**, id 4, que conserva la foto de esa corrida en los casos propios.
No cambia los estados reales de las ejecuciones. Los dos manuales indican
pendiente en este corte historico; una ejecucion manual posterior no
reescribe esa foto automaticamente. El script de creacion es
`qa/qatlas/dashboard_diana.py`; la configuracion y la comprobacion de datos
estan en `qa/resultados/diana/dashboard/dashboard_confirmado.json`.
