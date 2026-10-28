<?php

namespace Nodo\Disponibilidad\Aplicacion;

use Nodo\Compartido\Aplicacion\PublicadorDeEventos;
use Nodo\Disponibilidad\Dominio\AgendaDelRecursoRepository;
use Nodo\Disponibilidad\Dominio\AgendaModificadaPorOtro;
use Nodo\Reserva\Dominio\Eventos\ReservaCancelada;

/**
 * P-07: cuando se cancela una reserva, Disponibilidad libera su franja.
 * Evento de dominio en proceso; la agenda se guarda en su propia transacción.
 */
final class LiberarFranja
{
    public function __construct(
        private readonly AgendaDelRecursoRepository $agendas,
        private readonly PublicadorDeEventos $eventos,
    ) {}

    public function handle(ReservaCancelada $evento): void
    {
        try {
            $this->liberar($evento);
        } catch (AgendaModificadaPorOtro) {
            $this->liberar($evento);
        }
    }

    private function liberar(ReservaCancelada $evento): void
    {
        $agenda = $this->agendas->de($evento->salaId, $evento->franja->dia());

        if ($agenda === null) {
            return;
        }

        $agenda->liberar((string) $evento->reservaId); // INV-D4 → Franja liberada
        $this->agendas->guardar($agenda);
        $this->eventos->publicar($agenda->extraerEventos());
    }
}
