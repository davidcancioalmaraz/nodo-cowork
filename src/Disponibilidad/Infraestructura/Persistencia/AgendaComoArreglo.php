<?php

namespace Nodo\Disponibilidad\Infraestructura\Persistencia;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\AgendaDelRecurso;
use Nodo\Disponibilidad\Dominio\Bloqueo;
use Nodo\Disponibilidad\Dominio\MotivoDeBloqueo;
use Nodo\Disponibilidad\Dominio\Ocupacion;

/** Traduce la agenda a datos planos para guardarla y de vuelta. */
final class AgendaComoArreglo
{
    private const FORMATO = 'Y-m-d H:i:s';

    /** @return array{ocupaciones: list<array<string, string>>, bloqueos: list<array<string, string>>} */
    public static function partes(AgendaDelRecurso $agenda): array
    {
        return [
            'ocupaciones' => array_map(fn (Ocupacion $ocupacion) => [
                'reserva_id' => $ocupacion->reservaId,
                'inicio' => $ocupacion->franja->inicio->format(self::FORMATO),
                'fin' => $ocupacion->franja->fin->format(self::FORMATO),
            ], $agenda->ocupaciones()),
            'bloqueos' => array_map(fn (Bloqueo $bloqueo) => [
                'bloqueo_id' => $bloqueo->bloqueoId,
                'inicio' => $bloqueo->franja->inicio->format(self::FORMATO),
                'fin' => $bloqueo->franja->fin->format(self::FORMATO),
                'motivo' => $bloqueo->motivo->value,
                'bloqueado_por' => $bloqueo->bloqueadoPor,
            ], $agenda->bloqueos()),
        ];
    }

    /**
     * @param  list<array<string, string>>  $ocupaciones
     * @param  list<array<string, string>>  $bloqueos
     */
    public static function agenda(string $salaId, string $fecha, array $ocupaciones, array $bloqueos, int $version): AgendaDelRecurso
    {
        return AgendaDelRecurso::reconstituir(
            new SalaId($salaId),
            $fecha,
            array_map(fn (array $o) => new Ocupacion($o['reserva_id'], self::franja($o)), $ocupaciones),
            array_map(fn (array $b) => new Bloqueo(
                $b['bloqueo_id'], self::franja($b), MotivoDeBloqueo::from($b['motivo']), $b['bloqueado_por'],
            ), $bloqueos),
            $version,
        );
    }

    /** @param  array<string, string>  $datos */
    private static function franja(array $datos): Franja
    {
        return Franja::de(new DateTimeImmutable($datos['inicio']), new DateTimeImmutable($datos['fin']));
    }
}
