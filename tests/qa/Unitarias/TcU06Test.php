<?php

declare(strict_types=1);

namespace Tests\qa\Unitarias;

use Carbon\Carbon;
use FireflyIII\Models\CurrencyExchangeRate;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Models\UserGroup;
use FireflyIII\Support\Http\Api\ExchangeRateConverter;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-U-06 Conversión de moneda sin error de redondeo visible. RF-12.
 * Técnica: valores límite sobre la precisión decimal de ExchangeRateConverter::convert (33.33 x 1.08347).
 */
final class TcU06Test extends QaTestCase
{
    private string $primerResultado;
    private array $diezResultados = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: tasa EUR a USD de 1.08347 para 2026-10-01; USD con 2 decimales.
        $usuario = $this->crearUsuario('qa-u06@firefly.test');
        $euro    = TransactionCurrency::query()->where('code', 'EUR')->first();
        $dolar   = TransactionCurrency::query()->where('code', 'USD')->first();
        CurrencyExchangeRate::create([
            'user_id'          => $usuario->id,
            'user_group_id'    => $usuario->user_group_id,
            'from_currency_id' => $euro->id,
            'to_currency_id'   => $dolar->id,
            'date'             => '2026-10-01',
            'rate'             => '1.08347',
        ]);
        $convertidor = new ExchangeRateConverter();
        $convertidor->setIgnoreSettings(true);
        $convertidor->setUserGroup(UserGroup::query()->find($usuario->user_group_id));

        // Act (paso 1): una conversión de 33.33.
        $this->primerResultado = $convertidor->convert($euro, $dolar, Carbon::parse('2026-10-01'), '33.33');
        // Act (paso 3): la misma conversión 10 veces seguidas.
        for ($i = 0; $i < 10; ++$i) {
            $this->diezResultados[] = $convertidor->convert($euro, $dolar, Carbon::parse('2026-10-01'), '33.33');
        }
    }

    #[Test]
    public function paso_1_convertir_33_33_devuelve_36_11(): void
    {
        $this->assertSame('36.11', $this->primerResultado);
    }

    #[Test]
    public function paso_2_el_resultado_tiene_exactamente_2_decimales(): void
    {
        $this->assertSame(2, strlen(substr((string) strrchr($this->primerResultado, '.'), 1)));
    }

    #[Test]
    public function paso_3_las_10_conversiones_devuelven_36_11(): void
    {
        $this->assertSame(array_fill(0, 10, '36.11'), $this->diezResultados);
    }
}