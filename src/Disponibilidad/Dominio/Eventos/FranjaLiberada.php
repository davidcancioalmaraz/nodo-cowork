<?php

namespace Nodo\Disponibilidad\Dominio\Eventos;

use Nodo\Compartido\Dominio\EventoDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;

/**
 * La franja vuelve a estar disponible: porque se canceló la reserva que la
 * ocupaba o porque se retiró el bloqueo que la sacaba de disponibilidad (D-53).
 */
final class FranjaLiberada implements EventoDeDominio
{
    public function __construct(
        public readonly SalaId $salaId,
        public readonly Franja $franja,
        public readonly ?string $reservaId = null,
        public readonly ?string $bloqueoId = null,
    ) {}

    public function nombre(): string
    {
        return 'Franja liberada';
    }

    public function datos(): array
    {
        return [
            'salaId' => (string) $this->salaId,
            'franja' => (string) $this->franja,
            'reservaId' => $this->reservaId,
            'bloqueoId' => $this->bloqueoId,
        ];
    }
}
