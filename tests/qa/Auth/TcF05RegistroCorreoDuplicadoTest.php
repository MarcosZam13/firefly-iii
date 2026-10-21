<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-05 (FF-TC-5) Registro con correo ya registrado. RF-02.
 * Técnica: partición de equivalencias, clase inválida (correo repetido).
 */
final class TcF05RegistroCorreoDuplicadoTest extends QaTestCase
{
    private const string CORREO = 'qa-dup@firefly.test';
    private TestResponse $respuestaRegistro;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: el correo ya existe, registro habilitado, sin sesión.
        $this->crearUsuario(self::CORREO);
        $this->habilitarRegistro();

        // Act (paso 1): POST /register con el mismo correo.
        $this->respuestaRegistro = $this->postJson('/register', [
            'email'                 => self::CORREO,
            'password'              => self::CLAVE_VALIDA,
            'password_confirmation' => self::CLAVE_VALIDA,
        ]);
    }

    #[Test]
    public function paso_1_correo_repetido_responde_http_422_con_error_en_email(): void
    {
        $this->respuestaRegistro->assertJsonValidationErrorFor('email');
    }

    #[Test]
    public function paso_2_sigue_habiendo_un_solo_usuario_con_ese_correo(): void
    {
        $this->assertSame(1, User::query()->where('email', self::CORREO)->count());
    }
}
