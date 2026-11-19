<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-32 Editar transacción dos veces no duplica el efecto sobre el saldo. RF-07.
 * Técnica: transición de estado (misma edición repetida, idempotencia).
 */
final class TcF32Test extends ApiTransaccionesTestCase
{
    private TestResponse $gasto;
    private TestResponse $primeraEdicion;
    private TestResponse $segundaEdicion;
    private TestResponse $saldo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta de 1000.00 EUR con un gasto de 100.00 (saldo actual 900.00).
        $this->prepararCuentaConSaldo('qa-f32@firefly.test');
        $this->gasto = $this->enviarGasto('100.00');
        $idGasto     = (string) $this->gasto->json('data.id');

        // Act (paso 1): editar el monto a 60.00.
        $this->primeraEdicion = $this->editarTransaccion($idGasto, '60.00');
        // Act (paso 2): repetir la misma edición.
        $this->segundaEdicion = $this->editarTransaccion($idGasto, '60.00');
        // Act (paso 3): saldo de la cuenta.
        $this->saldo = $this->consultarCuenta();
    }

    #[Test]
    public function precondicion_gasto_de_100_creado_responde_http_200(): void
    {
        $this->gasto->assertOk();
    }

    #[Test]
    public function paso_1_primera_edicion_responde_http_200(): void
    {
        $this->primeraEdicion->assertOk();
    }

    #[Test]
    public function paso_1_monto_queda_en_60_00(): void
    {
        $this->assertSame('60.00', $this->dosDecimales($this->primeraEdicion->json('data.attributes.transactions.0.amount')));
    }

    #[Test]
    public function paso_2_segunda_edicion_responde_http_200(): void
    {
        $this->segundaEdicion->assertOk();
    }

    #[Test]
    public function paso_2_monto_sigue_en_60_00(): void
    {
        $this->assertSame('60.00', $this->dosDecimales($this->segundaEdicion->json('data.attributes.transactions.0.amount')));
    }

    #[Test]
    public function paso_3_consulta_de_cuenta_responde_http_200(): void
    {
        $this->saldo->assertOk();
    }

    #[Test]
    public function paso_3_saldo_es_940_00(): void
    {
        $this->assertSame('940.00', $this->dosDecimales($this->saldo->json('data.attributes.current_balance')));
    }
}