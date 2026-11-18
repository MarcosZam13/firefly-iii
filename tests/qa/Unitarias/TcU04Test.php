<?php

declare(strict_types=1);

namespace Tests\qa\Unitarias;

use FireflyIII\Models\TransactionGroup;
use FireflyIII\Models\TransactionJournal;
use FireflyIII\Services\Internal\Update\JournalUpdateService;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use Tests\qa\Transaction\ApiTransaccionesTestCase;
use Throwable;

/**
 * TC-U-04 Editar el monto actualiza las dos patas sin duplicar. RF-07.
 * Técnica: transición de estado sobre JournalUpdateService::updateAmount (privado, se invoca por reflexión).
 *
 * El journal de gasto de 100.00 se crea con la propia API; luego se llama al servicio directo.
 */
final class TcU04Test extends ApiTransaccionesTestCase
{
    private ?Throwable $excepcion = null;
    private TransactionJournal $journal;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: journal de gasto con monto original 100.00.
        $this->prepararCuentaConSaldo('qa-u04@firefly.test');
        $idGrupo       = (int) $this->enviarGasto('100.00')->json('data.id');
        $grupo         = TransactionGroup::query()->find($idGrupo);
        $this->journal = $grupo->transactionJournals()->first();

        $servicio = new JournalUpdateService();
        $servicio->setTransactionGroup($grupo);
        $servicio->setTransactionJournal($this->journal);
        $servicio->setData(['amount' => '75.00']);

        // Act (paso 1): actualizar el monto a 75.00.
        try {
            (new ReflectionMethod(JournalUpdateService::class, 'updateAmount'))->invoke($servicio);
        } catch (Throwable $e) {
            $this->excepcion = $e;
        }
    }

    #[Test]
    public function paso_1_la_actualizacion_se_ejecuta_sin_excepcion(): void
    {
        $this->assertNull($this->excepcion);
    }

    #[Test]
    public function paso_2_el_journal_tiene_exactamente_2_transacciones(): void
    {
        $this->assertSame(2, $this->journal->transactions()->count());
    }

    #[Test]
    public function paso_3_pata_origen_queda_en_menos_75_00(): void
    {
        $this->assertSame(0, bccomp((string) $this->journal->transactions()->where('amount', '<', 0)->first()->amount, '-75.00', 2));
    }

    #[Test]
    public function paso_3_pata_destino_queda_en_75_00(): void
    {
        $this->assertSame(0, bccomp((string) $this->journal->transactions()->where('amount', '>', 0)->first()->amount, '75.00', 2));
    }

    #[Test]
    public function paso_3_pata_origen_tiene_balance_dirty_true(): void
    {
        $this->assertTrue((bool) $this->journal->transactions()->where('amount', '<', 0)->first()->balance_dirty);
    }

    #[Test]
    public function paso_3_pata_destino_tiene_balance_dirty_true(): void
    {
        $this->assertTrue((bool) $this->journal->transactions()->where('amount', '>', 0)->first()->balance_dirty);
    }
}