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
 * TC-U-05 Conversión de moneda aplica la tasa vigente en la fecha. RF-12.
 * Técnica: partición de equivalencias sobre ExchangeRateConverter::convert (dos fechas y una moneda sin tasa).
 *
 * Paso 3: en el código actual, sin tasa cargada devuelve tasa 1; si falla, se registra como defecto contra RF-12, criterio 2.
 */
final class TcU05Test extends QaTestCase
{
    private string $resultadoOctubre1;
    private string $resultadoOctubre15;
    private string $resultadoSinTasa;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: tasas EUR a USD de 1.10 (2026-10-01) y 1.20 (2026-10-15); ninguna EUR a GBP.
        $usuario = $this->crearUsuario('qa-u05@firefly.test');
        $euro    = TransactionCurrency::query()->where('code', 'EUR')->first();
        $dolar   = TransactionCurrency::query()->where('code', 'USD')->first();
        $libra   = TransactionCurrency::query()->where('code', 'GBP')->first();
        foreach ([['2026-10-01', '1.10'], ['2026-10-15', '1.20']] as [$fecha, $tasa]) {
            CurrencyExchangeRate::create([
                'user_id'          => $usuario->id,
                'user_group_id'    => $usuario->user_group_id,
                'from_currency_id' => $euro->id,
                'to_currency_id'   => $dolar->id,
                'date'             => $fecha,
                'rate'             => $tasa,
            ]);
        }
        $convertidor = new ExchangeRateConverter();
        $convertidor->setIgnoreSettings(true);
        $convertidor->setUserGroup(UserGroup::query()->find($usuario->user_group_id));

        // Act (pasos 1 a 3).
        $this->resultadoOctubre1  = $convertidor->convert($euro, $dolar, Carbon::parse('2026-10-01'), '100.00');
        $this->resultadoOctubre15 = $convertidor->convert($euro, $dolar, Carbon::parse('2026-10-15'), '100.00');
        $this->resultadoSinTasa   = $convertidor->convert($euro, $libra, Carbon::parse('2026-10-01'), '100.00');
    }

    private function dosDecimales(string $valor): string
    {
        return number_format((float) $valor, 2, '.', '');
    }

    #[Test]
    public function paso_1_convertir_el_2026_10_01_devuelve_110_00(): void
    {
        $this->assertSame('110.00', $this->dosDecimales($this->resultadoOctubre1));
    }

    #[Test]
    public function paso_2_convertir_el_2026_10_15_devuelve_120_00(): void
    {
        $this->assertSame('120.00', $this->dosDecimales($this->resultadoOctubre15));
    }

    #[Test]
    public function paso_3_sin_tasa_cargada_no_devuelve_el_monto_sin_convertir(): void
    {
        $this->assertNotSame('100.00', $this->dosDecimales($this->resultadoSinTasa));
    }
}