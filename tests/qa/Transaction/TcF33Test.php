<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-33 Eliminar transacción revierte el saldo exactamente. RF-07.
 * Técnica: transición de estado (registrar y luego eliminar).
 */
final class TcF33Test extends ApiTransaccionesTestCase
{
    private TestResponse $gasto;
    private TestResponse $saldoConGasto;
    private TestResponse $eliminacion;
    private TestResponse $saldoFinal;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta de 1000.00 EUR con un gasto de 50.00.
        $this->prepararCuentaConSaldo('qa-f33@firefly.test');
        $this->gasto = $this->enviarGasto('50.00');

        // Act (paso 1): saldo con el gasto registrado.
        $this->saldoConGasto = $this->consultarCuenta();
        // Act (paso 2): eliminar el gasto.
        $this->eliminacion = $this->eliminarTransaccion((string) $this->gasto->json('data.id'));
        // Act (paso 3): saldo después de eliminar.
        $this->saldoFinal = $this->consultarCuenta();
    }

    #[Test]
    public function precondicion_gasto_de_50_creado_responde_http_200(): void
    {
        $this->gasto->assertOk();
    }

    #[Test]
    public function paso_1_consulta_responde_http_200(): void
    {
        $this->saldoConGasto->assertOk();
    }

    #[Test]
    public function paso_1_saldo_con_el_gasto_es_950_00(): void
    {
        $this->assertSame('950.00', $this->dosDecimales($this->saldoConGasto->json('data.attributes.current_balance')));
    }

    #[Test]
    public function paso_2_eliminacion_responde_http_204(): void
    {
        $this->eliminacion->assertNoContent();
    }

    #[Test]
    public function paso_3_consulta_final_responde_http_200(): void
    {
        $this->saldoFinal->assertOk();
    }

    #[Test]
    public function paso_3_saldo_vuelve_a_1000_00(): void
    {
        $this->assertSame('1000.00', $this->dosDecimales($this->saldoFinal->json('data.attributes.current_balance')));
    }
}