<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-04 Bloqueo tras intentos fallidos consecutivos. RF-01. Seguridad.
 * Técnica: partición de equivalencias. Firefly suma 2 al limitador por cada
 * fallo (al entrar y al fallar) con un máximo de 5, así que el tercer intento
 * queda bloqueado aunque la contraseña sea correcta.
 *
 * Firefly hace sleep() real hasta que el bloqueo vence (hasta 60 s). Para no
 * esperar un minuto, después de los 2 fallos se adelanta el reloj de prueba
 * 55 s: el bloqueo dura ~5 s reales. Al terminar el paso 3 se adelanta 10 s
 * más, que es lo que en la ejecución manual pasa mientras el servidor espera.
 */
final class TcF04BloqueoFuerzaBrutaTest extends QaTestCase
{
    private const string CORREO = 'qa-bloqueo@firefly.test';
    private TestResponse $primerFallo;
    private TestResponse $segundoFallo;
    private TestResponse $tercerIntento;
    private TestResponse $intentoTrasBloqueo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario activo, limitador en cero (caché nueva por prueba).
        $this->crearUsuario(self::CORREO);

        // Act: pasos 1 a 4.
        $this->primerFallo  = $this->iniciarSesion(self::CORREO, 'Clave-Incorrecta-2026');
        $this->segundoFallo = $this->iniciarSesion(self::CORREO, 'Clave-Incorrecta-2026');
        $this->travel(55)->seconds();
        $this->tercerIntento = $this->iniciarSesion(self::CORREO);
        $this->travel(10)->seconds();
        $this->intentoTrasBloqueo = $this->iniciarSesion(self::CORREO);
    }

    #[Test]
    public function paso_1_primer_fallo_responde_http_422(): void
    {
        $this->primerFallo->assertStatus(422);
    }

    #[Test]
    public function paso_2_segundo_fallo_responde_http_422(): void
    {
        $this->segundoFallo->assertStatus(422);
    }

    #[Test]
    public function paso_3_tercer_intento_con_clave_correcta_responde_http_429(): void
    {
        $this->tercerIntento->assertStatus(429);
    }

    #[Test]
    public function paso_4_pasado_el_bloqueo_login_responde_http_204(): void
    {
        $this->intentoTrasBloqueo->assertNoContent();
    }
}
