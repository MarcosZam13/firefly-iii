<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-26 Registrar ingreso válido aumenta el saldo exacto. RF-07.
 * Técnica: valores límite (ingreso con decimales sobre un saldo conocido).
 */
final class TcF26Test extends ApiTransaccionesTestCase
{
    private TestResponse $saldoInicial;
    private TestResponse $ingreso;
    private TestResponse $saldoFinal;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta con saldo inicial 1000.00 EUR.
        $this->prepararCuentaConSaldo('qa-f26@firefly.test');

        // Act (paso 1): saldo inicial.
        $this->saldoInicial = $this->consultarCuenta();
        // Act (paso 2): ingreso de 350.75.
        $this->ingreso = $this->enviarIngreso('350.75');
        // Act (paso 3): saldo final.
        $this->saldoFinal = $this->consultarCuenta();
    }

    #[Test]
    public function paso_1_consulta_inicial_responde_http_200(): void
    {
        $this->saldoInicial->assertOk();
    }

    #[Test]
    public function paso_1_saldo_inicial_es_1000_00(): void
    {
        $this->assertSame('1000.00', $this->dosDecimales($this->saldoInicial->json('data.attributes.current_balance')));
    }

    #[Test]
    public function paso_2_registro_del_ingreso_responde_http_200(): void
    {
        $this->ingreso->assertOk();
    }

    #[Test]
    public function paso_2_monto_del_ingreso_es_350_75(): void
    {
        $this->assertSame('350.75', $this->dosDecimales($this->ingreso->json('data.attributes.transactions.0.amount')));
    }

    #[Test]
    public function paso_3_consulta_final_responde_http_200(): void
    {
        $this->saldoFinal->assertOk();
    }

    #[Test]
    public function paso_3_saldo_final_es_1350_75(): void
    {
        $this->assertSame('1350.75', $this->dosDecimales($this->saldoFinal->json('data.attributes.current_balance')));
    }
}