<?php

namespace Tests\Feature\Nodo;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\AgendaDelRecurso;
use Nodo\Disponibilidad\Dominio\MotivoDeBloqueo;
use Nodo\Disponibilidad\Infraestructura\Persistencia\AgendaDelRecursoRepositoryEnMemoria;
use Nodo\Reserva\Aplicacion\SolicitarReserva;
use Nodo\Reserva\Aplicacion\SolicitarReservaComando;
use Nodo\Reserva\Dominio\EstadoDeReserva;
use Nodo\Reserva\Infraestructura\Persistencia\ReservaRepositoryEnMemoria;
use Nodo\Reserva\Infraestructura\Vecinos\EspaciosYRecursosSimulado;
use Nodo\Reserva\Infraestructura\Vecinos\MembresiasYPlanesSimulado;
use Nodo\Reserva\Infraestructura\Vecinos\TarifasSimulado;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\PublicadorQueRecuerda;
use Tests\Support\RelojFijo;

class SolicitarReservaTest extends TestCase
{
    private ReservaRepositoryEnMemoria $reservas;

    private AgendaDelRecursoRepositoryEnMemoria $agendas;

    private PublicadorQueRecuerda $eventos;

    private SolicitarReserva $solicitarReserva;

    protected function setUp(): void
    {
        $this->reservas = new ReservaRepositoryEnMemoria;
        $this->agendas = new AgendaDelRecursoRepositoryEnMemoria;
        $this->eventos = new PublicadorQueRecuerda;

        $this->solicitarReserva = new SolicitarReserva(
            $this->reservas,
            $this->agendas,
            new EspaciosYRecursosSimulado(['sala-b' => ['name' => 'Sala B', 'type' => 'SALA_REUNION', 'status' => 'ACTIVE', 'capacity' => 8]]),
            new MembresiasYPlanesSimulado([
                'm-ana' => ['plan_status' => 'ACTIVE', 'debt' => 0, 'included_minutes' => 1200, 'used_minutes' => 600],
                'm-luis' => ['plan_status' => 'OVERDUE', 'debt' => 350, 'included_minutes' => 600, 'used_minutes' => 600],
            ]),
            new TarifasSimulado(['sala-b' => ['amount' => 80, 'currency' => 'BOB']]),
            RelojFijo::en('2026-11-03 09:00'),
            $this->eventos,
        );
    }

    private function comando(string $inicio = '10:00', string $fin = '11:00', string $titular = 'm-ana', ?string $registradaPor = null, int $asistentes = 4): SolicitarReservaComando
    {
        return new SolicitarReservaComando(
            $titular, $registradaPor, 'sala-b',
            new DateTimeImmutable("2026-11-05 {$inicio}"), new DateTimeImmutable("2026-11-05 {$fin}"), $asistentes,
        );
    }

    public function test_inv_r1_la_reserva_confirmada_tiene_su_franja_ocupada(): void
    {
        $reserva = $this->solicitarReserva->ejecutar($this->comando());

        $this->assertSame(EstadoDeReserva::Confirmada, $reserva->estado());
        $this->assertSame('80.00 BOB', (string) $reserva->precio());

        $agenda = $this->agendas->de(new SalaId('sala-b'), '2026-11-05');
        $this->assertSame((string) $reserva->id(), $agenda->ocupaciones()[0]->reservaId);
        $this->assertSame(['Reserva solicitada', 'Reserva confirmada'], $this->eventos->nombres());
        $this->assertNotNull($this->reservas->porId($reserva->id()));
    }

    public function test_si_la_franja_esta_ocupada_la_solicitud_se_rechaza(): void
    {
        $this->solicitarReserva->ejecutar($this->comando('10:00', '11:00'));

        $segunda = $this->solicitarReserva->ejecutar($this->comando('11:05', '12:00'));

        $this->assertSame(EstadoDeReserva::Rechazada, $segunda->estado());
        $this->assertStringContainsString('INV-D2', $segunda->motivoDeRechazo());
        $this->assertCount(1, $this->agendas->de(new SalaId('sala-b'), '2026-11-05')->ocupaciones());
    }

    public function test_si_supera_el_aforo_se_rechaza_sin_ocupar_la_franja(): void
    {
        $reserva = $this->solicitarReserva->ejecutar($this->comando(asistentes: 9));

        $this->assertSame(EstadoDeReserva::Rechazada, $reserva->estado());
        $this->assertStringContainsString('INV-R5', $reserva->motivoDeRechazo());
        $this->assertNull($this->agendas->de(new SalaId('sala-b'), '2026-11-05'));
    }

    public function test_un_miembro_que_no_esta_al_dia_es_rechazado_aun_en_reserva_asistida(): void
    {
        $reserva = $this->solicitarReserva->ejecutar($this->comando(titular: 'm-luis', registradaPor: 'r-carla'));

        $this->assertSame(EstadoDeReserva::Rechazada, $reserva->estado());
        $this->assertStringContainsString('INV-R3', $reserva->motivoDeRechazo());
    }

    public function test_una_sala_bloqueada_no_se_puede_reservar(): void
    {
        $agenda = AgendaDelRecurso::abrir(new SalaId('sala-b'), '2026-11-05');
        $agenda->bloquear(
            Franja::de(new DateTimeImmutable('2026-11-05 09:00'), new DateTimeImmutable('2026-11-05 12:00')),
            MotivoDeBloqueo::Mantenimiento,
            'r-carla',
        );
        $this->agendas->guardar($agenda);

        $reserva = $this->solicitarReserva->ejecutar($this->comando());

        $this->assertSame(EstadoDeReserva::Rechazada, $reserva->estado());
        $this->assertStringContainsString('INV-D3', $reserva->motivoDeRechazo());
    }

    public function test_si_falla_guardar_la_reserva_se_libera_la_franja(): void
    {
        $this->reservas->fallarAlGuardar = true;

        try {
            $this->solicitarReserva->ejecutar($this->comando());
            $this->fail('Se esperaba la falla al guardar.');
        } catch (RuntimeException) {
        }

        $this->assertSame([], $this->agendas->de(new SalaId('sala-b'), '2026-11-05')->ocupaciones());
        $this->assertSame(['Franja liberada'], $this->eventos->nombres());
    }
}
