<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use PHPUnit\Framework\Attributes\Test;

/** TC-U-08, RF-19. Simula cambios de la lista, sin despachar eventos ni tocar SQL. */
final class TcU08RecalculoPresupuestoTest extends BudgetUnitTestCase
{
    private string $trasRegistro;
    private string $trasEdicion;
    private string $trasBorrado;
    protected function setUp(): void
    {
        parent::setUp();
        $gastos = [$this->gasto('100.00')];
        $gastos[] = $this->gasto('25.50');
        $this->trasRegistro = $this->disponible($gastos);
        $gastos[0]['amount'] = '60.25';
        $this->trasEdicion = $this->disponible($gastos);
        unset($gastos[1]);
        $this->trasBorrado = $this->disponible($gastos);
    }
    #[Test] public function paso_1_registrar_resta_25_con_50_al_disponible(): void { $this->assertSame('374.50', $this->trasRegistro); }
    #[Test] public function paso_2_editar_ajusta_solo_la_diferencia(): void { $this->assertSame('414.25', $this->trasEdicion); }
    #[Test] public function paso_3_eliminar_restituye_el_monto_borrado(): void { $this->assertSame('439.75', $this->trasBorrado); }
}
