<?php

namespace Nodo\Disponibilidad\Dominio;

use RuntimeException;

final class AgendaModificadaPorOtro extends RuntimeException
{
    public function __construct(AgendaDelRecurso $agenda)
    {
        parent::__construct("La agenda de {$agenda->salaId()} del {$agenda->fecha()} cambió mientras se usaba.");
    }
}
