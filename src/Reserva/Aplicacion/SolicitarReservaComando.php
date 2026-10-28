<?php

namespace Nodo\Reserva\Aplicacion;

use DateTimeImmutable;

final class SolicitarReservaComando
{
    /** @param  string|null  $registradaPor  persona de recepción, en una reserva asistida */
    public function __construct(
        public readonly string $titularId,
        public readonly ?string $registradaPor,
        public readonly string $salaId,
        public readonly DateTimeImmutable $inicio,
        public readonly DateTimeImmutable $fin,
        public readonly int $asistentes,
    ) {}
}
