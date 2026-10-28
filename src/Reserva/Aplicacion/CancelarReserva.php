<?php

namespace Nodo\Reserva\Aplicacion;

use Nodo\Compartido\Aplicacion\PublicadorDeEventos;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Reloj;
use Nodo\Reserva\Aplicacion\Puertos\Actor;
use Nodo\Reserva\Dominio\Reserva;
use Nodo\Reserva\Dominio\ReservaId;
use Nodo\Reserva\Dominio\ReservaRepository;

/**
 * Cancelar una reserva. Liberar la franja no es asunto de este servicio:
 * Disponibilidad reacciona a «Reserva cancelada» (P-07).
 */
final class CancelarReserva
{
    public function __construct(
        private readonly ReservaRepository $reservas,
        private readonly Reloj $reloj,
        private readonly PublicadorDeEventos $eventos,
    ) {}

    public function ejecutar(string $reservaId, Actor $quien): Reserva
    {
        $reserva = $this->reservas->porId(new ReservaId($reservaId))
            ?? throw new ErrorDeDominio('No existe la reserva indicada.');

        if (! $quien->esRecepcion() && $reserva->responsable()->titularId !== $quien->personaId) {
            throw new ErrorDeDominio('Solo el titular o recepción pueden cancelar la reserva.');
        }

        $reserva->cancelar($quien->personaId, $this->reloj->ahora()); // INV-R2, INV-R7
        $this->reservas->guardar($reserva);
        $this->eventos->publicar($reserva->extraerEventos());

        return $reserva;
    }
}
