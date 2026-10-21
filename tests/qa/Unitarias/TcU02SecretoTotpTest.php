<?php

declare(strict_types=1);

namespace Tests\qa\Unitarias;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PragmaRX\Google2FA\Google2FA;

/**
 * TC-U-02 Generación de secreto TOTP con formato válido. RF-05.
 * Técnica: cobertura de sentencias de generateSecretKey y getQRCodeUrl.
 *
 * Hallazgo al automatizar (igual que TC-F-20): google2fa v9, la versión de
 * Firefly 6.6.6, genera 32 caracteres por defecto, no 16. Se corrige el
 * resultado esperado del caso.
 */
final class TcU02SecretoTotpTest extends TestCase
{
    private const string EMISOR = 'Firefly III';
    private const string CUENTA = 'qa-2fa@firefly.test';
    private string $secreto;
    private string $uri;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: instancia de Google2FA sin dependencias externas.
        $google2fa = new Google2FA();

        // Act.
        $this->secreto = $google2fa->generateSecretKey();
        $this->uri     = $google2fa->getQRCodeUrl(self::EMISOR, self::CUENTA, $this->secreto);
    }

    #[Test]
    public function paso_1_el_secreto_tiene_32_caracteres(): void
    {
        $this->assertSame(32, strlen($this->secreto));
    }

    #[Test]
    public function paso_2_el_secreto_solo_usa_el_alfabeto_base32(): void
    {
        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $this->secreto);
    }

    #[Test]
    public function paso_3_el_uri_empieza_con_otpauth_totp(): void
    {
        $this->assertStringStartsWith('otpauth://totp/', $this->uri);
    }

    #[Test]
    public function paso_3_el_uri_trae_el_emisor_firefly_iii(): void
    {
        $this->assertStringContainsString('issuer=Firefly%20III', $this->uri);
    }

    #[Test]
    public function paso_3_el_uri_trae_el_secreto_generado(): void
    {
        $this->assertStringContainsString('secret='.$this->secreto, $this->uri);
    }
}
