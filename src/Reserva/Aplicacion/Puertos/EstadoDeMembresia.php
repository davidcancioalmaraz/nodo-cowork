<?php

namespace Nodo\Reserva\Aplicacion\Puertos;

/** Lo que Reserva necesita saber de un miembro, ya traducido por el ACL ⑤. */
final class EstadoDeMembresia
{
    public function __construct(
        public readonly bool $alDia,
        public readonly float $horasDisponibles,
    ) {}
}
