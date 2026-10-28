<?php

namespace Nodo\Disponibilidad\Dominio;

use Nodo\Compartido\Dominio\SalaId;

interface AgendaDelRecursoRepository
{
    public function de(SalaId $salaId, string $fecha): ?AgendaDelRecurso;

    /**
     * Falla con AgendaModificadaPorOtro si la versión cambió desde que se
     * leyó (bloqueo optimista: el último cupo).
     */
    public function guardar(AgendaDelRecurso $agenda): void;
}
