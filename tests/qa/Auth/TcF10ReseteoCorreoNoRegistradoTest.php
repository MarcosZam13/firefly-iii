<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-10 (FF-TC-10) Solicitar reseteo con correo no registrado. RF-03. Seguridad.
 * Técnica: partición de equivalencias, clase inválida. La respuesta no debe
 * revelar si el correo existe.
 */
final class TcF10ReseteoCorreoNoRegistradoTest extends QaTestCase
{
    private const string CORREO = 'noexiste@firefly.test';
    private TestResponse $solicitud;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: ninguna cuenta con ese correo (base nueva).

        // Act (paso 1).
        $this->solicitud = $this->postJson('/password/email', ['email' => self::CORREO]);
    }

    #[Test]
    public function paso_1_solicitud_responde_http_302(): void
    {
        $this->solicitud->assertStatus(302);
    }

    #[Test]
    public function paso_2_mensaje_es_el_mismo_que_para_un_correo_registrado(): void
    {
        $this->solicitud->assertSessionHas('status', 'Thank you. If an account exists with this email address, you will find instructions in your inbox.');
    }

    #[Test]
    public function paso_3_no_se_crea_ningun_token(): void
    {
        $this->assertSame(0, DB::table('password_resets')->where('email', self::CORREO)->count());
    }
}
