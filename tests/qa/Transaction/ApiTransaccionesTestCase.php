<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Carbon\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\qa\QaTestCase;

/**
 * Base de los casos por API de transacciones y categorías (TC-F-21 en adelante).
 * Crea un usuario con token y una cuenta de activo, y ofrece helpers para
 * registrar, editar y eliminar transacciones y consultar cuentas e historial.
 * No es un caso de prueba: no se ejecuta solo ni se sube a QAtlas.
 */
abstract class ApiTransaccionesTestCase extends QaTestCase
{
    protected array $cabeceras = [];
    protected string $idCuenta = '';

    /** Usuario con token y cuenta de activo con saldo inicial (por defecto 1000.00 EUR). */
    protected function prepararCuentaConSaldo(string $correo, string $saldo = '1000.00'): void
    {
        $usuario         = $this->crearUsuario($correo);
        [$token]         = $this->crearTokenPersonal($usuario);
        $this->cabeceras = $this->cabecerasApi($token);

        $cuenta = $this->postJson('/api/v1/accounts', [
            'name'                 => 'Cuenta QA '.$correo,
            'type'                 => 'asset',
            'account_role'         => 'defaultAsset',
            'currency_code'        => 'EUR',
            'opening_balance'      => $saldo,
            'opening_balance_date' => '2026-01-01',
        ], $this->cabeceras);
        $this->idCuenta = (string) $cuenta->json('data.id');
    }

    /** Usuario con token y cuenta de activo sin ningún movimiento (ni saldo inicial), creada directo en la base. */
    protected function prepararCuentaSinMovimientos(string $correo): void
    {
        $usuario         = $this->crearUsuario($correo);
        [$token]         = $this->crearTokenPersonal($usuario);
        $this->cabeceras = $this->cabecerasApi($token);
        $this->idCuenta  = (string) $this->crearCuentaDeActivo($usuario, 'Cuenta QA '.$correo)->id;
    }

    /** POST /api/v1/transactions con los campos indicados (fecha de hoy y descripción por defecto). */
    protected function enviarTransaccion(array $campos): TestResponse
    {
        return $this->postJson('/api/v1/transactions', [
            'error_if_duplicate_hash' => false,
            'apply_rules'             => false,
            'fire_webhooks'           => false,
            'transactions'            => [array_merge([
                'date'        => Carbon::now()->format('Y-m-d'),
                'description' => 'Transaccion QA',
            ], $campos)],
        ], $this->cabeceras);
    }

    /** Gasto (withdrawal) desde la cuenta del caso. */
    protected function enviarGasto(string $monto): TestResponse
    {
        return $this->enviarTransaccion([
            'type'             => 'withdrawal',
            'amount'           => $monto,
            'description'      => 'Gasto QA',
            'source_id'        => $this->idCuenta,
            'destination_name' => 'Comercio QA',
        ]);
    }

    /** Ingreso (deposit) hacia la cuenta del caso. */
    protected function enviarIngreso(string $monto): TestResponse
    {
        return $this->enviarTransaccion([
            'type'           => 'deposit',
            'amount'         => $monto,
            'source_name'    => 'Empleador QA',
            'destination_id' => $this->idCuenta,
        ]);
    }

    /** PUT /api/v1/transactions/{id} cambiando solo el monto. */
    protected function editarTransaccion(string $id, string $monto): TestResponse
    {
        return $this->putJson('/api/v1/transactions/'.$id, [
            'apply_rules'   => false,
            'fire_webhooks' => false,
            'transactions'  => [['amount' => $monto]],
        ], $this->cabeceras);
    }

    protected function eliminarTransaccion(string $id): TestResponse
    {
        return $this->deleteJson('/api/v1/transactions/'.$id, [], $this->cabeceras);
    }

    protected function consultarTransaccion(string $id): TestResponse
    {
        return $this->getJson('/api/v1/transactions/'.$id, $this->cabeceras);
    }

    protected function consultarCuenta(): TestResponse
    {
        return $this->getJson('/api/v1/accounts/'.$this->idCuenta, $this->cabeceras);
    }

    /** GET /api/v1/accounts/{id}/transactions de la cuenta del caso. */
    protected function historialDeCuenta(): TestResponse
    {
        return $this->getJson('/api/v1/accounts/'.$this->idCuenta.'/transactions', $this->cabeceras);
    }

    /** GET /api/v1/summary/basic entre dos fechas (YYYY-MM-DD). */
    protected function resumenBasico(string $inicio, string $fin): TestResponse
    {
        return $this->getJson('/api/v1/summary/basic?start='.$inicio.'&end='.$fin, $this->cabeceras);
    }

    /** Petición genérica GET a la API con el token del caso (categorías, filtros, etc.). */
    protected function apiGet(string $ruta): TestResponse
    {
        return $this->getJson($ruta, $this->cabeceras);
    }

    /** Petición genérica POST a la API con el token del caso. */
    protected function apiPost(string $ruta, array $datos): TestResponse
    {
        return $this->postJson($ruta, $datos, $this->cabeceras);
    }

    protected function dosDecimales(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }
}