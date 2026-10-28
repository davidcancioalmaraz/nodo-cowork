<?php

namespace Nodo\Disponibilidad\Aplicacion;

/**
 * Modelo de lectura «Salas disponibles» (CQRS ligero, D-65): una vista plana
 * del día que nunca decide si una franja cabe; eso lo decide la agenda.
 */
interface SalasDisponibles
{
    /**
     * Ocupaciones y bloqueos de cada sala en la fecha, indexados por salaId.
     *
     * @return array<string, array{ocupaciones: list<array<string, string>>, bloqueos: list<array<string, string>>}>
     */
    public function delDia(string $fecha): array;
}
