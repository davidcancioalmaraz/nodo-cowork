<?php

namespace App\Http\Controllers\Nodo;

/**
 * Nombres para mostrar en pantalla, tomados de los vecinos simulados
 * (Membresías y Control de acceso). Solo presentación: el dominio trabaja con ids.
 */
final class Directorio
{
    public static function nombreDe(?string $personaId): ?string
    {
        if ($personaId === null) {
            return null;
        }

        $miembro = config("nodo.membresias.{$personaId}.full_name");

        if ($miembro !== null) {
            return $miembro;
        }

        foreach (config('nodo.identidades') as $token) {
            if ($token['sub'] === $personaId) {
                return $token['name'];
            }
        }

        return $personaId;
    }

    public static function nombreDeSala(string $salaId): string
    {
        return config("nodo.espacios.{$salaId}.name", $salaId);
    }

    /** @return list<array{id: string, nombre: string}> */
    public static function miembros(): array
    {
        return collect(config('nodo.membresias'))
            ->map(fn (array $plan, string $id) => ['id' => $id, 'nombre' => $plan['full_name']])
            ->values()
            ->all();
    }
}
