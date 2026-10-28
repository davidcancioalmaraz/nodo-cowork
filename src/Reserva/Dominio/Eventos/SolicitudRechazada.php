<?php

namespace Nodo\Reserva\Dominio\Eventos;

use Nodo\Compartido\Dominio\EventoDeDominio;
use Nodo\Reserva\Dominio\ReservaId;

final class SolicitudRechazada implements EventoDeDominio
{
    public function __construct(
        public readonly ReservaId $reservaId,
        public readonly string $titularId,
        public readonly string $motivo,
    ) {}

    public function nombre(): string
    {
        return 'Solicitud rechazada';
    }

    public function datos(): array
    {
        return [
            'reservaId' => (string) $this->reservaId,
            'titularId' => $this->titularId,
            'motivo' => $this->motivo,
        ];
    }
}
