<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use FireflyIII\Models\Account;
use FireflyIII\Models\Transaction;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-50, RF-17. El borrado sin cuenta de reemplazo elimina el historial activo. */
final class TcF50EliminarCuentaConTransaccionesTest extends ApiDianaTestCase
{
    private string $conHistorial;
    private string $vacia;
    private TestResponse $borradoHistorial;
    private TestResponse $borradoVacia;
    protected function setUp(): void
    {
        parent::setUp();
        $this->conHistorial = $this->crearCuentaApi();
        $this->registrarGastoApi($this->conHistorial);
        $this->vacia = $this->idDePrecondicion($this->api('POST', '/accounts', $this->datosCuenta('asset', 'Cuenta vacia QA')));
        $this->borradoHistorial = $this->api('DELETE', '/accounts/'.$this->conHistorial);
        $this->borradoVacia = $this->api('DELETE', '/accounts/'.$this->vacia);
    }
    #[Test] public function paso_1_borrar_con_historial_responde_http_204(): void { $this->borradoHistorial->assertStatus(204); }
    #[Test] public function paso_2_no_quedan_transacciones_activas_de_la_cuenta(): void { $this->assertSame(0, Transaction::query()->where('account_id', $this->conHistorial)->count()); }
    #[Test] public function paso_2_cuenta_con_historial_deja_de_estar_activa(): void { $this->assertFalse(Account::query()->whereKey($this->conHistorial)->exists()); }
    #[Test] public function paso_3_borrar_cuenta_vacia_responde_http_204(): void { $this->borradoVacia->assertStatus(204); }
    #[Test] public function paso_3_cuenta_vacia_deja_de_estar_disponible(): void { $this->assertFalse(Account::query()->whereKey($this->vacia)->exists()); }
}
