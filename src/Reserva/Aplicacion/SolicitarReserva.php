<?php

namespace Nodo\Reserva\Aplicacion;

use Nodo\Compartido\Aplicacion\PublicadorDeEventos;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\Reloj;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\AgendaDelRecurso;
use Nodo\Disponibilidad\Dominio\AgendaDelRecursoRepository;
use Nodo\Disponibilidad\Dominio\AgendaModificadaPorOtro;
use Nodo\Disponibilidad\Dominio\ResultadoDeOcupar;
use Nodo\Reserva\Aplicacion\Puertos\AforoDeSala;
use Nodo\Reserva\Aplicacion\Puertos\EstadoDelMiembro;
use Nodo\Reserva\Aplicacion\Puertos\PrecioDeFranja;
use Nodo\Reserva\Dominio\Asistentes;
use Nodo\Reserva\Dominio\Reserva;
use Nodo\Reserva\Dominio\ReservaId;
use Nodo\Reserva\Dominio\ReservaRepository;
use Nodo\Reserva\Dominio\Responsable;
use Throwable;

/**
 * Caso principal: reservar una sala. Coordina, no decide: las reglas las
 * deciden Reserva y AgendaDelRecurso.
 *
 * Ocupar antes de confirmar cumple INV-R1 (toda reserva confirmada tiene su
 * franja ocupada) sin modificar dos aggregates en la misma transacción. Si
 * guardar la reserva falla, se libera la franja (compensación).
 */
final class SolicitarReserva
{
    private const INTENTOS_AL_OCUPAR = 2;

    public function __construct(
        private readonly ReservaRepository $reservas,
        private readonly AgendaDelRecursoRepository $agendas,
        private readonly AforoDeSala $aforoDeSala,
        private readonly EstadoDelMiembro $estadoDelMiembro,
        private readonly PrecioDeFranja $precioDeFranja,
        private readonly Reloj $reloj,
        private readonly PublicadorDeEventos $eventos,
    ) {}

    public function ejecutar(SolicitarReservaComando $cmd): Reserva
    {
        $ahora = $this->reloj->ahora();
        $salaId = new SalaId($cmd->salaId);
        $franja = Franja::de($cmd->inicio, $cmd->fin);

        $responsable = $cmd->registradaPor === null
            ? Responsable::titular($cmd->titularId)
            : Responsable::asistida($cmd->titularId, $cmd->registradaPor);

        // INV-R4 e INV-R8: si no se cumplen, la solicitud ni siquiera se registra.
        $reserva = Reserva::solicitar(ReservaId::nueva(), $responsable, $salaId, $franja, new Asistentes($cmd->asistentes), $ahora);

        $aforo = $this->aforoDeSala->consultar($salaId);
        $estado = $this->estadoDelMiembro->consultar($cmd->titularId);

        $motivo = $reserva->motivoParaNoAceptar($aforo, $estado->alDia, $ahora);

        if ($motivo !== null) {
            $reserva->rechazar($motivo); // P-08

            return $this->guardarYPublicar($reserva);
        }

        $ocupacion = $this->ocuparLaFranja($salaId, $franja, $reserva->id());

        if (! $ocupacion->ocupada) {
            $reserva->rechazar($ocupacion->motivo); // P-08

            return $this->guardarYPublicar($reserva);
        }

        try {
            $reserva->confirmar($aforo, $estado->alDia, $this->precioDeFranja->vigente($salaId, $franja), $ahora); // P-01
            $this->reservas->guardar($reserva);
        } catch (Throwable $error) {
            $this->liberarLaFranja($salaId, $franja, $reserva->id()); // compensación: INV-R1

            throw $error;
        }

        $this->eventos->publicar($reserva->extraerEventos());

        return $reserva;
    }

    private function ocuparLaFranja(SalaId $salaId, Franja $franja, ReservaId $reservaId): ResultadoDeOcupar
    {
        for ($intento = 1; ; $intento++) {
            $agenda = $this->agendas->de($salaId, $franja->dia()) ?? AgendaDelRecurso::abrir($salaId, $franja->dia());

            $resultado = $agenda->ocupar($franja, (string) $reservaId);

            if (! $resultado->ocupada) {
                return $resultado;
            }

            try {
                $this->agendas->guardar($agenda);

                return $resultado;
            } catch (AgendaModificadaPorOtro $conflicto) {
                // El último cupo: otra solicitud cambió la agenda. Se reintenta
                // con la versión nueva; si la franja ya no cabe, se rechaza.
                if ($intento >= self::INTENTOS_AL_OCUPAR) {
                    throw $conflicto;
                }
            }
        }
    }

    private function liberarLaFranja(SalaId $salaId, Franja $franja, ReservaId $reservaId): void
    {
        $agenda = $this->agendas->de($salaId, $franja->dia());
        $agenda->liberar((string) $reservaId);
        $this->agendas->guardar($agenda);
        $this->eventos->publicar($agenda->extraerEventos());
    }

    private function guardarYPublicar(Reserva $reserva): Reserva
    {
        $this->reservas->guardar($reserva);
        $this->eventos->publicar($reserva->extraerEventos());

        return $reserva;
    }
}
