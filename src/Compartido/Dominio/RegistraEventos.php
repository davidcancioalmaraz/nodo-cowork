<?php

namespace Nodo\Compartido\Dominio;

/**
 * Los aggregates acumulan sus domain events; el application service los
 * extrae y los publica después de guardar.
 */
trait RegistraEventos
{
    /** @var list<EventoDeDominio> */
    private array $eventos = [];

    protected function registrar(EventoDeDominio $evento): void
    {
        $this->eventos[] = $evento;
    }

    /** @return list<EventoDeDominio> */
    public function extraerEventos(): array
    {
        $eventos = $this->eventos;
        $this->eventos = [];

        return $eventos;
    }
}
