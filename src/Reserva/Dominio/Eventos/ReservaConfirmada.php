<?php

namespace Nodo\Reserva\Dominio\Eventos;

use Nodo\Compartido\Dominio\EventoDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Dominio\Dinero;
use Nodo\Reserva\Dominio\ReservaId;

final class ReservaConfirmada implements EventoDeDominio
{
    public function __construct(
        public readonly ReservaId $reservaId,
        public readonly string $titularId,
        public readonly ?string $registradaPor,
        public readonly SalaId $salaId,
        public readonly Franja $franja,
        public readonly Dinero $precio,
    ) {}

    public function nombre(): string
    {
        return 'Reserva confirmada';
    }

    public function datos(): array
    {
        return [
            'reservaId' => (string) $this->reservaId,
            'titularId' => $this->titularId,
            'registradaPor' => $this->registradaPor,
            'salaId' => (string) $this->salaId,
            'franja' => (string) $this->franja,
            'precio' => (string) $this->precio,
        ];
    }
}
