<?php

namespace Nodo\Reserva\Dominio;

use Nodo\Compartido\Dominio\ErrorDeDominio;

/**
 * Quién responde por la reserva (titular) y, en una reserva asistida, quién de
 * recepción la registró (INV-R8).
 */
final class Responsable
{
    private function __construct(
        public readonly string $titularId,
        public readonly ?string $registradaPor,
    ) {
        if (trim($titularId) === '') {
            throw new ErrorDeDominio('Toda reserva tiene un titular.', 'INV-R8');
        }

        if ($registradaPor !== null && trim($registradaPor) === '') {
            throw new ErrorDeDominio('La reserva asistida registra quién la hizo.', 'INV-R8');
        }
    }

    public static function titular(string $titularId): self
    {
        return new self($titularId, null);
    }

    public static function asistida(string $titularId, string $registradaPor): self
    {
        return new self($titularId, $registradaPor);
    }

    public function esAsistida(): bool
    {
        return $this->registradaPor !== null;
    }
}
