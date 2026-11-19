<?php

declare(strict_types=1);

namespace Tests\qa\Diana;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-47, RF-16. La edición rechazada no debe cambiar la cuenta. */
final class TcF47EditarTipoInvalidoTest extends ApiDianaTestCase
{
    private TestResponse $edicion;
    private TestResponse $consulta;

    protected function setUp(): void
    {
        parent::setUp();
        $id = $this->crearCuentaApi();
        $this->edicion = $this->api('PUT', '/accounts/'.$id, ['type' => 'invalid']);
        $this->consulta = $this->api('GET', '/accounts/'.$id);
    }

    #[Test]
    public function paso_2_tipo_invalido_responde_http_422(): void
    {
        $this->edicion->assertStatus(422);
    }

    #[Test]
    public function paso_2_error_identifica_el_campo_type(): void
    {
        $this->edicion->assertJsonValidationErrorFor('type');
    }

    #[Test]
    public function paso_3_cuenta_conserva_el_tipo_original(): void
    {
        $this->assertSame('asset', $this->consulta->json('data.attributes.type'));
    }
}
