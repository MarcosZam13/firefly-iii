<?php

declare(strict_types=1);

namespace Tests\qa\Diana;

use Carbon\CarbonInterval;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use RuntimeException;
use Tests\qa\QaTestCase;

abstract class ApiDianaTestCase extends QaTestCase
{
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Passport::personalAccessTokensExpireIn(CarbonInterval::hours(1));
        $usuario = $this->crearUsuario('qa-diana@firefly.test');
        [$this->token] = $this->crearTokenPersonal($usuario);
    }

    protected function api(string $metodo, string $ruta, array $datos = []): TestResponse
    {
        // Cada petición debe resolver su usuario desde el token de nuevo.
        $this->volverAlGuardWeb();

        return $this->json($metodo, '/api/v1'.$ruta, $datos, $this->cabecerasApi($this->token));
    }

    protected function datosCuenta(string $tipo, string $nombre): array
    {
        $datos = ['name' => $nombre, 'type' => $tipo, 'currency_code' => 'EUR'];
        if ('asset' === $tipo) {
            $datos['account_role'] = 'defaultAsset';
        }
        if ('liability' === $tipo) {
            $datos += ['liability_type' => 'debt', 'liability_direction' => 'credit',
                'interest' => '0', 'interest_period' => 'monthly'];
        }

        return $datos;
    }

    protected function crearCuentaApi(): string
    {
        return $this->idDePrecondicion($this->api('POST', '/accounts', $this->datosCuenta('asset', 'Cuenta QA Diana')));
    }

    protected function crearPresupuestoApi(): string
    {
        return $this->idDePrecondicion($this->api('POST', '/budgets', ['name' => 'Presupuesto QA Diana', 'active' => true]));
    }

    protected function crearLimiteApi(string $presupuesto, string $monto = '500.00'): string
    {
        return $this->idDePrecondicion($this->api('POST', '/budgets/'.$presupuesto.'/limits', $this->datosLimite($monto)));
    }

    protected function datosGasto(string $cuenta, string $monto = '25.00', ?string $presupuesto = null): array
    {
        $transaccion = ['type' => 'withdrawal', 'date' => '2026-11-09', 'amount' => $monto,
            'description' => 'Gasto QA Diana', 'source_id' => $cuenta,
            'destination_name' => 'Comercio QA Diana', 'currency_code' => 'EUR'];
        if (null !== $presupuesto) {
            $transaccion['budget_id'] = $presupuesto;
        }

        return ['apply_rules' => false, 'fire_webhooks' => false, 'transactions' => [$transaccion]];
    }

    protected function registrarGastoApi(string $cuenta, string $monto = '25.00', ?string $presupuesto = null): string
    {
        return $this->idDePrecondicion($this->api('POST', '/transactions', $this->datosGasto($cuenta, $monto, $presupuesto)));
    }

    protected function gastoDelLimite(TestResponse $consulta): string
    {
        $filas = $consulta->json('data.attributes.spent');
        if (!is_array($filas)) {
            throw new RuntimeException('La consulta no devolvio el detalle spent del limite.');
        }
        $monto = '0.00';
        foreach ($filas as $fila) {
            if ('EUR' === $fila['currency_code']) {
                $monto = bcadd($monto, (string) $fila['sum'], 2);
            }
        }

        return $monto;
    }

    protected function disponibleDelLimite(TestResponse $consulta): string
    {
        return bcadd((string) $consulta->json('data.attributes.amount'), $this->gastoDelLimite($consulta), 2);
    }

    protected function datosLimite(string $monto): array
    {
        return ['amount' => $monto, 'start' => '2026-11-01', 'end' => '2026-11-30',
            'currency_code' => 'EUR', 'fire_webhooks' => false];
    }

    protected function idDePrecondicion(TestResponse $respuesta): string
    {
        $id = $respuesta->json('data.id');
        if (!in_array($respuesta->status(), [200, 201], true) || null === $id) {
            throw new RuntimeException('No se pudo crear el dato previo de QA. HTTP '.$respuesta->status());
        }

        return (string) $id;
    }
}
