<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use Carbon\Carbon;
use FireflyIII\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-11 (FF-TC-11) Token de reseteo ya usado y token vencido son rechazados. RF-03. Seguridad.
 * Técnica: valores límite (expire = 60 min en config/auth.php).
 *
 * Tras un reseteo exitoso Firefly deja la sesión iniciada. Los pasos 2, 4 y 5
 * se envían como un navegador sin sesión (se cierra la sesión de la prueba
 * antes), que es como alguien reusaría el enlace del correo.
 */
final class TcF11ReseteoTokenUsadoOVencidoTest extends QaTestCase
{
    private const string CORREO   = 'qa-token@firefly.test';
    private const string CLAVE_P1 = 'Primera-Clave-P1-2026';
    private const string CLAVE_P2 = 'Segunda-Clave-P2-2026';
    private User $usuario;
    private TestResponse $reseteoValido;
    private TestResponse $reusoDelToken;
    private string $fechaTokenVencido;
    private TestResponse $reseteoVencido;
    private TestResponse $loginConP2;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario con token T1 recién generado.
        $this->usuario = $this->crearUsuario(self::CORREO);
        $tokenT1       = Password::broker()->createToken($this->usuario);

        // Act paso 1: reseteo válido con T1.
        $this->reseteoValido = $this->postJson('/password/reset', $this->datosReseteo($tokenT1, self::CLAVE_P1));

        // Act paso 2: reuso de T1, sin sesión.
        $this->cerrarSesionDeLaPrueba();
        $this->reusoDelToken = $this->postJson('/password/reset', $this->datosReseteo($tokenT1, self::CLAVE_P2));

        // Act paso 3: token T2 con created_at de hace 61 minutos.
        $tokenT2 = Password::broker()->createToken($this->usuario);
        DB::table('password_resets')->where('email', self::CORREO)->update(['created_at' => Carbon::now()->subMinutes(61)]);
        $this->fechaTokenVencido = (string) DB::table('password_resets')->where('email', self::CORREO)->value('created_at');

        // Act pasos 4 y 5.
        $this->cerrarSesionDeLaPrueba();
        $this->reseteoVencido = $this->postJson('/password/reset', $this->datosReseteo($tokenT2, self::CLAVE_P2));
        $this->cerrarSesionDeLaPrueba();
        $this->loginConP2 = $this->iniciarSesion(self::CORREO, self::CLAVE_P2);
    }

    #[Test]
    public function paso_1_reseteo_con_token_nuevo_responde_http_200(): void
    {
        $this->reseteoValido->assertOk();
    }

    #[Test]
    public function paso_1_la_contrasena_pasa_a_ser_p1(): void
    {
        $this->assertTrue(Hash::check(self::CLAVE_P1, $this->usuario->fresh()->password));
    }

    #[Test]
    public function paso_2_token_ya_usado_responde_http_422(): void
    {
        $this->reusoDelToken->assertStatus(422);
    }

    #[Test]
    public function paso_2_token_ya_usado_trae_mensaje_de_token_invalido(): void
    {
        $this->assertSame('This password reset token is invalid.', $this->reusoDelToken->json('errors.email.0'));
    }

    #[Test]
    public function paso_3_token_nuevo_queda_con_fecha_de_hace_61_minutos(): void
    {
        $this->assertSame(Carbon::now()->subMinutes(61)->format('Y-m-d H:i'), Carbon::parse($this->fechaTokenVencido)->format('Y-m-d H:i'));
    }

    #[Test]
    public function paso_4_token_vencido_responde_http_422(): void
    {
        $this->reseteoVencido->assertStatus(422);
    }

    #[Test]
    public function paso_4_token_vencido_trae_mensaje_de_token_invalido(): void
    {
        $this->assertSame('This password reset token is invalid.', $this->reseteoVencido->json('errors.email.0'));
    }

    #[Test]
    public function paso_5_login_con_p2_responde_http_422(): void
    {
        $this->loginConP2->assertStatus(422);
    }

    private function datosReseteo(string $token, string $clave): array
    {
        return ['token' => $token, 'email' => self::CORREO, 'password' => $clave, 'password_confirmation' => $clave];
    }

    private function cerrarSesionDeLaPrueba(): void
    {
        auth()->logout();
        $this->flushSession();
    }
}
