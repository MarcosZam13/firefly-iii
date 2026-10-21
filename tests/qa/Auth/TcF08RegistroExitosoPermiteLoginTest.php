<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-08 (FF-TC-8) Registro exitoso permite iniciar sesión de inmediato. RF-02.
 * Técnica: partición de equivalencias, clase válida.
 */
final class TcF08RegistroExitosoPermiteLoginTest extends QaTestCase
{
    private const string CORREO = 'qa-nuevo@firefly.test';
    private TestResponse $registro;
    private TestResponse $logout;
    private TestResponse $login;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: registro habilitado.
        $this->crearUsuario('qa-admin@firefly.test');
        $this->habilitarRegistro();

        // Act (pasos 1 a 3).
        $this->registro = $this->postJson('/register', [
            'email' => self::CORREO, 'password' => self::CLAVE_VALIDA, 'password_confirmation' => self::CLAVE_VALIDA,
        ]);
        $this->logout = $this->postJson('/logout');
        $this->login  = $this->iniciarSesion(self::CORREO);
    }

    #[Test]
    public function paso_1_registro_valido_responde_http_302(): void
    {
        $this->registro->assertStatus(302);
    }

    #[Test]
    public function paso_1_usuario_nuevo_queda_en_la_tabla_users(): void
    {
        $this->assertTrue(User::query()->where('email', self::CORREO)->exists());
    }

    #[Test]
    public function paso_2_logout_responde_http_204(): void
    {
        $this->logout->assertNoContent();
    }

    #[Test]
    public function paso_3_login_con_las_credenciales_nuevas_responde_http_204(): void
    {
        $this->login->assertNoContent();
    }
}
