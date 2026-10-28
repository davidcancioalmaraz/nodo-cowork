<?php

namespace Nodo\Reserva\Aplicacion\Puertos;

/** Fachada del OHS + ACL ⑨ de Control de acceso físico. */
interface QuienActua
{
    public function actual(): ?Actor;
}
