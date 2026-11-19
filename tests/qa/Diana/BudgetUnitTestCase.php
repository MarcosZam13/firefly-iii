<?php
declare(strict_types=1);
namespace Tests\qa\Diana;

use Carbon\Carbon;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Models\UserGroup;
use FireflyIII\Repositories\Budget\OperationsRepository;
use FireflyIII\Support\Facades\Amount;
use FireflyIII\Support\JsonApi\Enrichments\BudgetLimitEnrichment;
use FireflyIII\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\integration\CreatesApplication;

/** Sin RefreshDatabase: modelos sin guardar y dependencias monetarias sustituidas. */
abstract class BudgetUnitTestCase extends TestCase
{
    use CreatesApplication;
    private OperationsRepository $operaciones;
    private TransactionCurrency $moneda;
    private int $escalaAnterior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escalaAnterior = bcscale(8);
        DB::listen(static function (): void { throw new \RuntimeException('Una prueba unitaria intento ejecutar SQL.'); });
        $this->moneda = new TransactionCurrency();
        $this->moneda->forceFill(['id' => 1, 'name' => 'Euro', 'code' => 'EUR', 'symbol' => 'EUR', 'decimal_places' => 2]);
        $grupo = new UserGroup();
        $grupo->forceFill(['id' => 1, 'title' => 'Grupo unitario QA']);
        $usuario = new User();
        $usuario->forceFill(['id' => 1, 'user_group_id' => 1]);
        $usuario->setRelation('userGroup', $grupo);
        Amount::shouldReceive('getPrimaryCurrencyByUserGroup')->andReturn($this->moneda);
        Amount::shouldReceive('getPrimaryCurrency')->andReturn($this->moneda);
        Amount::shouldReceive('convertToPrimary')->andReturn(false);
        $this->operaciones = new OperationsRepository();
        $this->operaciones->setUser($usuario);
    }

    protected function tearDown(): void
    {
        bcscale($this->escalaAnterior);
        parent::tearDown();
    }

    protected function gasto(string $monto, string $fecha = '2026-11-09', int $presupuesto = 42, int $moneda = 1): array
    {
        return ['amount' => $monto, 'date' => Carbon::parse($fecha), 'budget_id' => $presupuesto,
            'currency_id' => $moneda, 'currency_name' => 'Euro', 'currency_symbol' => 'EUR',
            'currency_code' => 'EUR', 'currency_decimal_places' => 2, 'foreign_currency_id' => 0];
    }

    protected function sumar(array $gastos): array
    {
        // Es el mismo filtrado real que usa la consulta del limite en Firefly III.
        $enriquecedor = new BudgetLimitEnrichment();
        $filtro = new ReflectionMethod(BudgetLimitEnrichment::class, 'filterToBudget');
        $delPresupuesto = $filtro->invoke($enriquecedor, $gastos, 42);

        return $this->operaciones->sumCollectedExpenses($delPresupuesto,
            Carbon::parse('2026-11-01')->startOfDay(), Carbon::parse('2026-11-30')->endOfDay(), $this->moneda);
    }

    protected function disponible(array $gastos): string
    {
        $resumen = $this->sumar($gastos);

        return bcadd('500.00', $resumen[1]['sum'] ?? '0', 2);
    }
}
