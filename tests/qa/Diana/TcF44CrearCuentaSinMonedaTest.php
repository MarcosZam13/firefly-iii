<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-44, RF-15. Esta version asigna EUR, la preferencia inicial del usuario. */
final class TcF44CrearCuentaSinMonedaTest extends ApiDianaTestCase
{
    private TestResponse $creacion;
    private TestResponse $consulta;
    protected function setUp(): void
    {
        parent::setUp();
        $datos = $this->datosCuenta('asset', 'Cuenta sin moneda');
        unset($datos['currency_code']);
        $this->creacion = $this->api('POST', '/accounts', $datos);
        $id = $this->idDePrecondicion($this->creacion);
        $this->consulta = $this->api('GET', '/accounts/'.$id);
    }
    #[Test] public function paso_1_cuenta_sin_moneda_se_crea(): void { $this->assertNotNull($this->creacion->json('data.id')); }
    #[Test] public function paso_2_creacion_responde_http_200(): void { $this->creacion->assertStatus(200); }
    #[Test] public function paso_3_moneda_por_defecto_es_eur(): void { $this->assertSame('EUR', $this->consulta->json('data.attributes.currency_code')); }
}
