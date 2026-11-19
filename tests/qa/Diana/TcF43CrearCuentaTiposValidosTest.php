<?php

declare(strict_types=1);

namespace Tests\qa\Diana;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-43, RF-15. Creación por API de los cuatro tipos del plan entregado. */
final class TcF43CrearCuentaTiposValidosTest extends ApiDianaTestCase
{
    public static function tiposValidos(): array
    {
        return ['activo' => ['asset'], 'pasivo' => ['liability'],
            'gasto' => ['expense'], 'ingreso' => ['revenue']];
    }

    #[Test]
    #[DataProvider('tiposValidos')]
    public function paso_1_crear_cuenta_responde_http_200(string $tipo): void
    {
        $respuesta = $this->api('POST', '/accounts', $this->datosCuenta($tipo, 'Cuenta QA '.$tipo));
        $respuesta->assertStatus(200);
    }

    #[Test]
    #[DataProvider('tiposValidos')]
    public function paso_3_consultar_cuenta_conserva_el_tipo_solicitado(string $tipo): void
    {
        $id = $this->idDePrecondicion($this->api('POST', '/accounts', $this->datosCuenta($tipo, 'Cuenta QA '.$tipo)));
        $respuesta = $this->api('GET', '/accounts/'.$id);
        // La API acepta "liability" al crear y serializa "liabilities" al consultar
        // (config/firefly.php, shortNamesByFullName). Ambos designan un pasivo.
        $tipoEsperado = 'liability' === $tipo ? 'liabilities' : $tipo;
        $this->assertSame($tipoEsperado, $respuesta->json('data.attributes.type'));
    }

    #[Test]
    #[DataProvider('tiposValidos')]
    public function paso_3_consultar_cuenta_conserva_el_nombre(string $tipo): void
    {
        $nombre = 'Cuenta QA '.$tipo;
        $id = $this->idDePrecondicion($this->api('POST', '/accounts', $this->datosCuenta($tipo, $nombre)));
        $respuesta = $this->api('GET', '/accounts/'.$id);
        $this->assertSame($nombre, $respuesta->json('data.attributes.name'));
    }
}
