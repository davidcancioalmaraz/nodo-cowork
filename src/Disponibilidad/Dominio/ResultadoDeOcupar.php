<?php

namespace Nodo\Disponibilidad\Dominio;

/**
 * `ocupar` devuelve «ocupada» o «no disponible» y no emite evento (D-61).
 */
final class ResultadoDeOcupar
{
    private function __construct(
        public readonly bool $ocupada,
        public readonly ?string $motivo = null,
    ) {}

    public static function ocupada(): self
    {
        return new self(true);
    }

    public static function noDisponible(string $motivo): self
    {
        return new self(false, $motivo);
    }
}
