import importlib.util
import json
from pathlib import Path
import tempfile
import unittest
import xml.etree.ElementTree as ET

SPEC = importlib.util.spec_from_file_location('subir_diana', Path(__file__).resolve().parents[1] / 'subir_diana.py')
m = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(m)


class RegistroDianaTest(unittest.TestCase):
    def analizar(self, nombres, fallos=None, codigo='TC-F-53', esperado=6):
        root = ET.Element('testsuites')
        suite = ET.SubElement(root, 'testsuite')
        clase = 'TcF53LimiteInvalidoTest' if codigo == 'TC-F-53' else 'TcF43CrearCuentaTiposValidosTest'
        for i, nombre in enumerate(nombres):
            node = ET.SubElement(suite, 'testcase', {'class': 'Tests\\qa\\Diana\\'+clase,
                'name': nombre, 'time': '.1'})
            if fallos and i in fallos:
                ET.SubElement(node, fallos[i], {'message': 'resultado de prueba'})
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp)/'junit.xml'
            ET.ElementTree(root).write(path)
            return m.leer_resultados(path, {codigo: {'class': clase, 'checks': esperado}})[codigo]

    def nombres_f53(self):
        return [f'{prefijo} with data set "{dato}"' for prefijo in [
            'paso_1_y_2_monto_invalido_responde_http_422',
            'paso_1_y_2_error_identifica_el_monto', 'paso_3_no_se_guarda_un_limite_invalido'
        ] for dato in ['negativo', 'cero']]

    def test_montos_negativo_y_cero_se_vinculan_a_pasos_distintos(self):
        r = self.analizar(self.nombres_f53())
        self.assertEqual({1: 2, 2: 2, 3: 2}, {n:len(v) for n,v in r['steps'].items()})

    def test_un_fallo_no_se_oculta_entre_proveedores_aprobados(self):
        r = self.analizar(self.nombres_f53(), {1:'failure'})
        self.assertEqual('fallido', r['status'])

    def test_error_de_preparacion_bloquea_el_caso(self):
        r = self.analizar(self.nombres_f53(), {0:'error'})
        self.assertEqual('bloqueado', r['status'])

    def test_omitido_no_se_convierte_en_aprobado(self):
        r = self.analizar(self.nombres_f53(), {0:'skipped'})
        self.assertEqual('omitido', r['status'])

    def test_filtro_parcial_no_acredita_un_caso_completo(self):
        r = self.analizar(self.nombres_f53()[:2])
        self.assertFalse(r['complete'])
        self.assertEqual('bloqueado', r['status'])

    def test_duplicar_nombres_no_completa_verificaciones_faltantes(self):
        r = self.analizar(self.nombres_f53()[:3]*2)
        self.assertFalse(r['complete'])

    def test_cuenta_activo_y_otros_tipos_respetan_pasos_del_plan(self):
        self.assertEqual([1], m.pasos_de_prueba('TC-F-43', 'paso_1_crear_cuenta with data set "activo"'))
        self.assertEqual([2], m.pasos_de_prueba('TC-F-43', 'paso_1_crear_cuenta with data set "pasivo"'))

    def test_casos_manuales_no_pertenecen_al_registro_automatico(self):
        self.assertTrue({'TC-F-49','TC-F-58'}.isdisjoint(m.AUTOMATICOS))

    def test_token_de_otra_persona_no_permite_publicar(self):
        class OtroUsuario:
            def pedir(self, *args): return {'id':1,'name':'Otro usuario'}
        with self.assertRaises(ValueError):
            m.vincular(OtroUsuario(), {'results':[]}, {})

    def test_registro_diana_no_incluye_json_de_rendimiento_de_marcos(self):
        with tempfile.TemporaryDirectory() as tmp:
            path=Path(tmp)/'junit.xml'
            path.write_text('<testsuites/>')
            envio=m.construir_envio(path, {'TC-F-53': {'status':'aprobado','duration_ms':10,'error_message':''}})
        self.assertEqual(['TC-F-53'],[r['test_case_code'] for r in envio['results']])
        self.assertTrue(envio['run']['external_id'].startswith('fase3-diana-'))


if __name__ == '__main__':
    unittest.main()
