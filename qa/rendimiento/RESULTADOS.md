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

