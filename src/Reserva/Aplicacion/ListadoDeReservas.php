<?php

namespace Nodo\Reserva\Aplicacion;

/**
 * Modelo de lectura: las reservas como datos planos para mostrar, sin cargar
 * aggregates ni reglas.
 */
interface ListadoDeReservas
{
    /** @return list<array<string, mixed>> */
    public function todas(): array;

    /** @return list<array<string, mixed>> */
    public function deTitular(string $titularId): array;
}
