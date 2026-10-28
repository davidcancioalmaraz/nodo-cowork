<?php

namespace Nodo\Disponibilidad\Dominio;

enum MotivoDeBloqueo: string
{
    case Mantenimiento = 'mantenimiento';
    case Limpieza = 'limpieza';
}
