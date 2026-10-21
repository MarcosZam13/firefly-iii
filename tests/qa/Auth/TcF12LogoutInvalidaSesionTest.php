<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-12 Cerrar sesión invalida la sesión. RF-04.
 * Técnica: partición de equivalencias.
 */
final class TcF12LogoutInvalidaSesionTest extends QaTestCase
{
    private TestResponse $logout;
    private TestResponse $accesoTrasLogout;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: sesión iniciada con POST /login.
        $this->crearUsuario('qa-logout@firefly.test');
        $this->iniciarSesion('qa-logout@firefly.test');

        // Act (pasos 1 y 2).
        $this->logout           = $this->postJson('/logout');
        $this->accesoTrasLogout = $this->getJson('/');
    }

    #[Test]
    public function paso_1_logout_responde_http_204(): void
    {
        $this->logout->assertNoContent();
    }

    #[Test]
    public function paso_2_la_misma_sesion_ya_no_da_acceso_http_401(): void
    {
        $this->accesoTrasLogout->assertUnauthorized();
    }
}
