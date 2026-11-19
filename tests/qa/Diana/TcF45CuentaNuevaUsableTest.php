<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-45, RF-15. La precondicion se crea localmente, sin depender de TC-F-43. */
final class TcF45CuentaNuevaUsableTest extends ApiDianaTestCase
{
    private string $cuenta;
    private TestResponse $gasto;
    protected function setUp(): void
    {
        parent::setUp();
        $this->cuenta = $this->crearCuentaApi();
        $this->gasto = $this->api('POST', '/transactions', $this->datosGasto($this->cuenta));
    }
    #[Test] public function paso_1_transaccion_queda_creada(): void { $this->assertNotNull($this->gasto->json('data.id')); }
    #[Test] public function paso_1_transaccion_usa_la_cuenta_nueva(): void { $this->assertSame($this->cuenta, $this->gasto->json('data.attributes.transactions.0.source_id')); }
    #[Test] public function paso_2_registro_responde_http_200(): void { $this->gasto->assertStatus(200); }
}
