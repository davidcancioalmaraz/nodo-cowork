<?php

namespace Nodo\Disponibilidad\Infraestructura\Lectura;

use Illuminate\Database\ConnectionInterface;
use Nodo\Disponibilidad\Aplicacion\SalasDisponibles;

/** Lee las agendas del día como datos planos, sin reconstruir aggregates. */
final class SalasDisponiblesPostgres implements SalasDisponibles
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function delDia(string $fecha): array
    {
        return $this->db->table('agendas_del_recurso')
            ->where('fecha', $fecha)
            ->get()
            ->mapWithKeys(fn ($fila) => [$fila->sala_id => [
                'ocupaciones' => json_decode($fila->ocupaciones, true),
                'bloqueos' => json_decode($fila->bloqueos, true),
            ]])
            ->all();
    }
}
