<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-46, RF-16. Renombrar conserva identidad e historial financiero. */
final class TcF46RenombrarCuentaTest extends ApiDianaTestCase
{
    private string $cuenta;
    private string $grupo;
    private TestResponse $edicion;
    private TestResponse $listado;
    private TestResponse $historial;
    protected function setUp(): void
    {
        parent::setUp();
        $this->cuenta = $this->crearCuentaApi();
        $this->grupo = $this->registrarGastoApi($this->cuenta);
        $this->edicion = $this->api('PUT', '/accounts/'.$this->cuenta, ['name' => 'Cuenta renombrada QA']);
        $this->listado = $this->api('GET', '/accounts?type=asset');
        $this->historial = $this->api('GET', '/accounts/'.$this->cuenta.'/transactions');
    }
    #[Test] public function paso_1_renombrar_responde_http_200(): void { $this->edicion->assertStatus(200); }
    #[Test] public function paso_2_existe_una_sola_cuenta_con_el_mismo_id(): void
    {
        $filas = array_values(array_filter($this->listado->json('data'), fn ($v) => $v['attributes']['name'] === 'Cuenta renombrada QA'));
        $this->assertSame([$this->cuenta], array_column($filas, 'id'));
    }
    #[Test] public function paso_3_historial_conserva_la_transaccion(): void { $this->assertSame([$this->grupo], array_column($this->historial->json('data'), 'id')); }
}
