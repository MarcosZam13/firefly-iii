<?php

declare(strict_types=1);

namespace Tests\qa\Category;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\Transaction\ApiTransaccionesTestCase;

/**
 * TC-F-42 Filtro por categoría con rango de fechas en los bordes. RF-08.
 * Técnica: valores límite (fechas en el borde inferior, superior y justo afuera del rango).
 */
final class TcF42Test extends ApiTransaccionesTestCase
{
    private array $gastos = [];
    private TestResponse $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: categoría con 3 gastos del 2026-10-01, 2026-10-31 y 2026-11-01.
        $this->prepararCuentaSinMovimientos('qa-f42@firefly.test');
        $idCategoria = (string) $this->apiPost('/api/v1/categories', ['name' => 'QA Bordes F42'])->json('data.id');
        foreach (['2026-10-01', '2026-10-31', '2026-11-01'] as $fecha) {
            $this->gastos[] = $this->enviarTransaccion([
                'type'             => 'withdrawal',
                'amount'           => '10.00',
                'date'             => $fecha,
                'source_id'        => $this->idCuenta,
                'destination_name' => 'Comercio QA',
                'category_id'      => $idCategoria,
            ]);
        }

        // Act (paso 1): transacciones de la categoría entre 2026-10-01 y 2026-10-31.
        $this->filtro = $this->apiGet('/api/v1/categories/'.$idCategoria.'/transactions?start=2026-10-01&end=2026-10-31');
    }

    private function fechasDevueltas(): array
    {
        $fechas = collect($this->filtro->json('data'))
            ->map(static fn (array $g): string => substr((string) $g['attributes']['transactions'][0]['date'], 0, 10))
            ->sort()
            ->values()
            ->all();

        return $fechas;
    }

    #[Test]
    public function precondicion_los_3_gastos_se_crearon_con_http_200(): void
    {
        $this->assertSame([200, 200, 200], array_map(static fn (TestResponse $r): int => $r->getStatusCode(), $this->gastos));
    }

    #[Test]
    public function paso_1_filtro_responde_http_200(): void
    {
        $this->filtro->assertOk();
    }

    #[Test]
    public function paso_1_lista_trae_exactamente_2_transacciones(): void
    {
        $this->filtro->assertJsonCount(2, 'data');
    }

    #[Test]
    public function paso_2_las_fechas_devueltas_son_2026_10_01_y_2026_10_31(): void
    {
        $this->assertSame(['2026-10-01', '2026-10-31'], $this->fechasDevueltas());
    }

    #[Test]
    public function paso_2_la_fecha_2026_11_01_no_aparece(): void
    {
        $this->assertNotContains('2026-11-01', $this->fechasDevueltas());
    }
}