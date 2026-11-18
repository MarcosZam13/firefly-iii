<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-29 Transferencia con origen y destino iguales es rechazada. RF-07.
 * Técnica: partición de equivalencias, clase inválida (origen = destino).
 */
final class TcF29Test extends ApiTransaccionesTestCase
{
    private TestResponse $transferencia;
    private TestResponse $saldo;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta con saldo inicial 1000.00 EUR.
        $this->prepararCuentaConSaldo('qa-f29@firefly.test');

        // Act (paso 1): transferencia de 50.00 de la cuenta a sí misma.
        $this->transferencia = $this->enviarTransaccion([
            'type'           => 'transfer',
            'amount'         => '50.00',
            'source_id'      => $this->idCuenta,
            'destination_id' => $this->idCuenta,
        ]);
        // Act (paso 2): saldo de la cuenta.
        $this->saldo = $this->consultarCuenta();
    }

    #[Test]
    public function paso_1_transferencia_a_la_misma_cuenta_responde_http_422(): void
    {
        $this->transferencia->assertStatus(422);
    }

    #[Test]
    public function paso_1_errores_traen_source_and_destination_are_the_same(): void
    {
        $this->assertStringContainsString('Source and destination are the same.', $this->transferencia->getContent());
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