<?php

namespace Nodo\Compartido\Infraestructura;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\Reloj;

final class RelojDelSistema implements Reloj
{
    public function ahora(): DateTimeImmutable
    {
        return new DateTimeImmutable;
    }
}
