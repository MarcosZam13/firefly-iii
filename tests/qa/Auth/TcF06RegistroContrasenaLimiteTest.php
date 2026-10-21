<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-06 (FF-TC-6) Registro con contraseña de 15 caracteres es rechazado. RF-02.
 * Técnica: valores límite sobre la política de contraseña (mínimo 16).
 */
final class TcF06RegistroContrasenaLimiteTest extends QaTestCase
{
    private const string CLAVE_15 = 'Qa-Firefly-1515';  // 15 caracteres
    private const string CLAVE_16 = 'Qa-Firefly-16161'; // 16 caracteres
    private TestResponse $registro15;
    private TestResponse $registro16;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: registro habilitado, correos sin registrar.
        $this->crearUsuario('qa-admin@firefly.test');
        $this->habilitarRegistro();

        // Act (pasos 1 y 3): un registro a cada lado del límite.
        $this->registro15 = $this->postJson('/register', [
            'email' => 'qa-15@firefly.test', 'password' => self::CLAVE_15, 'password_confirmation' => self::CLAVE_15,
        ]);
        $this->registro16 = $this->postJson('/register', [
            'email' => 'qa-16@firefly.test', 'password' => self::CLAVE_16, 'password_confirmation' => self::CLAVE_16,
        ]);
    }

    #[Test]
    public function paso_1_clave_de_15_caracteres_responde_http_422_con_error_en_password(): void
    {
        $this->registro15->assertJsonValidationErrorFor('password');
    }

    #[Test]
    public function paso_2_usuario_con_clave_de_15_no_existe(): void
    {
        $this->assertFalse(User::query()->where('email', 'qa-15@firefly.test')->exists());
    }

    #[Test]
    public function paso_3_clave_de_16_caracteres_responde_http_302(): void
    {
        $this->registro16->assertStatus(302);
    }

    #[Test]
    public function paso_3_usuario_con_clave_de_16_queda_creado(): void
    {
        $this->assertTrue(User::query()->where('email', 'qa-16@firefly.test')->exists());
    }
}
