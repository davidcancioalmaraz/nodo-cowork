<?php

namespace Nodo\Reserva\Dominio;

use Nodo\Compartido\Dominio\ErrorDeDominio;

final class Asistentes
{
    public function __construct(public readonly int $cantidad)
    {
        if ($cantidad < 1) {
            throw new ErrorDeDominio('Una reserva tiene al menos un asistente.');
        }
    }

    public function superan(int $aforo): bool
    {
        return $this->cantidad > $aforo;
    }
}
