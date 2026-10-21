<?php

declare(strict_types=1);

namespace Tests\qa\Profile;

use FireflyIII\Models\Account;
use FireflyIII\User;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-19 Datos financieros tras eliminar el usuario. RF-06, RNF-03. Seguridad.
 * Técnica: partición de equivalencias. Las claves foráneas a users tienen
 * ON DELETE CASCADE: al borrar el usuario se borran sus cuentas, y otro
 * usuario no puede consultarlas.
 */
final class TcF19DatosTrasEliminarUsuarioTest extends QaTestCase
{
    private const string CORREO_A = 'qa-usuario-a@firefly.test';
    private int $idUsuarioA;
    private int $idCuentaA;
    private TestResponse $registroDelGasto;
    private TestResponse $eliminacion;
    private TestResponse $consultaDeB;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario A con 1 cuenta de activo y 1 gasto (registrado por la API con su token).
        $usuarioA         = $this->crearUsuario(self::CORREO_A);
        $this->idUsuarioA = $usuarioA->id;
        $this->idCuentaA  = $this->crearCuentaDeActivo($usuarioA, 'Cuenta de A')->id;
        [$tokenA]         = $this->crearTokenPersonal($usuarioA);
        $this->registroDelGasto = $this->postJson('/api/v1/transactions', [
            'transactions' => [[
                'type'             => 'withdrawal',
                'date'             => '2026-10-21',
                'amount'           => '12.50',
                'description'      => 'Gasto QA de A',
                'source_id'        => (string) $this->idCuentaA,
                'destination_name' => 'Supermercado QA',
            ]],
        ], $this->cabecerasApi($tokenA));
        $this->volverAlGuardWeb();

        // Arrange: usuario B con su propio token.
        $usuarioB = $this->crearUsuario('qa-usuario-b@firefly.test');
        [$tokenB] = $this->crearTokenPersonal($usuarioB);
        $this->volverAlGuardWeb();

        // Act paso 1: A inicia sesión y elimina su usuario.
        $this->iniciarSesion(self::CORREO_A);
        $this->eliminacion = $this->postJson('/profile/delete-account', ['password' => self::CLAVE_VALIDA]);
        $this->volverAlGuardWeb();

        // Act paso 3: B consulta la cuenta que era de A.
        $this->consultaDeB = $this->getJson('/api/v1/accounts/'.$this->idCuentaA, $this->cabecerasApi($tokenB));
    }

    #[Test]
    public function precondicion_el_gasto_de_a_quedo_registrado_http_200(): void
    {
        $this->registroDelGasto->assertOk();
    }

    #[Test]
    public function paso_1_eliminar_al_usuario_a_responde_http_302(): void
    {
        $this->eliminacion->assertStatus(302);
    }

    #[Test]
    public function paso_1_el_usuario_a_ya_no_existe(): void
    {
        $this->assertFalse(User::query()->whereKey($this->idUsuarioA)->exists());
    }

    #[Test]
    public function paso_2_no_quedan_cuentas_del_usuario_a(): void
    {
        $this->assertSame(0, Account::withTrashed()->where('user_id', $this->idUsuarioA)->count());
    }

    #[Test]
    public function paso_3_el_usuario_b_recibe_http_404_al_pedir_la_cuenta_de_a(): void
    {
        $this->consultaDeB->assertNotFound();
    }
}
