<?php

declare(strict_types=1);

namespace Tests\qa\Category;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\Transaction\ApiTransaccionesTestCase;

/**
 * TC-F-38 Una transacción admite una sola categoría a la vez. RF-08.
 * Técnica: transición de estado (asignar C1 y luego C2 a la misma transacción).
 */
final class TcF38Test extends ApiTransaccionesTestCase
{
    private TestResponse $gasto;
    private TestResponse $categoria1;
    private TestResponse $categoria2;
    private TestResponse $asignacion1;
    private TestResponse $asignacion2;
    private TestResponse $detalle;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: gasto sin categoría y categorías C1 y C2.
        $this->prepararCuentaConSaldo('qa-f38@firefly.test');
        $this->gasto      = $this->enviarGasto('30.00');
        $this->categoria1 = $this->apiPost('/api/v1/categories', ['name' => 'QA Categoria C1 F38']);
        $this->categoria2 = $this->apiPost('/api/v1/categories', ['name' => 'QA Categoria C2 F38']);
        $idGasto          = (string) $this->gasto->json('data.id');

        // Act (paso 1): asignar C1.
        $this->asignacion1 = $this->asignarCategoria($idGasto, (string) $this->categoria1->json('data.id'));
        // Act (paso 2): asignar C2.
        $this->asignacion2 = $this->asignarCategoria($idGasto, (string) $this->categoria2->json('data.id'));
        // Act (paso 3): consultar la transacción.
        $this->detalle = $this->consultarTransaccion($idGasto);
    }

    private function asignarCategoria(string $idGasto, string $idCategoria): TestResponse
    {
        return $this->putJson('/api/v1/transactions/'.$idGasto, [
            'apply_rules'   => false,
            'fire_webhooks' => false,
            'transactions'  => [['category_id' => $idCategoria]],
        ], $this->cabeceras);
    }

    #[Test]
    public function precondicion_gasto_sin_categoria_creado_responde_http_200(): void
    {
        $this->gasto->assertOk();
    }

    #[Test]
    public function precondicion_categoria_c1_creada_responde_http_200(): void
    {
        $this->categoria1->assertOk();
    }

    #[Test]
    public function precondicion_categoria_c2_creada_responde_http_200(): void
    {
        $this->categoria2->assertOk();
    }

    #[Test]
    public function paso_1_asignar_c1_responde_http_200(): void
    {
        $this->asignacion1->assertOk();
    }

    #[Test]
    public function paso_1_category_id_es_c1(): void
    {
        $this->assertSame((string) $this->categoria1->json('data.id'), (string) $this->asignacion1->json('data.attributes.transactions.0.category_id'));
    }

    #[Test]
    public function paso_2_asignar_c2_responde_http_200(): void
    {
        $this->asignacion2->assertOk();
    }

    #[Test]
    public function paso_2_category_id_es_c2(): void
    {
        $this->assertSame((string) $this->categoria2->json('data.id'), (string) $this->asignacion2->json('data.attributes.transactions.0.category_id'));
    }

    #[Test]
    public function paso_3_consulta_responde_http_200(): void
    {
        $this->detalle->assertOk();
    }

    #[Test]
    public function paso_3_category_id_guardado_es_c2(): void
    {
        $this->assertSame((string) $this->categoria2->json('data.id'), (string) $this->detalle->json('data.attributes.transactions.0.category_id'));
    }
}