<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-25 Gasto registrado aparece en el historial con sus datos. RF-07.
 * Técnica: transición de estado (registrar y luego consultar).
 */
final class TcF25Test extends ApiTransaccionesTestCase
{
    private TestResponse $gasto;
    private TestResponse $historial;
    private TestResponse $detalle;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta de activo sin transacciones.
        $this->prepararCuentaSinMovimientos('qa-f25@firefly.test');

        // Act (paso 1): registrar el gasto de 12.34.
        $this->gasto = $this->enviarTransaccion([
            'type'             => 'withdrawal',
            'amount'           => '12.34',
            'description'      => 'QA historial F25',
            'date'             => '2026-10-01',
            'source_id'        => $this->idCuenta,
            'destination_name' => 'Comercio QA',
        ]);
        $idGasto = (string) $this->gasto->json('data.id');

        // Act (paso 2): historial de la cuenta.
        $this->historial = $this->getJson('/api/v1/accounts/'.$this->idCuenta.'/transactions', $this->cabeceras);
        // Act (paso 3): detalle de la transacción.
        $this->detalle = $this->consultarTransaccion($idGasto);
    }

    #[Test]
    public function paso_1_registro_del_gasto_responde_http_200(): void
    {
        $this->gasto->assertOk();
    }

    #[Test]
    public function paso_2_historial_responde_http_200(): void
    {
        $this->historial->assertOk();
    }

    #[Test]
    public function paso_2_historial_trae_exactamente_1_transaccion(): void
    {
        $this->historial->assertJsonCount(1, 'data');
    }

    #[Test]
    public function paso_2_transaccion_del_historial_es_la_del_paso_1(): void
    {
        $this->assertSame((string) $this->gasto->json('data.id'), (string) $this->historial->json('data.0.id'));
    }

    #[Test]
    public function paso_3_detalle_responde_http_200(): void
    {
        $this->detalle->assertOk();
    }

    #[Test]
    public function paso_3_fecha_es_2026_10_01(): void
    {
        $this->assertStringStartsWith('2026-10-01', (string) $this->detalle->json('data.attributes.transactions.0.date'));
    }

    #[Test]
    public function paso_3_monto_es_12_34(): void
    {
        $this->assertSame('12.34', $this->dosDecimales($this->detalle->json('data.attributes.transactions.0.amount')));
    }

    #[Test]
    public function paso_3_descripcion_es_qa_historial_f25(): void
    {
        $this->assertSame('QA historial F25', $this->detalle->json('data.attributes.transactions.0.description'));
    }
}