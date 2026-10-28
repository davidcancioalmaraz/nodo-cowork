<?php

namespace Nodo\Disponibilidad\Infraestructura\Persistencia;

use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\AgendaDelRecurso;
use Nodo\Disponibilidad\Dominio\AgendaDelRecursoRepository;
use Nodo\Disponibilidad\Dominio\AgendaModificadaPorOtro;

/** Para los tests: guarda copias planas, con la misma regla de versión que PostgreSQL. */
final class AgendaDelRecursoRepositoryEnMemoria implements AgendaDelRecursoRepository
{
    /** @var array<string, array{ocupaciones: list<array<string, string>>, bloqueos: list<array<string, string>>, version: int}> */
    private array $agendas = [];

    public function de(SalaId $salaId, string $fecha): ?AgendaDelRecurso
    {
        $guardada = $this->agendas["{$salaId}|{$fecha}"] ?? null;

        if ($guardada === null) {
            return null;
        }

        return AgendaComoArreglo::agenda((string) $salaId, $fecha, $guardada['ocupaciones'], $guardada['bloqueos'], $guardada['version']);
    }

    public function guardar(AgendaDelRecurso $agenda): void
    {
        $clave = "{$agenda->salaId()}|{$agenda->fecha()}";

        if (($this->agendas[$clave]['version'] ?? 0) !== $agenda->version()) {
            throw new AgendaModificadaPorOtro($agenda);
        }

        $this->agendas[$clave] = AgendaComoArreglo::partes($agenda) + ['version' => $agenda->version() + 1];
    }
}
