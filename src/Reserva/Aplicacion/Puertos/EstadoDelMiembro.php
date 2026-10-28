<?php

namespace Nodo\Reserva\Aplicacion\Puertos;

/** Fachada del ACL ⑤ de Membresías y planes. */
interface EstadoDelMiembro
{
    public function consultar(string $personaId): EstadoDeMembresia;
}
