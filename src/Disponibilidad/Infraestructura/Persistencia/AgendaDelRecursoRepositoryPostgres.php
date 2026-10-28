<?php

namespace Nodo\Disponibilidad\Infraestructura\Persistencia;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\AgendaDelRecurso;
use Nodo\Disponibilidad\Dominio\AgendaDelRecursoRepository;
use Nodo\Disponibilidad\Dominio\AgendaModificadaPorOtro;

/**
 * Una fila por sala y día. Las ocupaciones y los bloqueos viajan con su
 * agenda (sin repositorio propio). Guardar usa bloqueo optimista por versión.
 */
final class AgendaDelRecursoRepositoryPostgres implements AgendaDelRecursoRepository
{
    private const TABLA = 'agendas_del_recurso';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function de(SalaId $salaId, string $fecha): ?AgendaDelRecurso
    {
        $fila = $this->db->table(self::TABLA)
            ->where('sala_id', (string) $salaId)
            ->where('fecha', $fecha)
            ->first();

        if ($fila === null) {
            return null;
        }

        return AgendaComoArreglo::agenda(
            $fila->sala_id,
            $fecha,
            json_decode($fila->ocupaciones, true),
            json_decode($fila->bloqueos, true),
            (int) $fila->version,
        );
    }

    public function guardar(AgendaDelRecurso $agenda): void
    {
        $partes = AgendaComoArreglo::partes($agenda);
        $datos = [
            'ocupaciones' => json_encode($partes['ocupaciones']),
            'bloqueos' => json_encode($partes['bloqueos']),
            'version' => $agenda->version() + 1,
            'updated_at' => now(),
        ];

        if ($agenda->version() === 0) {
            try {
                $this->db->table(self::TABLA)->insert($datos + [
                    'sala_id' => (string) $agenda->salaId(),
                    'fecha' => $agenda->fecha(),
                    'created_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new AgendaModificadaPorOtro($agenda);
            }

            return;
        }

        $actualizadas = $this->db->table(self::TABLA)
            ->where('sala_id', (string) $agenda->salaId())
            ->where('fecha', $agenda->fecha())
            ->where('version', $agenda->version())
            ->update($datos);

        if ($actualizadas === 0) {
            throw new AgendaModificadaPorOtro($agenda);
        }
    }
}
