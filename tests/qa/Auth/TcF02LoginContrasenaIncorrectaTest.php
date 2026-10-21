<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-02 (FF-TC-2) Login con contraseña incorrecta. RF-01.
 * Técnica: partición de equivalencias, clase inválida (contraseña).
 */
final class TcF02LoginContrasenaIncorrectaTest extends QaTestCase
{
    private TestResponse $respuestaLogin;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario activo; la contraseña enviada es otra.
        $this->crearUsuario('qa-clave@firefly.test');

        // Act (paso 1): POST /login con la contraseña incorrecta.
        $this->respuestaLogin = $this->iniciarSesion('qa-clave@firefly.test', 'Clave-Incorrecta-2026');
    }

    #[Test]
    public function paso_1_contrasena_incorrecta_responde_http_422(): void
    {
        $this->respuestaLogin->assertStatus(422);
    }

    #[Test]
    public function paso_2_error_de_email_es_el_mensaje_generico(): void
    {
        $this->assertSame('These credentials do not match our records.', $this->respuestaLogin->json('errors.email.0'));
    }

    #[Test]
    public function paso_3_sesion_queda_sin_usuario_autenticado(): void
    {
        $this->assertGuest();
    }
}
