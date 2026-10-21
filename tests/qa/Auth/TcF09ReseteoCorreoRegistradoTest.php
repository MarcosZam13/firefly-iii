<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-09 Solicitar reseteo de contraseña con correo registrado. RF-03.
 * Técnica: partición de equivalencias, clase válida.
 */
final class TcF09ReseteoCorreoRegistradoTest extends QaTestCase
{
    private const string CORREO = 'qa-reset@firefly.test';
    private TestResponse $solicitud;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario registrado, sin tokens previos. APP_URL = http://localhost, el mismo host de la prueba.
        $this->crearUsuario(self::CORREO);

        // Act (paso 1).
        $this->solicitud = $this->postJson('/password/email', ['email' => self::CORREO]);
    }

    #[Test]
    public function paso_1_solicitud_responde_http_302(): void
    {
        $this->solicitud->assertStatus(302);
    }

    #[Test]
    public function paso_2_mensaje_de_estado_es_el_generico(): void
    {
        $this->solicitud->assertSessionHas('status', 'Thank you. If an account exists with this email address, you will find instructions in your inbox.');
    }

    #[Test]
    public function paso_3_queda_un_token_para_el_correo(): void
    {
        $this->assertSame(1, DB::table('password_resets')->where('email', self::CORREO)->count());
    }
}
