<?php

declare(strict_types=1);

namespace Tests\qa\Category;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\Transaction\ApiTransaccionesTestCase;

/**
 * TC-F-41 Filtrar una categoría sin transacciones devuelve lista vacía. RF-08.
 * Técnica: valores límite (cero resultados).
 */
final class TcF41Test extends ApiTransaccionesTestCase
{
    private TestResponse $filtro;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: categoría sin transacciones.
        $this->prepararCuentaSinMovimientos('qa-f41@firefly.test');
        $idCategoria = (string) $this->apiPost('/api/v1/categories', ['name' => 'QA Vacia F41'])->json('data.id');

        // Act (paso 1): transacciones de la categoría.
        $this->filtro = $this->apiGet('/api/v1/categories/'.$idCategoria.'/transactions');
    }

    #[Test]
    public function paso_1_filtro_responde_http_200(): void
    {
        $this->filtro->assertOk();
    }

    #[Test]
    public function paso_2_data_es_una_lista_vacia(): void
    {
        $this->filtro->assertJsonCount(0, 'data');
    }

    #[Test]
    public function paso_2_total_de_paginacion_es_0(): void
    {
        $this->assertSame(0, (int) $this->filtro->json('meta.pagination.total'));
    }
}