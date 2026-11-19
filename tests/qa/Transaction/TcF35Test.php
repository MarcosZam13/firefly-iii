<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/**
 * TC-F-35 Transacción eliminada no aparece en el historial ni en el resumen. RF-07.
 * Técnica: transición de estado (eliminar y revisar historial y resumen).
 */
final class TcF35Test extends ApiTransaccionesTestCase
{
    private TestResponse $gasto;
    private TestResponse $resumenAntes;
    private TestResponse $eliminacion;
    private TestResponse $historial;
    private TestResponse $resumenDespues;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuenta sin movimientos con un único gasto de 40.00 del 2026-10-05.
        $this->prepararCuentaSinMovimientos('qa-f35@firefly.test');
        $this->gasto = $this->enviarTransaccion([
            'type'             => 'withdrawal',
            'amount'           => '40.00',
            'date'             => '2026-10-05',
            'source_id'        => $this->idCuenta,
            'destination_name' => 'Comercio QA',
        ]);

        // Act (paso 1): resumen de octubre antes de eliminar.
        $this->resumenAntes = $this->resumenBasico('2026-10-01', '2026-10-31');
        // Act (paso 2): eliminar el gasto.
        $this->eliminacion = $this->eliminarTransaccion((string) $this->gasto->json('data.id'));
        // Act (paso 3): historial de la cuenta.
        $this->historial = $this->historialDeCuenta();
        // Act (paso 4): resumen de octubre después de eliminar.
        $this->resumenDespues = $this->resumenBasico('2026-10-01', '2026-10-31');
    }

    #[Test]
    public function precondicion_gasto_de_40_creado_responde_http_200(): void
    {
        $this->gasto->assertOk();
    }

    #[Test]
    public function paso_1_resumen_inicial_responde_http_200(): void
    {
        $this->resumenAntes->assertOk();
    }

    #[Test]
    public function paso_2_eliminacion_responde_http_204(): void
    {
        $this->eliminacion->assertNoContent();
    }

    #[Test]
    public function paso_3_historial_responde_http_200(): void
    {
        $this->historial->assertOk();
    }

    #[Test]
    public function paso_3_historial_queda_vacio(): void
    {
        $this->historial->assertJsonCount(0, 'data');
    }

    #[Test]
    public function paso_4_gastado_baja_exactamente_40_00(): void
    {
        $antes   = abs((float) $this->resumenAntes->json('spent-in-EUR.monetary_value'));
        $despues = abs((float) $this->resumenDespues->json('spent-in-EUR.monetary_value'));

        $this->assertSame('40.00', $this->dosDecimales($antes - $despues));
    }
}