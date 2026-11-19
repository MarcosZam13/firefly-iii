<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-31 Editar transacción a monto cero o negativo es rechazado. RF-07.
 * Técnica: valores límite (monto = 0 y monto = -5 al editar).
 */
final class TcF31Test extends ApiTransaccionesTestCase
{
    private TestResponse $gasto;
    private TestResponse $edicionCero;
    private TestResponse $edicionNegativa;
    private TestResponse $detalle;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta con saldo y un gasto de 80.00.
        $this->prepararCuentaConSaldo('qa-f31@firefly.test');
        $this->gasto = $this->enviarGasto('80.00');
        $idGasto     = (string) $this->gasto->json('data.id');

        // Act (paso 1): editar el monto a 0.
        $this->edicionCero = $this->editarTransaccion($idGasto, '0');
        // Act (paso 2): editar el monto a -5.
        $this->edicionNegativa = $this->editarTransaccion($idGasto, '-5');
        // Act (paso 3): consultar la transacción.
        $this->detalle = $this->consultarTransaccion($idGasto);
    }

    #[Test]
    public function precondicion_gasto_de_80_creado_responde_http_200(): void
    {
        $this->gasto->assertOk();
    }

    #[Test]
    public function paso_1_edicion_a_cero_responde_http_422(): void
    {
        $this->edicionCero->assertStatus(422);
    }

    #[Test]
    public function paso_1_error_de_validacion_en_transactions_0_amount(): void
    {
        $this->edicionCero->assertJsonValidationErrors(['transactions.0.amount']);
    }

    #[Test]
    public function paso_2_edicion_a_negativo_responde_http_422(): void
    {
        $this->edicionNegativa->assertStatus(422);
    }

    #[Test]
    public function paso_2_error_de_validacion_en_transactions_0_amount(): void
    {
        $this->edicionNegativa->assertJsonValidationErrors(['transactions.0.amount']);
    }

    #[Test]
    public function paso_3_consulta_de_la_transaccion_responde_http_200(): void
    {
        $this->detalle->assertOk();
    }

    #[Test]
    public function paso_3_monto_sigue_en_80_00(): void
    {
        $this->assertSame('80.00', $this->dosDecimales($this->detalle->json('data.attributes.transactions.0.amount')));
    }
}