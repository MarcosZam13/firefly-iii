<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-57, RF-20. Eliminar el presupuesto no elimina sus transacciones. */
final class TcF57EliminarPresupuestoConservaTransaccionesTest extends ApiDianaTestCase
{
    private TestResponse $borrado;
    private TestResponse $despues;
    private array $antes;
    protected function setUp(): void
    {
        parent::setUp();
        $cuenta = $this->crearCuentaApi();
        $presupuesto = $this->crearPresupuestoApi();
        $grupo = $this->registrarGastoApi($cuenta, '25.00', $presupuesto);
        $this->antes = $this->api('GET', '/transactions/'.$grupo)->json('data.attributes.transactions.0');
        $this->borrado = $this->api('DELETE', '/budgets/'.$presupuesto);
        $this->despues = $this->api('GET', '/transactions/'.$grupo);
    }
    #[Test] public function paso_1_borrado_responde_http_204(): void { $this->borrado->assertStatus(204); }
    #[Test] public function paso_2_transaccion_sigue_existiendo(): void { $this->despues->assertStatus(200); }
    #[Test] public function paso_3_campos_financieros_no_cambian(): void
    {
        $claves = array_flip(['amount', 'date', 'source_id', 'destination_id', 'currency_code']);
        $this->assertSame(array_intersect_key($this->antes, $claves), array_intersect_key($this->despues->json('data.attributes.transactions.0'), $claves));
    }
    #[Test] public function paso_3_transaccion_pierde_el_presupuesto(): void { $this->assertNull($this->despues->json('data.attributes.transactions.0.budget_id')); }
}
