<?php

namespace Nodo\Reserva\Dominio;

use Nodo\Compartido\Dominio\ErrorDeDominio;

final class ReservaId
{
    public function __construct(public readonly string $valor)
    {
        if (trim($valor) === '') {
            throw new ErrorDeDominio('El identificador de la reserva no puede estar vacío.');
        }
    }

    public static function nueva(): self
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return new self(vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4)));
    }

    public function __toString(): string
    {
        return $this->valor;
    }
}
