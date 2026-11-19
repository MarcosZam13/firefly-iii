<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-56, RF-19 y RF-20. Un gasto de 25 permanece al subir el limite de 500 a 600. */
final class TcF56EditarLimiteRecalculaTest extends ApiDianaTestCase
{
    private TestResponse $antes;
    private TestResponse $edicion;
    private TestResponse $despues;
    protected function setUp(): void
    {
        parent::setUp();
        $cuenta = $this->crearCuentaApi();
        $presupuesto = $this->crearPresupuestoApi();
        $limite = $this->crearLimiteApi($presupuesto);
        $this->registrarGastoApi($cuenta, '25.00', $presupuesto);
        $ruta = '/budgets/'.$presupuesto.'/limits/'.$limite;
        $this->antes = $this->api('GET', $ruta);
        $this->edicion = $this->api('PUT', $ruta, ['amount' => '600.00', 'fire_webhooks' => false]);
        $this->despues = $this->api('GET', $ruta);
    }
    #[Test] public function paso_1_disponible_inicial_es_475(): void { $this->assertSame('475.00', $this->disponibleDelLimite($this->antes)); }
    #[Test] public function paso_2_edicion_responde_http_200(): void { $this->edicion->assertStatus(200); }
    #[Test] public function paso_3_gasto_acumulado_no_cambia(): void { $this->assertSame($this->gastoDelLimite($this->antes), $this->gastoDelLimite($this->despues)); }
    #[Test] public function paso_3_disponible_final_es_575(): void { $this->assertSame('575.00', $this->disponibleDelLimite($this->despues)); }
}
