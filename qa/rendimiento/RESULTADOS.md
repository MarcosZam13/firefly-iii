# Resultados de rendimiento (2026-10-21)

Equipo: Intel Core i5-12450H (8 núcleos / 12 hilos), 15.7 GB RAM, Windows 11 Home; Docker 29.7.2 (12 CPU, 7.6 GB para contenedores). Firefly III 6.6.6 (fireflyiii/core:version-6.6.6 + mariadb:lts). JMeter 5.6.3, Java 25, modo no gráfico.

## TC-P-01 carga normal (completo)
```
GET /login: n=8356 prom=265 ms mediana=278 p90=345 max=636 error=0.00% codigos={'200': 8356}
POST /login: n=8337 prom=396 ms mediana=406 p90=514 max=862 error=0.00% codigos={'204': 8337}
```

## TC-P-06 pico (completo)
```
GET /login: n=2213 prom=1886 ms mediana=2032 p90=2427 max=2899 error=0.00% codigos={'200': 2213}
POST /login: n=2109 prom=2100 ms mediana=2185 p90=2540 max=3006 error=0.00% codigos={'204': 2109}
```

## TC-P-06 primeros 10 s
```
GET /login: n=848 prom=1549 ms mediana=1812 p90=2451 max=2826 error=0.00% codigos={'200': 848}
POST /login: n=681 prom=1831 ms mediana=2112 p90=2506 max=2858 error=0.00% codigos={'204': 681}
```

## TC-P-06 últimos 10 s
```
GET /login: n=656 prom=2160 ms mediana=2184 p90=2443 max=2858 error=0.00% codigos={'200': 656}
POST /login: n=688 prom=2294 ms mediana=2312 p90=2579 max=2902 error=0.00% codigos={'204': 688}
```

## TC-P-06b complementaria, 0-15 s con 300 hilos
```
GET /login: n=1298 prom=1527 ms mediana=1632 p90=2191 max=2609 error=0.00% codigos={'200': 1298}
POST /login: n=1194 prom=1757 ms mediana=1862 p90=2287 max=2757 error=0.00% codigos={'204': 1194}
```

## TC-P-06b complementaria, 20-40 s con 50 hilos
```
GET /login: n=1382 prom=297 ms mediana=286 p90=364 max=837 error=0.00% codigos={'200': 1382}
POST /login: n=1384 prom=423 ms mediana=407 p90=532 max=908 error=0.00% codigos={'204': 1384}
```


## TC-P-06 versión 3 (re-ejecución oficial)
```
ventana 0 10 s:
POST /login: n=773 prom=1705 ms mediana=1867 p90=2340 max=2810 error=0.00% codigos={'204': 773}
ventana 5 15 s:
POST /login: n=745 prom=2298 ms mediana=2263 p90=2734 max=3285 error=0.00% codigos={'204': 745}
ventana 20 30 s:
POST /login: n=684 prom=424 ms mediana=416 p90=516 max=1102 error=0.00% codigos={'204': 684}
```

# Resultados de rendimiento de transacciones (Kevin, 2026-11-17)

Equipo: MacBook Air Apple M4 (10 núcleos), 16 GB RAM, macOS 26.6.2; Docker 29.9.3 (10 CPU, 7.7 GB para contenedores). Firefly III 6.6.6 (fireflyiii/core:version-6.6.6 + mariadb:lts, docker-compose.yml de esta carpeta). JMeter 5.6.3 en la imagen alpine/jmeter:5.6.3, modo no gráfico, en la misma red de Docker que Firefly.
Preparación: `sh preparar-transacciones.sh 50 120` (50 usuarios con token de API, cuenta EUR con saldo inicial 100000.00 y 120 gastos de historial cada uno). Cada plan: `sh correr-jmeter.sh tc-p-0N`.

## TC-P-02 carga normal en registrar transacción (50 hilos, rampa 20 s, 120 s)
```
POST /api/v1/transactions: n=7774 prom=712 ms mediana=736 p90=1148 max=1387 error=0.00% codigos={'200': 7774}
```

## TC-P-04 carga en listado paginado (50 hilos, rampa 20 s, 120 s, limit=50, página 1 a 3)
```
GET /api/v1/transactions (página): n=21108 prom=261 ms mediana=270 p90=332 max=687 error=0.00% codigos={'200': 21108}
```

## TC-P-05 estrés escalonado en registrar transacción (25 -> 50 -> 100 -> 150 -> 200 hilos, 30 s cada escalón)
```
completo:  n=4911 prom=3260 ms mediana=3123 p90=7414 max=7765 error=0.00% codigos={'200': 4911}
0-30 s    (25 hilos):  n=1107 prom=633 ms  p90=755  max=973  error=0.00%
30-60 s   (50 hilos):  n=1057 prom=1393 ms p90=1544 max=1651 error=0.00%
60-90 s  (100 hilos):  n=991  prom=3054 ms p90=3361 max=3566 error=0.00%
90-120 s (150 hilos):  n=904  prom=5109 ms p90=5452 max=5586 error=0.00%
120-150 s (200 hilos): n=852  prom=7264 ms p90=7576 max=7765 error=0.00%
```
Punto de quiebre (promedio > 2000 ms): escalón de 100 hilos. El rendimiento no sube con más hilos (37 -> 28 transacciones/s): el tiempo de respuesta crece en proporción a la carga. Sin errores ni 5xx hasta 200 hilos.

## TC-P-08 transacciones concurrentes sobre la misma cuenta (50 hilos x 10 gastos de 1.00, liberados juntos)
```
POST /api/v1/transactions (misma cuenta): n=500 prom=1323 ms mediana=1120 p90=2322 max=3036 error=0.00% codigos={'200': 500}
saldo inicial 99990.00, gastos aceptados 500 de 500, saldo final 99490.00, esperado 99490.00
```
