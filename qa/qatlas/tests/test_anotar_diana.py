import sys
from pathlib import Path
import unittest

sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from anotar_diana import notas_caso


class ObservacionesDianaTest(unittest.TestCase):
    def setUp(self):
        self.fuentes={'TC-F-43':{'resultado':'Se crearon los tipos.','observacion':'Se consulta la API.'}}
        self.evidencia={'TC-F-43':{'status':'aprobado','checks':12,'duration_ms':2500}}
        self.caso={'test_case_title':'TC-F-43 Crear cuenta','status':'aprobado'}

    def test_no_anota_resultados_sin_evidencia_del_mismo_estado(self):
        self.caso['status']='fallido'
        with self.assertRaises(ValueError):notas_caso(self.caso,self.fuentes,self.evidencia)

    def test_no_anota_casos_manuales(self):
        self.caso['test_case_title']='TC-F-49 Cancelar borrado'
        with self.assertRaises(ValueError):notas_caso(self.caso,self.fuentes,self.evidencia)

    def test_resultado_fallido_no_usa_la_descripcion_de_aprobacion(self):
        self.caso['status']='fallido';self.evidencia['TC-F-43']['status']='fallido'
        self.evidencia['TC-F-43']['error_message']='La cuenta no se pudo crear.'
        _,texto=notas_caso(self.caso,self.fuentes,self.evidencia)
        self.assertIn('La cuenta no se pudo crear.',texto)
        self.assertNotIn('Se crearon los tipos.',texto)

    def test_anotacion_no_modifica_el_estado_de_la_evidencia(self):
        _,texto=notas_caso(self.caso,self.fuentes,self.evidencia)
        self.assertIn('12 verificaciones',texto)
        self.assertEqual('aprobado',self.evidencia['TC-F-43']['status'])
