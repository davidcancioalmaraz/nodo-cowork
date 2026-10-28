<?php

namespace Nodo\Disponibilidad\Aplicacion;

use Nodo\Compartido\Dominio\SalaId;

/** Fachada del ACL ② de Espacios y recursos: solo las salas que se reservan por franja. */
interface SalasReservables
{
    /** @return list<array{id: string, nombre: string}> */
    public function todas(): array;

    public function esReservable(SalaId $salaId): bool;
}
