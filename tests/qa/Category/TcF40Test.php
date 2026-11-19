<?php

declare(strict_types=1);

namespace Tests\qa\Category;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\Transaction\ApiTransaccionesTestCase;

/**
 * TC-F-40 Filtrar transacciones por categoría devuelve solo las asignadas. RF-08.
 * Técnica: partición de equivalencias (categoría con 2 gastos y otra con 1).
 */
final class TcF40Test extends ApiTransaccionesTestCase
{
    private array $gastos = [];
    private string $idCategoria1 = '';
    private string $idGastoDeC2 = '';
    private TestResponse $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: C1 con 2 gastos y C2 con 1 gasto.
        $this->prepararCuentaSinMovimientos('qa-f40@firefly.test');
        $this->idCategoria1 = (string) $this->apiPost('/api/v1/categories', ['name' => 'QA Categoria C1 F40'])->json('data.id');
        $idCategoria2       = (string) $this->apiPost('/api/v1/categories', ['name' => 'QA Categoria C2 F40'])->json('data.id');

        $this->gastos[] = $this->gastoEnCategoria('10.00', $this->idCategoria1);
        $this->gastos[] = $this->gastoEnCategoria('20.00', $this->idCategoria1);
        $gastoC2        = $this->gastoEnCategoria('30.00', $idCategoria2);
        $this->gastos[] = $gastoC2;
        $this->idGastoDeC2 = (string) $gastoC2->json('data.id');

        // Act (paso 1): transacciones de la categoría C1.
        $this->filtro = $this->apiGet('/api/v1/categories/'.$this->idCategoria1.'/transactions');
    }

    private function gastoEnCategoria(string $monto, string $idCategoria): TestResponse
    {
        return $this->enviarTransaccion([
            'type'             => 'withdrawal',
            'amount'           => $monto,
            'source_id'        => $this->idCuenta,
            'destination_name' => 'Comercio QA',
            'category_id'      => $idCategoria,
        ]);
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
    public function paso_2_todas_las_transacciones_son_de_c1(): void
    {
        $ids = collect($this->filtro->json('data'))
            ->map(static fn (array $g): string => (string) $g['attributes']['transactions'][0]['category_id'])
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$this->idCategoria1], $ids);
    }

    #[Test]
    public function paso_2_el_gasto_de_c2_no_aparece(): void
    {
        $this->assertNotContains($this->idGastoDeC2, collect($this->filtro->json('data'))->pluck('id')->map(static fn ($id): string => (string) $id)->all());
    }
}