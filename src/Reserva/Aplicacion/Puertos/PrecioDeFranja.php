<?php

namespace Nodo\Reserva\Aplicacion\Puertos;

use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Dominio\Dinero;

/** Fachada del ACL ④ de Tarifas y precios. */
interface PrecioDeFranja
{
    public function vigente(SalaId $salaId, Franja $franja): Dinero;
}
