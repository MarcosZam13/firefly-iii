<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use FireflyIII\Models\TransactionGroup;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-51, RF-17. Un ID borrado no debe crear una transaccion nueva. */
final class TcF51CuentaEliminadaNoUsableTest extends ApiDianaTestCase
{
    private TestResponse $registro;
    protected function setUp(): void
    {
        parent::setUp();
        $cuenta = $this->crearCuentaApi();
        $borrado = $this->api('DELETE', '/accounts/'.$cuenta);
        if (204 !== $borrado->status()) { throw new \RuntimeException('No se pudo preparar la cuenta eliminada.'); }
        $this->registro = $this->api('POST', '/transactions', $this->datosGasto($cuenta));
    }
    #[Test] public function paso_1_no_se_crea_la_transaccion(): void { $this->assertSame(0, TransactionGroup::query()->count()); }
    #[Test] public function paso_2_cuenta_eliminada_responde_http_422(): void { $this->registro->assertStatus(422); }
}
