<?php

namespace Nodo\Disponibilidad\Dominio;

use Nodo\Compartido\Dominio\Franja;

/**
 * Franja tomada por una reserva. Se identifica por la reserva para poder
 * liberarla.
 */
final class Ocupacion
{
    public function __construct(
        public readonly string $reservaId,
        public readonly Franja $franja,
    ) {}
}
