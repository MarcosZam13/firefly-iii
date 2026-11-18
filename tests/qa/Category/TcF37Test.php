<?php

declare(strict_types=1);

namespace Tests\qa\Category;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\Transaction\ApiTransaccionesTestCase;

/**
 * TC-F-37 Crear categoría con nombre válido y único. RF-08.
 * Técnica: partición de equivalencias, clase válida (nombre nuevo).
 */
final class TcF37Test extends ApiTransaccionesTestCase
{
    private const string NOMBRE = 'QA Comida F37';

    private TestResponse $creacion;
    private TestResponse $lista;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario con token; el nombre no existe entre sus categorías.
        $this->prepararCuentaSinMovimientos('qa-f37@firefly.test');

        // Act (paso 1): crear la categoría.
        $this->creacion = $this->apiPost('/api/v1/categories', ['name' => self::NOMBRE]);
        // Act (paso 2): listar las categorías.
        $this->lista = $this->apiGet('/api/v1/categories');
    }

    #[Test]
    public function paso_1_creacion_responde_http_200(): void
    {
        $this->creacion->assertOk();
    }

    #[Test]
    public function paso_1_nombre_devuelto_es_qa_comida_f37(): void
    {
        $this->assertSame(self::NOMBRE, $this->creacion->json('data.attributes.name'));
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