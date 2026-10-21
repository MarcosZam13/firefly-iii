<?php

declare(strict_types=1);

namespace Tests\qa\Unitarias;

use Carbon\Carbon;
use FireflyIII\Http\Controllers\Auth\TwoFactorController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * TC-U-01 Ventana de 300 s contra reuso de códigos 2FA. RF-05.
 * Técnica: valores límite sobre la condición (ahora - momento de uso) <= 300.
 *
 * Prueba unitaria: no levanta la aplicación ni la base. inMFAHistory es
 * privado, así que se invoca por reflexión, y el controlador se crea sin
 * constructor (el método solo usa el reloj de Carbon).
 */
final class TcU01VentanaHistorialMfaTest extends TestCase
{
    private const string CODIGO = '123456';
    private Carbon $momentoDeUso;
    private TwoFactorController $controlador;
    private ReflectionMethod $inMfaHistory;
    private array $historial;

    protected function setUp(): void
    {
        parent::setUp();
        // Arrange: historial con el código usado en un instante conocido T.
        $this->momentoDeUso = Carbon::create(2026, 10, 21, 12, 0, 0, 'America/Costa_Rica');
        $this->historial    = [['time' => $this->momentoDeUso->getTimestamp(), 'code' => self::CODIGO]];
        $this->controlador  = (new ReflectionClass(TwoFactorController::class))->newInstanceWithoutConstructor();
        $this->inMfaHistory = new ReflectionMethod(TwoFactorController::class, 'inMFAHistory');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function paso_1_a_los_299_segundos_el_codigo_sigue_usado(): void
    {
        Carbon::setTestNow($this->momentoDeUso->copy()->addSeconds(299));

        $this->assertTrue($this->inMfaHistory->invoke($this->controlador, self::CODIGO, $this->historial));
    }

    #[Test]
    public function paso_2_a_los_300_segundos_el_codigo_sigue_usado_borde_inclusivo(): void
    {
        Carbon::setTestNow($this->momentoDeUso->copy()->addSeconds(300));

        $this->assertTrue($this->inMfaHistory->invoke($this->controlador, self::CODIGO, $this->historial));
    }

    #[Test]
    public function paso_3_a_los_301_segundos_el_codigo_ya_no_cuenta_como_usado(): void
    {
        Carbon::setTestNow($this->momentoDeUso->copy()->addSeconds(301));

        $this->assertFalse($this->inMfaHistory->invoke($this->controlador, self::CODIGO, $this->historial));
    }
}
