<?php

namespace Nodo\Compartido\Infraestructura;

use Illuminate\Contracts\Events\Dispatcher;
use Nodo\Compartido\Aplicacion\PublicadorDeEventos;

/**
 * Eventos de dominio en proceso: los entrega al despachador de Laravel, que
 * invoca a los listeners de forma síncrona.
 */
final class PublicadorDeEventosLaravel implements PublicadorDeEventos
{
    public function __construct(private readonly Dispatcher $despachador) {}

    public function publicar(array $eventos): void
    {
        foreach ($eventos as $evento) {
            $this->despachador->dispatch($evento);
        }
    }
}
