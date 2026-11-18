<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-27 Ingreso con monto negativo o cero es rechazado. RF-07.
 * Técnica: valores límite (monto = -10 y monto = 0).
 */
final class TcF27Test extends ApiTransaccionesTestCase
{
    private TestResponse $ingresoNegativo;
    private TestResponse $ingresoCero;
    private TestResponse $saldo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta con saldo inicial 1000.00 EUR.
        $this->prepararCuentaConSaldo('qa-f27@firefly.test');

        // Act (paso 1): ingreso con monto -10.
        $this->ingresoNegativo = $this->enviarIngreso('-10');
        // Act (paso 2): ingreso con monto 0.
        $this->ingresoCero = $this->enviarIngreso('0');
        // Act (paso 3): saldo de la cuenta.
        $this->saldo = $this->consultarCuenta();
    }

    #[Test]
    public function paso_1_ingreso_negativo_responde_http_422(): void
    {
        $this->ingresoNegativo->assertStatus(422);
    }

    #[Test]
    public function paso_1_error_de_validacion_en_transactions_0_amount(): void
    {
        $this->ingresoNegativo->assertJsonValidationErrors(['transactions.0.amount']);
    }

    #[Test]
    public function paso_2_ingreso_en_cero_responde_http_422(): void
    {
        $this->ingresoCero->assertStatus(422);
    }

    #[Test]
    public function paso_2_error_de_validacion_en_transactions_0_amount(): void
    {
        $this->ingresoCero->assertJsonValidationErrors(['transactions.0.amount']);
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