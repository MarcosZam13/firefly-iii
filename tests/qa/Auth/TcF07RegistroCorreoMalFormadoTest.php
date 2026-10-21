<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-07 (FF-TC-7) Registro con correo sin formato válido. RF-02.
 * Técnica: partición de equivalencias, clase inválida (correo sin @).
 */
final class TcF07RegistroCorreoMalFormadoTest extends QaTestCase
{
    private const string CORREO_MAL = 'qatestfirefly.test';
    private TestResponse $respuestaRegistro;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: registro habilitado.
        $this->crearUsuario('qa-admin@firefly.test');
        $this->habilitarRegistro();

        // Act (paso 1).
        $this->respuestaRegistro = $this->postJson('/register', [
            'email' => self::CORREO_MAL, 'password' => self::CLAVE_VALIDA, 'password_confirmation' => self::CLAVE_VALIDA,
        ]);
    }

    #[Test]
    public function paso_1_correo_sin_arroba_responde_http_422_con_error_en_email(): void
    {
        $this->respuestaRegistro->assertJsonValidationErrorFor('email');
    }

    #[Test]
    public function paso_2_correo_mal_formado_no_queda_registrado(): void
    {
        $this->assertFalse(User::query()->where('email', self::CORREO_MAL)->exists());
    }
}
