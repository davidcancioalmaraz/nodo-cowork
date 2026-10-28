<?php

namespace Nodo\Reserva\Dominio;

use Nodo\Compartido\Dominio\ErrorDeDominio;

/**
 * Precio conservado de la franja. La moneda la define Tarifas y precios (D-54).
 */
final class Dinero
{
    public function __construct(
        public readonly int $centavos,
        public readonly string $moneda,
    ) {
        if ($centavos < 0) {
            throw new ErrorDeDominio('Un monto no puede ser negativo.');
        }
    }

    public function __toString(): string
    {
        return number_format($this->centavos / 100, 2, '.', '').' '.$this->moneda;
    }
}
