<?php

declare(strict_types=1);

namespace Tests\qa\Profile;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-18 Login con credenciales de una cuenta eliminada falla. RF-06.
 * Técnica: partición de equivalencias.
 */
final class TcF18LoginCuentaEliminadaTest extends QaTestCase
{
    private const string CORREO = 'qa-eliminado@firefly.test';
    private TestResponse $eliminacion;
    private TestResponse $loginTrasEliminar;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario sin 2FA creado para este caso, con sesión iniciada.
        $this->crearUsuario(self::CORREO);
        $this->iniciarSesion(self::CORREO);

        // Act pasos 1 y 2.
        $this->eliminacion       = $this->postJson('/profile/delete-account', ['password' => self::CLAVE_VALIDA]);
        $this->loginTrasEliminar = $this->iniciarSesion(self::CORREO);
    }

    #[Test]
    public function paso_1_eliminar_con_contrasena_correcta_responde_http_302(): void
    {
        $this->eliminacion->assertStatus(302);
    }

    #[Test]
    public function paso_1_el_usuario_ya_no_existe(): void
    {
        $this->assertFalse(User::query()->where('email', self::CORREO)->exists());
    }

    #[Test]
    public function paso_2_login_de_la_cuenta_eliminada_responde_http_422(): void
    {
        $this->loginTrasEliminar->assertStatus(422);
    }

    #[Test]
    public function paso_2_login_trae_el_mensaje_generico(): void
    {
        $this->assertSame('These credentials do not match our records.', $this->loginTrasEliminar->json('errors.email.0'));
    }
}
