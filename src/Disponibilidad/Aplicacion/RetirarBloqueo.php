<?php

namespace Nodo\Disponibilidad\Aplicacion;

use Nodo\Compartido\Aplicacion\PublicadorDeEventos;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\AgendaDelRecursoRepository;
use Nodo\Disponibilidad\Dominio\Eventos\FranjaLiberada;

/** Recepción retira un bloqueo y la franja vuelve a estar disponible (D-53). */
final class RetirarBloqueo
{
    public function __construct(
        private readonly AgendaDelRecursoRepository $agendas,
        private readonly PublicadorDeEventos $eventos,
    ) {}

    public function ejecutar(string $salaId, string $fecha, string $bloqueoId): FranjaLiberada
    {
        $agenda = $this->agendas->de(new SalaId($salaId), $fecha)
            ?? throw new ErrorDeDominio('Solo se retira un bloqueo existente.');

        $agenda->retirarBloqueo($bloqueoId);
        $this->agendas->guardar($agenda);

        $eventos = $agenda->extraerEventos();
        $this->eventos->publicar($eventos);

        return $eventos[0];
    }
}
