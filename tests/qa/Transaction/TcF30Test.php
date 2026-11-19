<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-30 Transferencia con cuenta inexistente o ajena es rechazada. RF-07.
 * Técnica: partición de equivalencias, clases inválidas (cuenta inexistente y cuenta de otro usuario).
 */
final class TcF30Test extends ApiTransaccionesTestCase
{
    private TestResponse $haciaInexistente;
    private TestResponse $haciaAjena;
    private TestResponse $saldo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario A con cuenta de 1000.00 EUR y usuario B con su propia cuenta.
        $this->prepararCuentaConSaldo('qa-f30-a@firefly.test');
        $usuarioB = $this->crearUsuario('qa-f30-b@firefly.test');
        $cuentaB  = $this->crearCuentaDeActivo($usuarioB, 'Cuenta de B');

        // Act (paso 1): transferencia hacia la cuenta 999999, que no existe.
        $this->haciaInexistente = $this->enviarTransaccion([
            'type'           => 'transfer',
            'amount'         => '50.00',
            'source_id'      => $this->idCuenta,
            'destination_id' => '999999',
        ]);
        // Act (paso 2): transferencia hacia la cuenta de B.
        $this->haciaAjena = $this->enviarTransaccion([
            'type'           => 'transfer',
            'amount'         => '50.00',
            'source_id'      => $this->idCuenta,
            'destination_id' => (string) $cuentaB->id,
        ]);
        // Act (paso 3): saldo de la cuenta de A.
        $this->saldo = $this->consultarCuenta();
    }

    #[Test]
    public function paso_1_destino_inexistente_responde_http_422(): void
    {
        $this->haciaInexistente->assertStatus(422);
    }

    #[Test]
    public function paso_1_error_en_la_cuenta_destino(): void
    {
        $this->haciaInexistente->assertJsonValidationErrors(['transactions.0.destination_id']);
    }

    #[Test]
    public function paso_2_destino_ajeno_responde_http_422(): void
    {
        $this->haciaAjena->assertStatus(422);
    }

    #[Test]
    public function paso_2_error_en_la_cuenta_destino(): void
    {
        $this->haciaAjena->assertJsonValidationErrors(['transactions.0.destination_id']);
    }

    #[Test]
    public function paso_3_consulta_de_cuenta_responde_http_200(): void
    {
        $this->saldo->assertOk();
    }

    #[Test]
    public function paso_3_saldo_sigue_en_1000_00(): void
    {
        $this->assertSame('1000.00', $this->dosDecimales($this->saldo->json('data.attributes.current_balance')));
    }
}