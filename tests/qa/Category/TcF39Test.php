<?php

declare(strict_types=1);

namespace Tests\qa\Category;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\Transaction\ApiTransaccionesTestCase;

/**
 * TC-F-39 Crear categoría con nombre duplicado es rechazada. RF-08.
 * Técnica: partición de equivalencias, clase inválida (nombre repetido).
 */
final class TcF39Test extends ApiTransaccionesTestCase
{
    private const string NOMBRE = 'QA Duplicada F39';

    private TestResponse $original;
    private TestResponse $duplicada;
    private TestResponse $lista;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: categoría "QA Duplicada F39" ya creada.
        $this->prepararCuentaSinMovimientos('qa-f39@firefly.test');
        $this->original = $this->apiPost('/api/v1/categories', ['name' => self::NOMBRE]);

        // Act (paso 1): crear otra categoría con el mismo nombre.
        $this->duplicada = $this->apiPost('/api/v1/categories', ['name' => self::NOMBRE]);
        // Act (paso 2): listar las categorías.
        $this->lista = $this->apiGet('/api/v1/categories');
    }

    #[Test]
    public function precondicion_categoria_original_creada_responde_http_200(): void
    {
        $this->original->assertOk();
    }

    #[Test]
    public function paso_1_nombre_duplicado_responde_http_422(): void
    {
        $this->duplicada->assertStatus(422);
    }

    #[Test]
    public function paso_1_error_de_validacion_en_el_campo_name(): void
    {
        $this->duplicada->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function paso_2_listado_responde_http_200(): void
    {
        $this->lista->assertOk();
    }

    #[Test]
    public function paso_2_lista_tiene_exactamente_1_categoria_con_ese_nombre(): void
    {
        $this->assertSame(1, collect($this->lista->json('data'))->where('attributes.name', self::NOMBRE)->count());
    }
}