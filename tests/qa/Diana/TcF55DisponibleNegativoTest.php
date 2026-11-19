<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-55, RF-19. El limite 500 menos gasto 600 debe producir -100. */
final class TcF55DisponibleNegativoTest extends ApiDianaTestCase
{
    private TestResponse $gasto;
    private TestResponse $consulta;
    protected function setUp(): void
    {
        parent::setUp();
        $cuenta = $this->crearCuentaApi();
        $presupuesto = $this->crearPresupuestoApi();
        $limite = $this->crearLimiteApi($presupuesto);
        $this->gasto = $this->api('POST', '/transactions', $this->datosGasto($cuenta, '600.00', $presupuesto));
        $this->consulta = $this->api('GET', '/budgets/'.$presupuesto.'/limits/'.$limite);
    }
    #[Test] public function paso_1_gasto_responde_http_200(): void { $this->gasto->assertStatus(200); }
    #[Test] public function paso_2_consulta_responde_http_200(): void { $this->consulta->assertStatus(200); }
    #[Test] public function paso_2_spent_contiene_gasto_negativo_600(): void { $this->assertSame('-600.00', $this->gastoDelLimite($this->consulta)); }
    #[Test] public function paso_3_disponible_es_menos_100_sin_truncar(): void { $this->assertSame('-100.00', $this->disponibleDelLimite($this->consulta)); }
}
