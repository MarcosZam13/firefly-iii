<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use PHPUnit\Framework\Attributes\Test;

/** TC-U-07, RF-19. Filtros y agregacion reales; el limite y los gastos viven en memoria. */
final class TcU07DisponiblePresupuestoTest extends BudgetUnitTestCase
{
    private function gastosDelCaso(): array
    {
        return [$this->gasto('100.10', '2026-11-01'), $this->gasto('24.90', '2026-11-30 23:59:59'),
            $this->gasto('777.00', '2026-10-31'), $this->gasto('888.00', '2026-12-01'),
            $this->gasto('999.00', '2026-11-09', 42, 2), $this->gasto('444.00', '2026-11-09', 99)];
    }
    #[Test] public function paso_1_calculo_devuelve_un_monto_decimal(): void
    {
        $this->assertMatchesRegularExpression('/^-?\d+\.\d{2}$/', $this->disponible($this->gastosDelCaso()));
    }
    #[Test] public function paso_2_disponible_es_500_menos_125(): void
    {
        $this->assertSame('375.00', $this->disponible($this->gastosDelCaso()));
    }
    #[Test] public function paso_3_gastos_ajenos_al_periodo_moneda_o_presupuesto_no_afectan(): void
    {
        $resumen = $this->sumar($this->gastosDelCaso());
        $this->assertSame(0, bccomp('-125.00', $resumen[1]['sum'], 2));
    }
}
