<?php
declare(strict_types=1);
namespace Tests\qa\Diana;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-54, RF-18. Esta version rechaza el limite duplicado con HTTP 422. */
final class TcF54LimiteDuplicadoTest extends ApiDianaTestCase
{
    private TestResponse $duplicado;
    private TestResponse $listado;
    protected function setUp(): void
    {
        parent::setUp();
        $id = $this->crearPresupuestoApi();
        $this->crearLimiteApi($id);
        $this->duplicado = $this->api('POST', '/budgets/'.$id.'/limits', $this->datosLimite('500.00'));
        $this->listado = $this->api('GET', '/budgets/'.$id.'/limits');
    }
    #[Test] public function paso_1_limite_repetido_es_rechazado(): void { $this->duplicado->assertJsonValidationErrorFor('start'); }
    #[Test] public function paso_2_duplicado_responde_http_422(): void { $this->duplicado->assertStatus(422); }
    #[Test] public function paso_3_solo_existe_un_limite(): void { $this->assertCount(1, $this->listado->json('data')); }
}
