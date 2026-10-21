<?php

declare(strict_types=1);

namespace Tests\qa\Profile;

use FireflyIII\Support\Facades\Preferences;
use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PragmaRX\Recovery\Recovery;
use Tests\qa\QaTestCase;

/**
 * TC-F-59 Login con código de respaldo de 2FA, que sirve una sola vez. RF-05. Seguridad.
 * Técnica: partición de equivalencias (código de respaldo vigente / ya usado).
 *
 * Los 8 códigos se generan con la misma librería y el mismo formato que usa
 * POST /mfa/backup-codes (Recovery: 8 códigos, 2 bloques de 6 caracteres).
 */
final class TcF59CodigoRespaldoUnSoloUsoTest extends QaTestCase
{
    private const string CORREO = 'qa-respaldo@firefly.test';
    private User $usuario;
    private string $codigoR;
    private TestResponse $login;
    private TestResponse $usoDelRespaldo;
    private mixed $avisoTrasRespaldo;
    private array $codigosRestantes;
    private TestResponse $logout;
    private TestResponse $segundoLogin;
    private TestResponse $reusoDelRespaldo;
    private mixed $errorTrasReuso;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario con 2FA activo y 8 códigos de respaldo; se toma uno, R.
        [$this->usuario] = $this->crearUsuarioCon2fa(self::CORREO);
        $codigos = (new Recovery())->lowercase()->setCount(8)->setBlocks(2)->setChars(6)->toArray();
        Preferences::setForUser($this->usuario, 'mfa_recovery', $codigos);
        $this->codigoR = $codigos[0];

        // Act pasos 1 y 2.
        $this->login             = $this->iniciarSesion(self::CORREO);
        $this->usoDelRespaldo    = $this->postJson('/two-factor/submit', ['one_time_password' => $this->codigoR]);
        $this->avisoTrasRespaldo = session('info');

        // Act paso 3.
        $this->codigosRestantes = $this->preferencia($this->usuario, 'mfa_recovery', []);

        // Act pasos 4 y 5.
        $this->logout           = $this->postJson('/logout');
        $this->segundoLogin     = $this->iniciarSesion(self::CORREO);
        $this->reusoDelRespaldo = $this->postJson('/two-factor/submit', ['one_time_password' => $this->codigoR]);
        $this->errorTrasReuso   = session('error');
    }

    #[Test]
    public function paso_1_login_con_clave_correcta_responde_http_204(): void
    {
        $this->login->assertNoContent();
    }

    #[Test]
    public function paso_2_codigo_de_respaldo_responde_http_302(): void
    {
        $this->usoDelRespaldo->assertStatus(302);
    }

    #[Test]
    public function paso_2_aviso_de_codigo_de_respaldo_usado(): void
    {
        $this->assertSame(
            "You have used a backup code to login to Firefly III. It can't be used again, so cross it from your list.",
            $this->avisoTrasRespaldo
        );
    }

    #[Test]
    public function paso_3_quedan_7_codigos_de_respaldo(): void
    {
        $this->assertCount(7, $this->codigosRestantes);
    }

    #[Test]
    public function paso_3_el_codigo_usado_ya_no_esta_en_la_lista(): void
    {
        $this->assertNotContains($this->codigoR, $this->codigosRestantes);
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
    public function paso_5_codigo_de_respaldo_reusado_responde_http_302(): void
    {
        $this->reusoDelRespaldo->assertStatus(302);
    }

    #[Test]
    public function paso_5_codigo_reusado_deja_el_error_de_codigo_invalido(): void
    {
        $this->assertSame('This MFA code is not valid.', $this->errorTrasReuso);
    }
}
