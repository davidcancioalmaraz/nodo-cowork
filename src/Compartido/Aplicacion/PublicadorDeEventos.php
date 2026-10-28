<?php

namespace Nodo\Compartido\Aplicacion;

use Nodo\Compartido\Dominio\EventoDeDominio;

interface PublicadorDeEventos
{
    /** @param list<EventoDeDominio> $eventos */
    public function publicar(array $eventos): void;
}
