<?php

namespace Nodo\Reserva\Infraestructura\Vecinos;

use Nodo\Reserva\Aplicacion\Puertos\EstadoDelMiembro;
use Nodo\Reserva\Aplicacion\Puertos\EstadoDeMembresia;

/**
 * ACL ⑤ de Membresías y planes, con los planes simulados (config/nodo.php).
 * Traductor: «plan_status» y «debt» → miembro al día; minutos → horas disponibles.
 */
final class MembresiasYPlanesSimulado implements EstadoDelMiembro
{
    /** @param  array<string, array<string, mixed>>  $planes  lo que respondería la API de Membresías */
    public function __construct(private readonly array $planes) {}

    public function consultar(string $personaId): EstadoDeMembresia
    {
        $plan = $this->planes[$personaId] ?? null;

        if ($plan === null) {
            // 404 en Membresías: no tiene plan (visitante, fuera del prototipo).
            return new EstadoDeMembresia(alDia: false, horasDisponibles: 0);
        }

        return new EstadoDeMembresia(
            alDia: $plan['plan_status'] === 'ACTIVE' && $plan['debt'] == 0,
            horasDisponibles: max(0, $plan['included_minutes'] - $plan['used_minutes']) / 60,
        );
    }
}
