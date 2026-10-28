<?php

namespace Nodo\Reserva\Infraestructura\Lectura;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Nodo\Reserva\Aplicacion\ListadoDeReservas;

final class ListadoDeReservasPostgres implements ListadoDeReservas
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function todas(): array
    {
        return $this->listar($this->consulta());
    }

    public function deTitular(string $titularId): array
    {
        return $this->listar($this->consulta()->where('titular_id', $titularId));
    }

    private function consulta(): Builder
    {
        return $this->db->table('reservas')->orderByDesc('inicio');
    }

    /** @return list<array<string, mixed>> */
    private function listar(Builder $consulta): array
    {
        return $consulta->get()->map(fn ($fila) => (array) $fila)->all();
    }
}
