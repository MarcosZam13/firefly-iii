<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-03 Login con correo inexistente. RF-01. Seguridad.
 * Técnica: partición de equivalencias, clase inválida (correo). El sistema no
 * debe revelar qué correos están registrados.
 */
final class TcF03LoginCorreoInexistenteTest extends QaTestCase
{
    private TestResponse $respuestaLogin;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: noexiste@firefly.test no tiene cuenta (base nueva, sin usuarios con ese correo).

        // Act (paso 1): POST /login con el correo inexistente.
        $this->respuestaLogin = $this->iniciarSesion('noexiste@firefly.test', 'Cualquier-Clave-16');
    }

    #[Test]
    public function paso_1_correo_inexistente_responde_http_422(): void
    {
        $this->respuestaLogin->assertStatus(422);
    }

    #[Test]
    public function paso_2_error_de_email_es_el_mismo_mensaje_que_con_clave_incorrecta(): void
    {
        $this->assertSame('These credentials do not match our records.', $this->respuestaLogin->json('errors.email.0'));
    }
}
