<?php

declare(strict_types=1);

namespace Tests\qa\Transaction;

use Carbon\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-F-21 Registrar gasto válido disminuye el saldo exacto. RF-07.
 * Técnica: valores límite (gasto con decimales sobre un saldo conocido).
 */
final class TcF21Test extends QaTestCase
{
    private TestResponse $saldoInicial;
    private TestResponse $gasto;
    private TestResponse $saldoFinal;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: usuario con token y cuenta de activo con saldo inicial 1000.00 EUR.
        $usuario = $this->crearUsuario('qa-gasto@firefly.test');
        [$token] = $this->crearTokenPersonal($usuario);
        $cabeceras = $this->cabecerasApi($token);

        $cuenta = $this->postJson('/api/v1/accounts', [
            'name'                 => 'Cuenta QA gasto',
            'type'                 => 'asset',
            'account_role'         => 'defaultAsset',
            'currency_code'        => 'EUR',
            'opening_balance'      => '1000.00',
            'opening_balance_date' => '2026-01-01',
        ], $cabeceras);
        $idCuenta = $cuenta->json('data.id');

        // Act (paso 1): GET de la cuenta antes del gasto.
        $this->saldoInicial = $this->getJson('/api/v1/accounts/'.$idCuenta, $cabeceras);

        // Act (paso 2): POST del gasto de 25.50.
        $this->gasto = $this->postJson('/api/v1/transactions', [
            'error_if_duplicate_hash' => false,
            'apply_rules'             => false,
            'fire_webhooks'           => false,
            'transactions'            => [[
                'type'             => 'withdrawal',
                'date'             => Carbon::now()->format('Y-m-d'),
                'amount'           => '25.50',
                'description'      => 'Supermercado QA',
                'source_id'        => $idCuenta,
                'destination_name' => 'Supermercado QA',
            ]],
        ], $cabeceras);

        // Act (paso 3): GET de la cuenta después del gasto.
        $this->saldoFinal = $this->getJson('/api/v1/accounts/'.$idCuenta, $cabeceras);
    }

    private function dosDecimales(mixed $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    #[Test]
    public function paso_1_consulta_inicial_responde_http_200(): void
    {
        $this->saldoInicial->assertOk();
    }

    #[Test]
    public function paso_1_saldo_inicial_es_1000_00(): void
    {
        $this->assertSame('1000.00', $this->dosDecimales($this->saldoInicial->json('data.attributes.current_balance')));
    }

    #[Test]
    public function paso_2_registro_del_gasto_responde_http_200(): void
    {
        $this->gasto->assertOk();
    }

    #[Test]
    public function paso_2_tipo_de_la_transaccion_es_withdrawal(): void
    {
        $this->assertSame('withdrawal', $this->gasto->json('data.attributes.transactions.0.type'));
    }

    #[Test]
    public function paso_2_monto_de_la_transaccion_es_25_50(): void
    {
        $this->assertSame('25.50', $this->dosDecimales($this->gasto->json('data.attributes.transactions.0.amount')));
    }

    #[Test]
    public function paso_3_consulta_final_responde_http_200(): void
    {
        $this->saldoFinal->assertOk();
    }

    #[Test]
    public function paso_3_saldo_final_es_974_50(): void
    {
        $this->assertSame('974.50', $this->dosDecimales($this->saldoFinal->json('data.attributes.current_balance')));
    }
}