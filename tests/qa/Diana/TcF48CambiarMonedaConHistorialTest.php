<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-48, RF-16. Caracterizacion: el historial mantiene moneda y monto explicitos. */
final class TcF48CambiarMonedaConHistorialTest extends ApiDianaTestCase
{
    private TestResponse $edicion;
    private TestResponse $cuenta;
    private TestResponse $transaccion;
    protected function setUp(): void
    {
        parent::setUp();
        $cuenta = $this->crearCuentaApi();
        $grupo = $this->registrarGastoApi($cuenta, '25.00');
        $this->edicion = $this->api('PUT', '/accounts/'.$cuenta, ['currency_code' => 'USD']);
        $this->cuenta = $this->api('GET', '/accounts/'.$cuenta);
        $this->transaccion = $this->api('GET', '/transactions/'.$grupo);
    }
    #[Test] public function paso_1_edicion_se_procesa(): void { $this->assertNotNull($this->edicion->json('data.id')); }
    #[Test] public function paso_2_edicion_responde_http_200(): void { $this->edicion->assertStatus(200); }
    #[Test] public function paso_3_cuenta_adopta_usd(): void { $this->assertSame('USD', $this->cuenta->json('data.attributes.currency_code')); }
    #[Test] public function paso_3_historial_conserva_moneda_eur_explicita(): void { $this->assertSame('EUR', $this->transaccion->json('data.attributes.transactions.0.currency_code')); }
    #[Test] public function paso_3_historial_conserva_el_monto(): void { $this->assertSame(0, bccomp('25.00', (string) $this->transaccion->json('data.attributes.transactions.0.amount'), 2)); }
}
