<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-36 Transferencia entre monedas distintas exige el monto en la moneda destino. RF-07.
 * Técnica: tabla de decisión (con y sin foreign_amount entre cuentas EUR y USD).
 */
final class TcF36Test extends ApiTransaccionesTestCase
{
    private TestResponse $cuentaB;
    private TestResponse $sinMontoDestino;
    private TestResponse $saldoA;
    private TestResponse $conMontoDestino;
    private TestResponse $saldoB;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: moneda USD habilitada, cuenta A en EUR con 1000.00 y cuenta B en USD con 0.00.
        $this->prepararCuentaConSaldo('qa-f36@firefly.test');
        $this->apiPost('/api/v1/currencies/USD/enable', []);
        $this->cuentaB = $this->apiPost('/api/v1/accounts', [
            'name'          => 'Cuenta B USD',
            'type'          => 'asset',
            'account_role'  => 'defaultAsset',
            'currency_code' => 'USD',
        ]);
        $idB = (string) $this->cuentaB->json('data.id');

        // Act (paso 1): transferencia de 100.00 sin foreign_amount.
        $this->sinMontoDestino = $this->enviarTransaccion([
            'type'           => 'transfer',
            'amount'         => '100.00',
            'source_id'      => $this->idCuenta,
            'destination_id' => $idB,
        ]);
        // Act (paso 2): saldo de A.
        $this->saldoA = $this->consultarCuenta();
        // Act (paso 3): la misma transferencia con foreign_amount 108.00 USD.
        $this->conMontoDestino = $this->enviarTransaccion([
            'type'                  => 'transfer',
            'amount'                => '100.00',
            'source_id'             => $this->idCuenta,
            'destination_id'        => $idB,
            'foreign_amount'        => '108.00',
            'foreign_currency_code' => 'USD',
        ]);
        // Act (paso 4): saldo de B.
        $this->saldoB = $this->apiGet('/api/v1/accounts/'.$idB);
    }

    #[Test]
    public function precondicion_cuenta_b_en_usd_creada_responde_http_200(): void
    {
        $this->cuentaB->assertOk();
    }

    #[Test]
    public function paso_1_transferencia_sin_foreign_amount_responde_http_422(): void
    {
        $this->sinMontoDestino->assertStatus(422);
    }

    #[Test]
    public function paso_1_error_de_validacion_en_transactions_0_foreign_amount(): void
    {
        $this->sinMontoDestino->assertJsonValidationErrors(['transactions.0.foreign_amount']);
    }

    #[Test]
    public function paso_2_consulta_de_cuenta_a_responde_http_200(): void
    {
        $this->saldoA->assertOk();
    }

    #[Test]
    public function paso_2_saldo_de_a_sigue_en_1000_00(): void
    {
        $this->assertSame('1000.00', $this->dosDecimales($this->saldoA->json('data.attributes.current_balance')));
    }

    #[Test]
    public function paso_3_transferencia_con_foreign_amount_responde_http_200(): void
    {
        $this->conMontoDestino->assertOk();
    }

    #[Test]
    public function paso_3_monto_es_100_00(): void
    {
        $this->assertSame('100.00', $this->dosDecimales($this->conMontoDestino->json('data.attributes.transactions.0.amount')));
    }

    #[Test]
    public function paso_3_foreign_amount_es_108_00(): void
    {
        $this->assertSame('108.00', $this->dosDecimales($this->conMontoDestino->json('data.attributes.transactions.0.foreign_amount')));
    }

    #[Test]
    public function paso_4_consulta_de_cuenta_b_responde_http_200(): void
    {
        $this->saldoB->assertOk();
    }

    #[Test]
    public function paso_4_saldo_de_b_es_108_00(): void
    {
        $this->assertSame('108.00', $this->dosDecimales($this->saldoB->json('data.attributes.current_balance')));
    }
}