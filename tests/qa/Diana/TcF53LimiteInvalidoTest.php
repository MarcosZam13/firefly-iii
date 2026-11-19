<?php

declare(strict_types=1);

namespace Tests\qa\Diana;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-53, RF-18. Valores límite: negativo y cero, con fixtures independientes. */
final class TcF53LimiteInvalidoTest extends ApiDianaTestCase
{
    public static function montosInvalidos(): array
    {
        return ['negativo' => ['-50.00'], 'cero' => ['0.00']];
    }

    #[Test]
    #[DataProvider('montosInvalidos')]
    public function paso_1_y_2_monto_invalido_responde_http_422(string $monto): void
    {
        $id = $this->crearPresupuestoApi();
        $respuesta = $this->api('POST', '/budgets/'.$id.'/limits', $this->datosLimite($monto));
        $respuesta->assertStatus(422);
    }

    #[Test]
    #[DataProvider('montosInvalidos')]
    public function paso_1_y_2_error_identifica_el_monto(string $monto): void
    {
        $id = $this->crearPresupuestoApi();
        $respuesta = $this->api('POST', '/budgets/'.$id.'/limits', $this->datosLimite($monto));
        $respuesta->assertJsonValidationErrorFor('amount');
    }

    #[Test]
    #[DataProvider('montosInvalidos')]
    public function paso_3_no_se_guarda_un_limite_invalido(string $monto): void
    {
        $id = $this->crearPresupuestoApi();
        $this->api('POST', '/budgets/'.$id.'/limits', $this->datosLimite($monto));
        $respuesta = $this->api('GET', '/budgets/'.$id.'/limits');
        $this->assertSame([], $respuesta->json('data'));
    }
}
