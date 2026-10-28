<?php

namespace Nodo\Reserva\Dominio\Eventos;

use Nodo\Compartido\Dominio\EventoDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Dominio\ReservaId;

final class ReservaCancelada implements EventoDeDominio
{
    public function __construct(
        public readonly ReservaId $reservaId,
        public readonly string $titularId,
        public readonly SalaId $salaId,
        public readonly Franja $franja,
        public readonly bool $tardia,
    ) {}

    public function nombre(): string
    {
        return 'Reserva cancelada';
    }

    public function datos(): array
    {
        return [
            'reservaId' => (string) $this->reservaId,
            'titularId' => $this->titularId,
            'salaId' => (string) $this->salaId,
            'franja' => (string) $this->franja,
            'tardia' => $this->tardia,
        ];
    }
}
