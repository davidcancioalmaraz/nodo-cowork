<?php

namespace Nodo\Disponibilidad\Dominio;

use Nodo\Compartido\Dominio\Franja;

/**
 * Franja que Nodo saca de disponibilidad por mantenimiento o limpieza.
 * No tiene titular ni cobro: bloquear no es cancelar.
 */
final class Bloqueo
{
    public function __construct(
        public readonly string $bloqueoId,
        public readonly Franja $franja,
        public readonly MotivoDeBloqueo $motivo,
        public readonly string $bloqueadoPor,
    ) {}
}
