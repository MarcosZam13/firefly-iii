import unittest
from ejecutar import evaluar

def muestras(hilos,duracion,elapsed=100,exito=True):
    return [{'label':'GET presupuesto','elapsed':str(elapsed),'timeStamp':str(100000+i*1000),
             'threadName':'usuario-'+str(i%hilos),'success':str(exito).lower()} for i in range(duracion)]

class EvaluacionRendimientoTest(unittest.TestCase):
    def test_validacion_corta_no_acredita_rendimiento(self):
        self.assertEqual('bloqueado',evaluar(muestras(1,2),'TC-P-03',False,1,2)['status'])
    def test_carga_completa_aprueba_con_datos_dentro_del_umbral(self):
        self.assertEqual('aprobado',evaluar(muestras(50,120),'TC-P-03',True,50,120)['status'])
    def test_carga_lenta_falla(self):
        self.assertEqual('fallido',evaluar(muestras(50,120,600),'TC-P-03',True,50,120)['status'])
    def test_no_finge_soak_sin_memoria(self):
        self.assertEqual('bloqueado',evaluar(muestras(30,1800),'TC-P-07',True,30,1800)['status'])
    def test_soak_con_memoria_creciente_falla(self):
        memoria=[{'segundo':i*300,'rss_kib':1000+i*100} for i in range(1,7)]
        self.assertEqual('fallido',evaluar(muestras(30,1800),'TC-P-07',True,30,1800,memoria)['status'])
    def test_soak_estable_completo_aprueba(self):
        memoria=[{'segundo':i*300,'rss_kib':1000} for i in range(1,7)]
        self.assertEqual('aprobado',evaluar(muestras(30,1800),'TC-P-07',True,30,1800,memoria)['status'])

if __name__=='__main__':unittest.main()
