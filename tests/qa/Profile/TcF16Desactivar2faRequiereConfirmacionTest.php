<?php

declare(strict_types=1);

namespace Tests\qa\Profile;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-16 (FF-TC-16) Desactivar 2FA requiere contraseña y código. RF-05.
 * Técnica: partición de equivalencias (falta uno de los dos datos / ambos presentes).
 */
final class TcF16Desactivar2faRequiereConfirmacionTest extends QaTestCase
{
    private const string CORREO = 'qa-2fa-off@firefly.test';
    private User $usuario;
    private TestResponse $soloCodigo;
    private TestResponse $soloClave;
    private ?string $secretoTrasIntentosIncompletos;
    private TestResponse $claveYCodigo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario con 2FA activo y sesión con 2FA completo.
        [$this->usuario, $secreto] = $this->crearUsuarioCon2fa(self::CORREO);
        $this->completarLoginCon2fa(self::CORREO, $secreto);

        // Act pasos 1 a 4.
        $this->soloCodigo = $this->postJson('/mfa/disableMFA', ['code' => $this->codigoTotp($secreto)]);
        $this->soloClave  = $this->postJson('/mfa/disableMFA', ['password' => self::CLAVE_VALIDA]);
        $this->secretoTrasIntentosIncompletos = $this->usuario->fresh()->mfa_secret;
        $this->claveYCodigo = $this->postJson('/mfa/disableMFA', ['password' => self::CLAVE_VALIDA, 'code' => $this->codigoTotp($secreto)]);
    }

    #[Test]
    public function paso_1_sin_contrasena_responde_http_422_con_error_en_password(): void
    {
        $this->soloCodigo->assertJsonValidationErrorFor('password');
    }

    #[Test]
    public function paso_2_sin_codigo_responde_http_422_con_error_en_code(): void
    {
        $this->soloClave->assertJsonValidationErrorFor('code');
    }

    #[Test]
    public function paso_3_el_2fa_sigue_activo_tras_los_intentos_incompletos(): void
    {
        $this->assertNotNull($this->secretoTrasIntentosIncompletos);
    }

    #[Test]
    public function paso_4_con_contrasena_y_codigo_responde_http_302(): void
    {
        $this->claveYCodigo->assertStatus(302);
    }

    #[Test]
    public function paso_4_el_secreto_2fa_queda_en_null(): void
    {
        $this->assertNull($this->usuario->fresh()->mfa_secret);
    }
}
