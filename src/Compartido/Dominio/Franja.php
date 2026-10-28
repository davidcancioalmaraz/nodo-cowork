<?php

namespace Nodo\Compartido\Dominio;

use DateTimeImmutable;

/**
 * Shared kernel ① entre Disponibilidad y Reserva.
 *
 * Un tramo de tiempo de un mismo día. Garantiza que el inicio sea anterior
 * al fin y sabe si se solapa con otra franja.
 */
final class Franja
{
    private function __construct(
        public readonly DateTimeImmutable $inicio,
        public readonly DateTimeImmutable $fin,
    ) {}

    public static function de(DateTimeImmutable $inicio, DateTimeImmutable $fin): self
    {
        if ($inicio >= $fin) {
            throw new ErrorDeDominio('El inicio de la franja debe ser anterior a su fin.');
        }

        if ($inicio->format('Y-m-d') !== $fin->format('Y-m-d')) {
            throw new ErrorDeDominio('Una franja empieza y termina el mismo día.');
        }

        return new self($inicio, $fin);
    }

    public function seSolapaCon(Franja $otra): bool
    {
        return $this->inicio < $otra->fin && $otra->inicio < $this->fin;
    }

    /**
     * La misma franja extendida a ambos lados. Se usa para comparar con margen;
     * el resultado puede cruzar el día, por eso no pasa por la validación de `de()`.
     */
    public function ampliadaEn(int $minutos): self
    {
        return new self(
            $this->inicio->modify("-{$minutos} minutes"),
            $this->fin->modify("+{$minutos} minutes"),
        );
    }

    public function dia(): string
    {
        return $this->inicio->format('Y-m-d');
    }

    public function horas(): float
    {
        return ($this->fin->getTimestamp() - $this->inicio->getTimestamp()) / 3600;
    }

    public function esIgualA(Franja $otra): bool
    {
        return $this->inicio == $otra->inicio && $this->fin == $otra->fin;
    }

    public function __toString(): string
    {
        return $this->inicio->format('Y-m-d H:i').'–'.$this->fin->format('H:i');
    }
}
