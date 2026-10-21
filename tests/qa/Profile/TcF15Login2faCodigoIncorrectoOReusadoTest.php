<?php

declare(strict_types=1);

namespace Tests\qa\Profile;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-15 Login con 2FA: código incorrecto o reutilizado es rechazado. RF-05. Seguridad.
 * Técnica: partición de equivalencias, clases inválidas (código errado y
 * código ya usado dentro de la ventana de 300 s).
 *
 * El mensaje de error es un flash de sesión: se lee justo después de cada
 * petición, porque la siguiente petición lo borra.
 */
final class TcF15Login2faCodigoIncorrectoOReusadoTest extends QaTestCase
{
    private const string CORREO = 'qa-2fa-mal@firefly.test';
    private const string MENSAJE_ERROR = 'This MFA code is not valid.';
    private User $usuario;
    private string $codigoC;
    private TestResponse $login;
    private TestResponse $codigoIncorrecto;
    private mixed $errorTrasCodigoIncorrecto;
    private int $fallosTrasCodigoIncorrecto;
    private TestResponse $codigoValido;
    private array $historialTrasCodigoValido;
    private int $fallosTrasCodigoValido;
    private TestResponse $logout;
    private TestResponse $segundoLogin;
    private TestResponse $codigoReusado;
    private mixed $errorTrasCodigoReusado;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario con 2FA activo, historial vacío y contador en 0.
        [$this->usuario, $secreto] = $this->crearUsuarioCon2fa(self::CORREO);

        // Act paso 1.
        $this->login = $this->iniciarSesion(self::CORREO);

        // Act paso 2: código incorrecto.
        $this->codigoIncorrecto           = $this->postJson('/two-factor/submit', ['one_time_password' => '000000']);
        $this->errorTrasCodigoIncorrecto  = session('error');
        $this->fallosTrasCodigoIncorrecto = (int) $this->preferencia($this->usuario, 'mfa_failure_count', 0);

        // Act paso 3: código vigente C.
        $this->codigoC                   = $this->codigoTotp($secreto);
        $this->codigoValido              = $this->postJson('/two-factor/submit', ['one_time_password' => $this->codigoC]);
        $this->historialTrasCodigoValido = array_column($this->preferencia($this->usuario, 'mfa_history', []), 'code');
        $this->fallosTrasCodigoValido    = (int) $this->preferencia($this->usuario, 'mfa_failure_count', 0);

        // Act paso 4: cerrar sesión y volver a entrar con la contraseña.
        $this->logout       = $this->postJson('/logout');
        $this->segundoLogin = $this->iniciarSesion(self::CORREO);

        // Act paso 5: el mismo código C, antes de 300 s.
        $this->codigoReusado          = $this->postJson('/two-factor/submit', ['one_time_password' => $this->codigoC]);
        $this->errorTrasCodigoReusado = session('error');
    }

    #[Test]
    public function paso_1_login_con_clave_correcta_responde_http_204(): void
    {
        $this->login->assertNoContent();
    }

    #[Test]
    public function paso_2_codigo_incorrecto_responde_http_302(): void
    {
        $this->codigoIncorrecto->assertStatus(302);
    }

    #[Test]
    public function paso_2_codigo_incorrecto_deja_el_error_de_codigo_invalido(): void
    {
        $this->assertSame(self::MENSAJE_ERROR, $this->errorTrasCodigoIncorrecto);
    }

    #[Test]
    public function paso_2_contador_de_fallos_pasa_a_1(): void
    {
        $this->assertSame(1, $this->fallosTrasCodigoIncorrecto);
    }

    #[Test]
    public function paso_3_codigo_vigente_responde_http_302(): void
    {
        $this->codigoValido->assertStatus(302);
    }

    #[Test]
    public function paso_3_historial_mfa_contiene_el_codigo_c(): void
    {
        $this->assertContains($this->codigoC, $this->historialTrasCodigoValido);
    }

    #[Test]
    public function paso_3_contador_de_fallos_vuelve_a_0(): void
    {
        $this->assertSame(0, $this->fallosTrasCodigoValido);
    }

    #[Test]
    public function paso_4_logout_responde_http_204(): void
    {
        $this->logout->assertNoContent();
    }

    #[Test]
    public function paso_4_segundo_login_responde_http_204(): void
    {
        $this->segundoLogin->assertNoContent();
    }

    #[Test]
    public function paso_5_codigo_reusado_responde_http_302(): void
    {
        $this->codigoReusado->assertStatus(302);
    }

    #[Test]
    public function paso_5_codigo_reusado_deja_el_error_de_codigo_invalido(): void
    {
        $this->assertSame(self::MENSAJE_ERROR, $this->errorTrasCodigoReusado);
    }
}
