<?php

namespace Tests\Support;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\Reloj;

final class RelojFijo implements Reloj
{
    public function __construct(private DateTimeImmutable $ahora) {}

    public static function en(string $fechaHora): self
    {
        return new self(new DateTimeImmutable($fechaHora));
    }

    public function ahora(): DateTimeImmutable
    {
        return $this->ahora;
    }

    public function avanzarA(string $fechaHora): void
    {
        $this->ahora = new DateTimeImmutable($fechaHora);
    }
}
