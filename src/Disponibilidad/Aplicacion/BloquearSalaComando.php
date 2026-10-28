<?php

namespace Nodo\Disponibilidad\Aplicacion;

use DateTimeImmutable;
use Nodo\Disponibilidad\Dominio\MotivoDeBloqueo;

final class BloquearSalaComando
{
    public function __construct(
        public readonly string $salaId,
        public readonly DateTimeImmutable $inicio,
        public readonly DateTimeImmutable $fin,
        public readonly MotivoDeBloqueo $motivo,
        public readonly string $bloqueadoPor,
    ) {}
}
