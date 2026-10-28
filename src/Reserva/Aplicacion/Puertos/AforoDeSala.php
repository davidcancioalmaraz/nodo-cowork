<?php

namespace Nodo\Reserva\Aplicacion\Puertos;

use Nodo\Compartido\Dominio\SalaId;

/** Fachada del ACL ③ de Espacios y recursos. */
interface AforoDeSala
{
    public function consultar(SalaId $salaId): int;
}
