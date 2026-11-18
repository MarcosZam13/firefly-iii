<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-28 Ingreso queda clasificado como deposit. RF-07.
 * Técnica: partición de equivalencias, clase válida (tipo deposit).
 */
final class TcF28Test extends ApiTransaccionesTestCase
{
    private TestResponse $ingreso;
    private TestResponse $detalle;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta de activo con saldo inicial 1000.00 EUR.
        $this->prepararCuentaConSaldo('qa-f28@firefly.test');

        // Act (paso 1): ingreso de 100.00.
        $this->ingreso = $this->enviarIngreso('100.00');
        // Act (paso 2): consultar la transacción.
        $this->detalle = $this->consultarTransaccion((string) $this->ingreso->json('data.id'));
    }

    #[Test]
    public function paso_1_registro_del_ingreso_responde_http_200(): void
    {
        $this->ingreso->assertOk();
    }

    #[Test]
    public function paso_2_consulta_de_la_transaccion_responde_http_200(): void
    {
        $this->detalle->assertOk();
    }

    #[Test]
    public function paso_2_tipo_de_la_transaccion_es_deposit(): void
    {
        $this->assertSame('deposit', $this->detalle->json('data.attributes.transactions.0.type'));
    }
}