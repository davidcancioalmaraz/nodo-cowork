<?php

namespace Nodo\Reserva\Aplicacion\Puertos;

/** Quién actúa en Reserva y con qué rol, traducido desde el token de Control de acceso. */
final class Actor
{
    public function __construct(
        public readonly string $personaId,
        public readonly string $nombre,
        public readonly Rol $rol,
    ) {}

    public function esRecepcion(): bool
    {
        return $this->rol === Rol::Recepcion;
    }
}
