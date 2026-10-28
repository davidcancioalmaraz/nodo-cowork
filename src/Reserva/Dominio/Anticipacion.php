<?php

namespace Nodo\Reserva\Dominio;

use DateTimeImmutable;

/**
 * Tiempo entre la solicitud y el inicio de la franja.
 * 24 h o más: automática · de 3 a 24 h: asistida · menos de 3 h: no se registra.
 */
final class Anticipacion
{
    private const MINIMA_EN_HORAS = 3;

    private const AUTOMATICA_EN_HORAS = 24;

    private function __construct(public readonly float $horas) {}

    public static function entre(DateTimeImmutable $ahora, DateTimeImmutable $inicio): self
    {
        return new self(($inicio->getTimestamp() - $ahora->getTimestamp()) / 3600);
    }

    public function permiteRegistrar(): bool
    {
        return $this->horas >= self::MINIMA_EN_HORAS;
    }

    public function permiteConfirmacionAutomatica(): bool
    {
        return $this->horas >= self::AUTOMATICA_EN_HORAS;
    }
}
