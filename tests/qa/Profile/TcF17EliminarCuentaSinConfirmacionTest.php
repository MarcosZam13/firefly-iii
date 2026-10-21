<?php

declare(strict_types=1);

namespace Tests\qa\Profile;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-17 (FF-TC-17) Eliminar cuenta de usuario con contraseña incorrecta no la borra. RF-06.
 * Técnica: partición de equivalencias, clases inválidas (sin contraseña / contraseña errada).
 */
final class TcF17EliminarCuentaSinConfirmacionTest extends QaTestCase
{
    private const string CORREO = 'qa-borrar@firefly.test';
    private TestResponse $sinClave;
    private TestResponse $claveIncorrecta;
    private mixed $errorTrasClaveIncorrecta;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario sin 2FA con sesión iniciada.
        $this->crearUsuario(self::CORREO);
        $this->iniciarSesion(self::CORREO);

        // Act pasos 1 y 2.
        $this->sinClave                 = $this->postJson('/profile/delete-account');
        $this->claveIncorrecta          = $this->postJson('/profile/delete-account', ['password' => 'Clave-Incorrecta-2026']);
        $this->errorTrasClaveIncorrecta = session('error');
    }

    #[Test]
    public function paso_1_sin_contrasena_responde_http_422_con_error_en_password(): void
    {
        $this->sinClave->assertJsonValidationErrorFor('password');
    }

    #[Test]
    public function paso_2_contrasena_incorrecta_redirige_a_delete_account_http_302(): void
    {
        $this->claveIncorrecta->assertRedirect('/profile/delete-account');
    }

    #[Test]
    public function paso_2_contrasena_incorrecta_deja_el_error_invalid_password(): void
    {
        $this->assertSame('Invalid password!', $this->errorTrasClaveIncorrecta);
    }

    #[Test]
    public function paso_3_el_usuario_sigue_existiendo(): void
    {
        $this->assertTrue(User::query()->where('email', self::CORREO)->exists());
    }
}
