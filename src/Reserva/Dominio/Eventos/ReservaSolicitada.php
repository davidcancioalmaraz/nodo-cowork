<?php

namespace Nodo\Reserva\Dominio\Eventos;

use Nodo\Compartido\Dominio\EventoDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Dominio\ReservaId;

final class ReservaSolicitada implements EventoDeDominio
{
    public function __construct(
        public readonly ReservaId $reservaId,
        public readonly string $titularId,
        public readonly SalaId $salaId,
        public readonly Franja $franja,
        public readonly int $asistentes,
    ) {}

    public function nombre(): string
    {
        return 'Reserva solicitada';
    }

    public function datos(): array
    {
        return [
            'reservaId' => (string) $this->reservaId,
            'titularId' => $this->titularId,
            'salaId' => (string) $this->salaId,
            'franja' => (string) $this->franja,
            'asistentes' => $this->asistentes,
        ];
    }
}
