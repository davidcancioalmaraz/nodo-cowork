<?php

namespace Nodo\Disponibilidad\Dominio;

/**
 * Tiempo que se deja libre alrededor de cada ocupación para limpiar y
 * preparar la sala. No es tiempo reservable.
 */
final class Margen
{
    private function __construct(public readonly int $minutos) {}

    public static function deSala(): self
    {
        return new self(15);
    }

    public static function deMinutos(int $minutos): self
    {
        return new self(max(0, $minutos));
    }
}
