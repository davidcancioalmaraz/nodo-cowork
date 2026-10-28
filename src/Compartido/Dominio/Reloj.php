<?php

namespace Nodo\Compartido\Dominio;

use DateTimeImmutable;

interface Reloj
{
    public function ahora(): DateTimeImmutable;
}
