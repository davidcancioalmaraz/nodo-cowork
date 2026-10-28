<?php

namespace Nodo\Reserva\Infraestructura\Persistencia;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Dominio\Asistentes;
use Nodo\Reserva\Dominio\Dinero;
use Nodo\Reserva\Dominio\EstadoDeReserva;
use Nodo\Reserva\Dominio\Reserva;
use Nodo\Reserva\Dominio\ReservaId;
use Nodo\Reserva\Dominio\Responsable;

/** Traduce la reserva a una fila plana y de vuelta. */
final class ReservaComoArreglo
{
    private const FORMATO = 'Y-m-d H:i:s';

    /** @return array<string, mixed> */
    public static function fila(Reserva $reserva): array
    {
        return [
            'id' => (string) $reserva->id(),
            'titular_id' => $reserva->responsable()->titularId,
            'registrada_por' => $reserva->responsable()->registradaPor,
            'sala_id' => (string) $reserva->salaId(),
            'inicio' => $reserva->franja()->inicio->format(self::FORMATO),
            'fin' => $reserva->franja()->fin->format(self::FORMATO),
            'asistentes' => $reserva->asistentes()->cantidad,
            'estado' => $reserva->estado()->value,
            'solicitada_en' => $reserva->solicitadaEn()->format(self::FORMATO),
            'moneda' => $reserva->precio()?->moneda,
            'precio_centavos' => $reserva->precio()?->centavos,
            'penalizacion_centavos' => $reserva->penalizacion()?->centavos,
            'motivo_de_rechazo' => $reserva->motivoDeRechazo(),
            'cancelada_por' => $reserva->canceladaPor(),
        ];
    }

    /** @param  array<string, mixed>  $fila */
    public static function reserva(array $fila): Reserva
    {
        $responsable = $fila['registrada_por'] === null
            ? Responsable::titular($fila['titular_id'])
            : Responsable::asistida($fila['titular_id'], $fila['registrada_por']);

        $dinero = fn (?int $centavos) => $centavos === null ? null : new Dinero($centavos, $fila['moneda']);

        return Reserva::reconstituir(
            new ReservaId($fila['id']),
            $responsable,
            new SalaId($fila['sala_id']),
            Franja::de(new DateTimeImmutable($fila['inicio']), new DateTimeImmutable($fila['fin'])),
            new Asistentes((int) $fila['asistentes']),
            new DateTimeImmutable($fila['solicitada_en']),
            EstadoDeReserva::from($fila['estado']),
            $dinero($fila['precio_centavos'] === null ? null : (int) $fila['precio_centavos']),
            $dinero($fila['penalizacion_centavos'] === null ? null : (int) $fila['penalizacion_centavos']),
            $fila['motivo_de_rechazo'],
            $fila['cancelada_por'],
        );
    }
}
