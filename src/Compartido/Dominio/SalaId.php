<?php

namespace Nodo\Compartido\Dominio;

/**
 * Shared kernel ①: identificador de la sala, común a Disponibilidad y Reserva.
 * La sala en sí pertenece a Espacios y recursos.
 */
final class SalaId
{
    public function __construct(public readonly string $valor)
    {
        if (trim($valor) === '') {
            throw new ErrorDeDominio('El identificador de la sala no puede estar vacío.');
        }
    }

    public function esIgualA(SalaId $otra): bool
    {
        return $this->valor === $otra->valor;
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
