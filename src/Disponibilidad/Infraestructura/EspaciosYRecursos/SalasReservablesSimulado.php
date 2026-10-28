<?php

namespace Nodo\Disponibilidad\Infraestructura\EspaciosYRecursos;

use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Aplicacion\SalasReservables;

/**
 * ACL ② de Espacios y recursos, con el catálogo simulado (config/nodo.php).
 * Traductor: de todo el catálogo de Nodo solo pasan las salas que se
 * reservan por franja y están activas; escritorios y salón se ignoran.
 */
final class SalasReservablesSimulado implements SalasReservables
{
    private const TIPOS_RESERVABLES = ['SALA_REUNION', 'SALA_CAPACITACION'];

    /** @param  array<string, array<string, mixed>>  $catalogo  lo que publica Espacios y recursos */
    public function __construct(private readonly array $catalogo) {}

    public function todas(): array
    {
        $salas = [];

        foreach ($this->catalogo as $id => $recurso) {
            if ($this->esSalaReservable($recurso)) {
                $salas[] = ['id' => $id, 'nombre' => $recurso['name']];
            }
        }

        return $salas;
    }

    public function esReservable(SalaId $salaId): bool
    {
        $recurso = $this->catalogo[(string) $salaId] ?? null;

        return $recurso !== null && $this->esSalaReservable($recurso);
    }

    /** @param  array<string, mixed>  $recurso */
    private function esSalaReservable(array $recurso): bool
    {
        return in_array($recurso['type'], self::TIPOS_RESERVABLES, true) && $recurso['status'] === 'ACTIVE';
    }
}
