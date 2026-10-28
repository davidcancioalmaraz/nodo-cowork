<?php

namespace Nodo\Disponibilidad\Aplicacion;

use Nodo\Compartido\Aplicacion\PublicadorDeEventos;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\AgendaDelRecurso;
use Nodo\Disponibilidad\Dominio\AgendaDelRecursoRepository;
use Nodo\Disponibilidad\Dominio\Eventos\SalaBloqueada;

/** Recepción saca una franja de disponibilidad por mantenimiento o limpieza. */
final class BloquearSala
{
    public function __construct(
        private readonly AgendaDelRecursoRepository $agendas,
        private readonly SalasReservables $salas,
        private readonly PublicadorDeEventos $eventos,
    ) {}

    /** Devuelve el hecho, con las reservas afectadas que recepción debe resolver (P-09). */
    public function ejecutar(BloquearSalaComando $cmd): SalaBloqueada
    {
        $salaId = new SalaId($cmd->salaId);

        if (! $this->salas->esReservable($salaId)) {
            throw new ErrorDeDominio("La sala {$salaId} no es reservable.");
        }

        $franja = Franja::de($cmd->inicio, $cmd->fin);
        $agenda = $this->agendas->de($salaId, $franja->dia()) ?? AgendaDelRecurso::abrir($salaId, $franja->dia());

        $agenda->bloquear($franja, $cmd->motivo, $cmd->bloqueadoPor); // INV-D3
        $this->agendas->guardar($agenda);

        $eventos = $agenda->extraerEventos();
        $this->eventos->publicar($eventos);

        return $eventos[0];
    }
}
