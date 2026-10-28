<?php

namespace Tests\Support;

use Closure;
use Nodo\Compartido\Aplicacion\PublicadorDeEventos;
use Nodo\Compartido\Dominio\EventoDeDominio;

/** Guarda los eventos publicados y, si se le indica, los entrega a un manejador en proceso. */
final class PublicadorQueRecuerda implements PublicadorDeEventos
{
    /** @var list<EventoDeDominio> */
    public array $publicados = [];

    /** @var array<class-string, Closure> */
    private array $manejadores = [];

    public function alPublicar(string $evento, Closure $manejador): void
    {
        $this->manejadores[$evento] = $manejador;
    }

    public function publicar(array $eventos): void
    {
        foreach ($eventos as $evento) {
            $this->publicados[] = $evento;

            if (isset($this->manejadores[$evento::class])) {
                ($this->manejadores[$evento::class])($evento);
            }
        }
    }

    /** @return list<string> */
    public function nombres(): array
    {
        return array_map(fn (EventoDeDominio $evento) => $evento->nombre(), $this->publicados);
    }
}
