<?php

namespace Nodo\Compartido\Dominio;

use DomainException;

/**
 * Una regla del dominio impidió la operación. El mensaje está en lenguaje
 * ubicuo y, cuando aplica, indica la invariante que se protegió.
 */
class ErrorDeDominio extends DomainException
{
    public function __construct(string $mensaje, public readonly ?string $invariante = null)
    {
        parent::__construct($invariante ? "[{$invariante}] {$mensaje}" : $mensaje);
    }
}
