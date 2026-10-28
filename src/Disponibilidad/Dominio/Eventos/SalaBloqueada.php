<?php

namespace Nodo\Disponibilidad\Dominio\Eventos;

use Nodo\Compartido\Dominio\EventoDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\MotivoDeBloqueo;

final class SalaBloqueada implements EventoDeDominio
{
    /** @param list<string> $reservasAfectadas */
    public function __construct(
        public readonly SalaId $salaId,
        public readonly Franja $franja,
        public readonly MotivoDeBloqueo $motivo,
        public readonly array $reservasAfectadas,
    ) {}

    public function nombre(): string
    {
        return 'Sala bloqueada';
    }

    public function datos(): array
    {
        return [
            'salaId' => (string) $this->salaId,
            'franja' => (string) $this->franja,
            'motivo' => $this->motivo->value,
            'reservasAfectadas' => $this->reservasAfectadas,
        ];
    }
}
