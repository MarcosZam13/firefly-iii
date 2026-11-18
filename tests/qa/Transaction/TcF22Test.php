<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-22 Gasto con monto negativo es rechazado. RF-07.
 * Técnica: partición de equivalencias, clase inválida (monto < 0).
 */
final class TcF22Test extends ApiTransaccionesTestCase
{
    private TestResponse $gasto;
    private TestResponse $saldo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta con saldo inicial 1000.00 EUR.
        $this->prepararCuentaConSaldo('qa-f22@firefly.test');

        // Act (paso 1): gasto con monto -10.
        $this->gasto = $this->enviarGasto('-10');
        // Act (paso 2): consultar la cuenta.
        $this->saldo = $this->consultarCuenta();
    }

    #[Test]
    public function paso_1_gasto_negativo_responde_http_422(): void
    {
        $this->gasto->assertStatus(422);
    }

    #[Test]
    public function paso_1_error_de_validacion_en_transactions_0_amount(): void
    {
        $this->gasto->assertJsonValidationErrors(['transactions.0.amount']);
    }

    #[Test]
    public function paso_2_consulta_de_cuenta_responde_http_200(): void
    {
        $this->saldo->assertOk();
    }

    #[Test]
    public function paso_2_saldo_sigue_en_1000_00(): void
    {
        $this->assertSame('1000.00', $this->dosDecimales($this->saldo->json('data.attributes.current_balance')));
    }
}