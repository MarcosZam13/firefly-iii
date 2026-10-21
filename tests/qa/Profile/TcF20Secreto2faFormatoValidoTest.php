<?php

declare(strict_types=1);

namespace Tests\qa\Profile;

use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Google2FALaravel\Facade as Google2FA;
use Tests\qa\QaTestCase;

/**
 * TC-F-20 (FF-TC-20) Generación de secreto de 2FA con formato TOTP válido. RF-05.
 * Técnica: partición de equivalencias, clase válida.
 *
 * Hallazgo al automatizar: el caso (v2) esperaba 16 caracteres, la longitud
 * por defecto de versiones viejas de google2fa. Firefly 6.6.6 usa google2fa
 * v9, que genera 32 caracteres Base32 (160 bits, lo que recomienda RFC 4226).
 * No es un defecto: se corrige el resultado esperado del caso a 32.
 */
final class TcF20Secreto2faFormatoValidoTest extends QaTestCase
{
    private const string CORREO = 'qa-2fa-alta@firefly.test';
    private User $usuario;
    private TestResponse $paginaActivar;
    private string $secretoTemporal;
    private TestResponse $activacion;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario sin 2FA con sesión iniciada.
        $this->usuario = $this->crearUsuario(self::CORREO);
        $this->iniciarSesion(self::CORREO);

        // Act pasos 1 a 3.
        $this->paginaActivar   = $this->get('/mfa/enableMFA');
        $this->secretoTemporal = (string) $this->preferencia($this->usuario, 'temp-mfa-secret', '');
        $this->activacion      = $this->postJson('/mfa/enableMFA', [
            'password' => self::CLAVE_VALIDA,
            'code'     => Google2FA::getCurrentOtp($this->secretoTemporal),
        ]);
    }

    #[Test]
    public function paso_1_pagina_de_activacion_responde_http_200(): void
    {
        $this->paginaActivar->assertOk();
    }

    #[Test]
    public function paso_2_secreto_temporal_es_base32_de_32_caracteres(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $this->secretoTemporal);
    }

    #[Test]
    public function paso_3_activacion_redirige_a_backup_codes_http_302(): void
    {
        $this->activacion->assertRedirect('/mfa/backup-codes');
    }

    #[Test]
    public function paso_3_el_secreto_guardado_es_el_del_paso_2(): void
    {
        $this->assertSame($this->secretoTemporal, $this->usuario->fresh()->mfa_secret);
    }
}
