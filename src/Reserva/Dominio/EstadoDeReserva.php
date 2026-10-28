<?php

namespace Nodo\Reserva\Dominio;

/**
 * INV-R2: una reserva solo pasa por estados válidos.
 * Cancelada, Rechazada y Expirada son estados finales.
 */
enum EstadoDeReserva: string
{
    case Solicitada = 'solicitada';
    case Provisional = 'provisional';
    case Confirmada = 'confirmada';
    case Rechazada = 'rechazada';
    case Expirada = 'expirada';
    case Cancelada = 'cancelada';

    public function puedePasarA(self $nuevo): bool
    {
        return in_array($nuevo, match ($this) {
            self::Solicitada => [self::Confirmada, self::Provisional, self::Rechazada],
            self::Provisional => [self::Confirmada, self::Expirada],
            self::Confirmada => [self::Cancelada],
            self::Rechazada, self::Expirada, self::Cancelada => [],
        }, true);
    }
}
