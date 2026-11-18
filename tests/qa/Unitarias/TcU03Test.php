<?php

declare(strict_types=1);

namespace Tests\qa\Unitarias;

use Carbon\Carbon;
use FireflyIII\Factory\TransactionFactory;
use FireflyIII\Models\Transaction;
use FireflyIII\Models\TransactionCurrency;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Models\TransactionType;
use PHPUnit\Framework\Attributes\Test;
use Tests\qa\QaTestCase;

/**
 * TC-U-03 Transferencia debita y acredita el mismo monto exacto. RF-07.
 * Técnica: partición de equivalencias sobre TransactionFactory (pata negativa y pata positiva).
 *
 * Usa la base en memoria: TransactionFactory guarda cada pata en la tabla transactions.
 */
final class TcU03Test extends QaTestCase
{
    private Transaction $pataOrigen;
    private Transaction $pataDestino;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: cuentas origen y destino, y un journal de transferencia de 250.00.
        $usuario = $this->crearUsuario('qa-u03@firefly.test');
        $origen  = $this->crearCuentaDeActivo($usuario, 'Origen U03');
        $destino = $this->crearCuentaDeActivo($usuario, 'Destino U03');
        $euro    = TransactionCurrency::query()->where('code', 'EUR')->first();
        $journal = TransactionJournal::create([
            'user_id'                 => $usuario->id,
            'user_group_id'           => $usuario->user_group_id,
            'transaction_type_id'     => TransactionType::query()->where('type', 'Transfer')->first()->id,
            'transaction_currency_id' => $euro->id,
            'description'             => 'Transferencia U03',
            'completed'               => true,
            'order'                   => 0,
            'tag_count'               => 0,
            'date'                    => Carbon::now(),
            'date_tz'                 => 'America/Costa_Rica',
        ]);

        // Act (paso 1): pata negativa en la cuenta origen.
        $fabricaOrigen = new TransactionFactory();
        $fabricaOrigen->setAccount($origen);
        $fabricaOrigen->setCurrency($euro);
        $fabricaOrigen->setJournal($journal);
        $this->pataOrigen = $fabricaOrigen->createNegative('250.00', null);

        // Act (paso 2): pata positiva en la cuenta destino.
        $fabricaDestino = new TransactionFactory();
        $fabricaDestino->setAccount($destino);
        $fabricaDestino->setCurrency($euro);
        $fabricaDestino->setJournal($journal);
        $this->pataDestino = $fabricaDestino->createPositive('250.00', null);
    }

    #[Test]
    public function paso_1_pata_origen_tiene_amount_menos_250_00(): void
    {
        $this->assertSame(0, bccomp((string) $this->pataOrigen->amount, '-250.00', 2));
    }

    #[Test]
    public function paso_2_pata_destino_tiene_amount_250_00(): void
    {
        $this->assertSame(0, bccomp((string) $this->pataDestino->amount, '250.00', 2));
    }

    #[Test]
    public function paso_3_la_suma_de_las_dos_patas_con_bcadd_es_0(): void
    {
        $this->assertSame(0, bccomp(bcadd((string) $this->pataOrigen->amount, (string) $this->pataDestino->amount, 2), '0', 2));
    }
}