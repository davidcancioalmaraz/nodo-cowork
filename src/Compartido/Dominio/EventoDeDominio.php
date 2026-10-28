<?php

namespace Nodo\Compartido\Dominio;

/**
 * Un hecho del dominio en pasado. Su nombre coincide con la nota naranja del
 * EventStorming que lo originó.
 */
interface EventoDeDominio
{
    public function nombre(): string;

    /** @return array<string, mixed> */
    public function datos(): array;
}
