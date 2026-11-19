<?php

declare(strict_types=1);

namespace Tests\qa\Diana;

use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;

/** TC-F-52, RF-18. Presupuesto y límite son dos operaciones distintas de la API. */
final class TcF52CrearPresupuestoValidoTest extends ApiDianaTestCase
{
    private TestResponse $presupuesto;
    private TestResponse $limite;
    private TestResponse $consulta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presupuesto = $this->api('POST', '/budgets', ['name' => 'Presupuesto QA Diana', 'active' => true]);
        $id = $this->idDePrecondicion($this->presupuesto);
        $this->limite = $this->api('POST', '/budgets/'.$id.'/limits', $this->datosLimite('500.00'));
        $idLimite = $this->idDePrecondicion($this->limite);
        $this->consulta = $this->api('GET', '/budgets/'.$id.'/limits/'.$idLimite);
    }

    #[Test]
    public function paso_1_presupuesto_responde_http_200(): void
    {
        $this->presupuesto->assertStatus(200);
    }

    #[Test]
    public function paso_2_limite_responde_http_200(): void
    {
        $this->limite->assertStatus(200);
    }

    #[Test]
    public function paso_3_monto_guardado_es_500_euros(): void
    {
        $this->assertSame(0, bccomp('500.00', (string) $this->consulta->json('data.attributes.amount'), 2));
    }

    #[Test]
    public function paso_3_fecha_inicial_es_la_solicitada(): void
    {
        $this->assertSame('2026-11-01', substr((string) $this->consulta->json('data.attributes.start'), 0, 10));
    }

    #[Test]
    public function paso_3_fecha_final_es_la_solicitada(): void
    {
        $this->assertSame('2026-11-30', substr((string) $this->consulta->json('data.attributes.end'), 0, 10));
    }
}
