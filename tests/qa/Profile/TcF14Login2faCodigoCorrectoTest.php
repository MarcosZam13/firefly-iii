<?php

declare(strict_types=1);

namespace Tests\qa\Profile;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-14 (FF-TC-14) Login con 2FA activo y código correcto. RF-05.
 * Técnica: partición de equivalencias, clase válida. Firefly redirige a /home
 * sirva o no el código, así que el éxito se valida con lo que guarda.
 */
final class TcF14Login2faCodigoCorrectoTest extends QaTestCase
{
    private const string CORREO = 'qa-2fa-ok@firefly.test';
    private User $usuario;
    private string $codigoEnviado;
    private TestResponse $login;
    private TestResponse $envioCodigo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario con 2FA activo, historial vacío y contador en 0.
        [$this->usuario, $secreto] = $this->crearUsuarioCon2fa(self::CORREO);

        // Act (pasos 1 y 2).
        $this->login         = $this->iniciarSesion(self::CORREO);
        $this->codigoEnviado = $this->codigoTotp($secreto);
        $this->envioCodigo   = $this->postJson('/two-factor/submit', ['one_time_password' => $this->codigoEnviado]);
    }

    #[Test]
    public function paso_1_login_con_clave_correcta_responde_http_204(): void
    {
        $this->login->assertNoContent();
    }

    #[Test]
    public function paso_2_codigo_correcto_redirige_a_home_http_302(): void
    {
        $this->envioCodigo->assertRedirect('/home');
    }

    #[Test]
    public function paso_3_historial_mfa_contiene_el_codigo_enviado(): void
    {
        $this->assertContains($this->codigoEnviado, array_column($this->preferencia($this->usuario, 'mfa_history', []), 'code'));
    }

    #[Test]
    public function paso_3_contador_de_fallos_queda_en_cero(): void
    {
        $this->assertSame(0, (int) $this->preferencia($this->usuario, 'mfa_failure_count', 0));
    }
}
