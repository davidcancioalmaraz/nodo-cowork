<?php

namespace Nodo\Reserva\Dominio\Eventos;

use Nodo\Compartido\Dominio\EventoDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Reserva\Dominio\Dinero;
use Nodo\Reserva\Dominio\ReservaId;

final class PenalizacionAplicada implements EventoDeDominio
{
    public function __construct(
        public readonly ReservaId $reservaId,
        public readonly string $titularId,
        public readonly Franja $franja,
        public readonly Dinero $monto,
    ) {}

    public function nombre(): string
    {
        return 'Penalización aplicada';
    }

    public function datos(): array
    {
        return [
            'reservaId' => (string) $this->reservaId,
            'titularId' => $this->titularId,
            'franja' => (string) $this->franja,
            'monto' => (string) $this->monto,
        ];
    }
}
