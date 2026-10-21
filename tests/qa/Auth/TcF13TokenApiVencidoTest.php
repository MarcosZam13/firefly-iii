<?php

declare(strict_types=1);

namespace Tests\qa\Auth;

use Carbon\CarbonInterval;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-13 Operación de la API con token vencido es rechazada. RF-04.
 * Técnica: valores límite (token con su vencimiento ya pasado).
 *
 * Hallazgo al automatizar: la precondición del caso (cambiar expires_at en
 * oauth_access_tokens) no vence el token. Passport valida la fecha "exp" que
 * va firmada dentro del JWT, no la columna de la tabla. Por eso el token se
 * emite con 1 segundo de vida y se espera a que venza de verdad.
 */
final class TcF13TokenApiVencidoTest extends QaTestCase
{
    private TestResponse $consultaConTokenVigente;
    private TestResponse $consultaConTokenVencido;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario con una cuenta de activo y un token personal de 1 s de vida.
        $usuario = $this->crearUsuario('qa-api@firefly.test');
        $this->crearCuentaDeActivo($usuario, 'Cuenta QA token vencido');
        Passport::personalAccessTokensExpireIn(CarbonInterval::seconds(1));
        [$token] = $this->crearTokenPersonal($usuario);

        // Control: con el token vigente la API sí responde (descarta un 401 por otra causa).
        $this->consultaConTokenVigente = $this->getJson('/api/v1/accounts', $this->cabecerasApi($token));
        $this->volverAlGuardWeb();

        // Arrange: se espera a que el token venza (reloj real: el JWT no usa el reloj de prueba).
        sleep(2);

        // Act (paso 1): GET /api/v1/accounts con el token vencido.
        $this->consultaConTokenVencido = $this->getJson('/api/v1/accounts', $this->cabecerasApi($token));
    }

    #[Test]
    public function control_token_vigente_responde_http_200(): void
    {
        $this->consultaConTokenVigente->assertOk();
    }

    #[Test]
    public function paso_1_token_vencido_responde_http_401(): void
    {
        $this->consultaConTokenVencido->assertUnauthorized();
    }

    #[Test]
    public function paso_2_respuesta_trae_el_campo_message(): void
    {
        $this->consultaConTokenVencido->assertJsonStructure(['message']);
    }

    #[Test]
    public function paso_2_respuesta_no_trae_datos_de_cuentas(): void
    {
        $this->consultaConTokenVencido->assertJsonMissingPath('data');
    }
}
