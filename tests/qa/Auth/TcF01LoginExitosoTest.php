<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-01 Login exitoso con credenciales válidas. RF-01.
 * Técnica: partición de equivalencias, clase válida.
 */
final class TcF01LoginExitosoTest extends QaTestCase
{
    private User $usuario;
    private TestResponse $respuestaLogin;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario activo, sin 2FA, contador de intentos en cero (base nueva).
        $this->usuario = $this->crearUsuario('qa-login@firefly.test');

        // Act (paso 1): POST /login con credenciales correctas.
        $this->respuestaLogin = $this->iniciarSesion('qa-login@firefly.test');
    }

    #[Test]
    public function paso_1_login_valido_responde_http_204_sin_cuerpo(): void
    {
        $this->respuestaLogin->assertNoContent();
    }

    #[Test]
    public function paso_2_sesion_queda_autenticada_con_el_usuario(): void
    {
        $this->assertAuthenticatedAs($this->usuario);
    }
}
