<?php

namespace Nodo\Reserva\Dominio;

interface ReservaRepository
{
    public function porId(ReservaId $id): ?Reserva;

    public function guardar(Reserva $reserva): void;
}
