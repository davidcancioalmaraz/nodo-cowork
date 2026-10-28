<?php

namespace Nodo\Reserva\Infraestructura\Vecinos;

use Closure;
use Nodo\Reserva\Aplicacion\Puertos\Actor;
use Nodo\Reserva\Aplicacion\Puertos\QuienActua;
use Nodo\Reserva\Aplicacion\Puertos\Rol;

/**
 * OHS + ACL ⑨ de Control de acceso físico, simulado.
 *
 * El usuario con el que se inició sesión («miembro» o «recepcion») elige un
 * token estático (config/nodo.php); el traductor convierte sus «roles» en el
 * rol de Reserva, para que palabras como «receptionist» no entren al dominio.
 */
final class ControlDeAccesoSimulado implements QuienActua
{
    private const ROLES = [
        'receptionist' => Rol::Recepcion,
        'member' => Rol::Miembro,
    ];

    /**
     * @param  array<string, array<string, mixed>>  $tokens  por usuario del login
     * @param  Closure(): ?string  $usuarioActual
     */
    public function __construct(
        private readonly array $tokens,
        private readonly Closure $usuarioActual,
    ) {}

    public function actual(): ?Actor
    {
        $token = $this->tokens[($this->usuarioActual)() ?? ''] ?? null;

        if ($token === null) {
            return null;
        }

        foreach (self::ROLES as $rolDelToken => $rol) {
            if (in_array($rolDelToken, $token['roles'], true)) {
                return new Actor($token['sub'], $token['name'], $rol);
            }
        }

        return null;
    }
}
