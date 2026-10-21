"""Resume un .jtl de JMeter para el informe: por etiqueta, promedio, p90, error y códigos.
Uso: python analizar.py resultados/tc-p-01.jtl [ventana_inicio_s ventana_fin_s]"""
import csv, sys, statistics
from collections import Counter

filas = list(csv.DictReader(open(sys.argv[1], encoding="utf-8")))
t0 = min(int(f["timeStamp"]) for f in filas)
desde = float(sys.argv[2]) if len(sys.argv) > 2 else None
hasta = float(sys.argv[3]) if len(sys.argv) > 3 else None
if desde is not None:
    filas = [f for f in filas if desde <= (int(f["timeStamp"]) - t0) / 1000 < hasta]
for etiqueta in sorted({f["label"] for f in filas}):
    g = [f for f in filas if f["label"] == etiqueta]
    t = sorted(int(f["elapsed"]) for f in g)
    err = [f for f in g if f["success"] != "true"]
    p90 = t[int(len(t) * 0.9) - 1] if t else 0
    print(f"{etiqueta}: n={len(g)} prom={statistics.mean(t):.0f} ms mediana={statistics.median(t):.0f} p90={p90} max={t[-1]} "
          f"error={100 * len(err) / len(g):.2f}% codigos={dict(Counter(f['responseCode'] for f in g))}")
    for f in err[:3]:
        print("   falla:", f["responseCode"], f["elapsed"], "ms", f["failureMessage"][:90])
