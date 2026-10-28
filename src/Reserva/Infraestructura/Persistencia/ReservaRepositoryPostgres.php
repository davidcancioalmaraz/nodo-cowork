<?php

namespace Nodo\Reserva\Infraestructura\Persistencia;

use Illuminate\Database\ConnectionInterface;
use Nodo\Reserva\Dominio\Reserva;
use Nodo\Reserva\Dominio\ReservaId;
use Nodo\Reserva\Dominio\ReservaRepository;

final class ReservaRepositoryPostgres implements ReservaRepository
{
    private const TABLA = 'reservas';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function porId(ReservaId $id): ?Reserva
    {
        $fila = $this->db->table(self::TABLA)->where('id', (string) $id)->first();

        return $fila === null ? null : ReservaComoArreglo::reserva((array) $fila);
    }

    public function guardar(Reserva $reserva): void
    {
        $fila = ReservaComoArreglo::fila($reserva);

        $this->db->table(self::TABLA)->upsert(
            [$fila + ['created_at' => now(), 'updated_at' => now()]],
            ['id'],
            [...array_keys(array_diff_key($fila, ['id' => true])), 'updated_at'],
        );
    }
}
