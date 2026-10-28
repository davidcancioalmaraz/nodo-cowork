<?php

namespace Tests\Feature\Nodo;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Aplicacion\LiberarFranja;
use Nodo\Disponibilidad\Infraestructura\Persistencia\AgendaDelRecursoRepositoryEnMemoria;
use Nodo\Reserva\Aplicacion\CancelarReserva;
use Nodo\Reserva\Aplicacion\Puertos\Actor;
use Nodo\Reserva\Aplicacion\Puertos\Rol;
use Nodo\Reserva\Aplicacion\SolicitarReserva;
use Nodo\Reserva\Aplicacion\SolicitarReservaComando;
use Nodo\Reserva\Dominio\Eventos\ReservaCancelada;
use Nodo\Reserva\Dominio\Reserva;
use Nodo\Reserva\Infraestructura\Persistencia\ReservaRepositoryEnMemoria;
use Nodo\Reserva\Infraestructura\Vecinos\EspaciosYRecursosSimulado;
use Nodo\Reserva\Infraestructura\Vecinos\MembresiasYPlanesSimulado;
use Nodo\Reserva\Infraestructura\Vecinos\TarifasSimulado;
use PHPUnit\Framework\TestCase;
use Tests\Support\PublicadorQueRecuerda;
use Tests\Support\RelojFijo;

class CancelarReservaTest extends TestCase
{
    private AgendaDelRecursoRepositoryEnMemoria $agendas;

    private PublicadorQueRecuerda $eventos;

    private RelojFijo $reloj;

    private CancelarReserva $cancelarReserva;

    private Reserva $reserva;

    protected function setUp(): void
    {
        $reservas = new ReservaRepositoryEnMemoria;
        $this->agendas = new AgendaDelRecursoRepositoryEnMemoria;
        $this->eventos = new PublicadorQueRecuerda;
        $this->reloj = RelojFijo::en('2026-11-03 09:00');

        // P-07: Disponibilidad reacciona a «Reserva cancelada» en proceso.
        $liberarFranja = new LiberarFranja($this->agendas, $this->eventos);
        $this->eventos->alPublicar(ReservaCancelada::class, $liberarFranja->handle(...));

        $this->reserva = (new SolicitarReserva(
            $reservas,
            $this->agendas,
            new EspaciosYRecursosSimulado(['sala-b' => ['type' => 'SALA_REUNION', 'capacity' => 8]]),
            new MembresiasYPlanesSimulado(['m-ana' => ['plan_status' => 'ACTIVE', 'debt' => 0, 'included_minutes' => 600, 'used_minutes' => 0]]),
            new TarifasSimulado(['sala-b' => ['amount' => 80, 'currency' => 'BOB']]),
            $this->reloj,
            $this->eventos,
        ))->ejecutar(new SolicitarReservaComando(
            'm-ana', null, 'sala-b', new DateTimeImmutable('2026-11-05 10:00'), new DateTimeImmutable('2026-11-05 11:00'), 4,
        ));

        $this->eventos->publicados = [];
        $this->cancelarReserva = new CancelarReserva($reservas, $this->reloj, $this->eventos);
    }

    public function test_cancelar_libera_la_franja_en_disponibilidad(): void
    {
        $this->cancelarReserva->ejecutar((string) $this->reserva->id(), new Actor('m-ana', 'Ana', Rol::Miembro));

        $this->assertSame(['Reserva cancelada', 'Franja liberada'], $this->eventos->nombres());
        $this->assertSame([], $this->agendas->de(new SalaId('sala-b'), '2026-11-05')->ocupaciones());
    }

    public function test_inv_r7_recepcion_cancela_tarde_y_se_aplica_la_penalizacion(): void
    {
        $this->reloj->avanzarA('2026-11-05 08:00');

        $cancelada = $this->cancelarReserva->ejecutar((string) $this->reserva->id(), new Actor('r-carla', 'Carla', Rol::Recepcion));

        $this->assertSame('80.00 BOB', (string) $cancelada->penalizacion());
        // «Franja liberada» llega antes que la penalización: Disponibilidad
        // reacciona en proceso apenas se publica «Reserva cancelada».
        $this->assertSame(['Reserva cancelada', 'Franja liberada', 'Penalización aplicada'], $this->eventos->nombres());
    }

    public function test_solo_el_titular_o_recepcion_pueden_cancelar(): void
    {
        $this->expectException(ErrorDeDominio::class);

        $this->cancelarReserva->ejecutar((string) $this->reserva->id(), new Actor('m-luis', 'Luis', Rol::Miembro));
    }
}
