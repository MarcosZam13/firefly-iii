<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-24 Gasto con el monto positivo mínimo es aceptado. RF-07.
 * Técnica: valores límite (monto = 0.01).
 */
final class TcF24Test extends ApiTransaccionesTestCase
{
    private TestResponse $gasto;
    private TestResponse $saldo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta con saldo inicial 1000.00 EUR.
        $this->prepararCuentaConSaldo('qa-f24@firefly.test');

        // Act (paso 1): gasto con monto 0.01.
        $this->gasto = $this->enviarGasto('0.01');
        // Act (paso 2): consultar la cuenta.
        $this->saldo = $this->consultarCuenta();
    }

    #[Test]
    public function paso_1_gasto_minimo_responde_http_200(): void
    {
        $this->gasto->assertOk();
    }

    #[Test]
    public function paso_1_monto_de_la_transaccion_es_0_01(): void
    {
        $this->assertSame('0.01', $this->dosDecimales($this->gasto->json('data.attributes.transactions.0.amount')));
    }

    #[Test]
    public function paso_2_consulta_de_cuenta_responde_http_200(): void
    {
        $this->saldo->assertOk();
    }

    #[Test]
    public function paso_2_saldo_es_999_99(): void
    {
        $this->assertSame('999.99', $this->dosDecimales($this->saldo->json('data.attributes.current_balance')));
    }
}