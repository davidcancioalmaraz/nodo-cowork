<?php

namespace Nodo\Reserva\Dominio;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\RegistraEventos;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Dominio\Eventos\PenalizacionAplicada;
use Nodo\Reserva\Dominio\Eventos\ReservaCancelada;
use Nodo\Reserva\Dominio\Eventos\ReservaConfirmada;
use Nodo\Reserva\Dominio\Eventos\ReservaSolicitada;
use Nodo\Reserva\Dominio\Eventos\SolicitudRechazada;

/**
 * Aggregate raíz del contexto Reserva: lo que pide una persona sobre una
 * franja de una sala. Cambia de estado y sigue siendo la misma.
 *
 * Protege:
 *  - INV-R2 Solo pasa por estados válidos.
 *  - INV-R3 La confirmación automática exige miembro al día y 24 h o más de anticipación.
 *  - INV-R4 Con menos de 3 h no se registra; de 3 a 24 h, solo como reserva asistida.
 *  - INV-R5 Los asistentes nunca superan el aforo.
 *  - INV-R7 Cancelar con menos de 4 h genera exactamente una penalización.
 *  - INV-R8 La reserva asistida guarda el titular y quién la registró.
 */
final class Reserva
{
    use RegistraEventos;

    private function __construct(
        private readonly ReservaId $id,
        private readonly Responsable $responsable,
        private readonly SalaId $salaId,
        private readonly Franja $franja,
        private readonly Asistentes $asistentes,
        private readonly DateTimeImmutable $solicitadaEn,
        private readonly PoliticaDeCancelacion $politica,
        private EstadoDeReserva $estado,
        private ?Dinero $precio = null,
        private ?Dinero $penalizacion = null,
        private ?string $motivoDeRechazo = null,
        private ?string $canceladaPor = null,
    ) {}

    public static function solicitar(
        ReservaId $id,
        Responsable $responsable,
        SalaId $salaId,
        Franja $franja,
        Asistentes $asistentes,
        DateTimeImmutable $ahora,
    ): self {
        $anticipacion = Anticipacion::entre($ahora, $franja->inicio);

        if (! $anticipacion->permiteRegistrar()) {
            throw new ErrorDeDominio('Ninguna reserva se registra con menos de 3 h de anticipación.', 'INV-R4');
        }

        if (! $anticipacion->permiteConfirmacionAutomatica() && ! $responsable->esAsistida()) {
            throw new ErrorDeDominio(
                'Con menos de 24 h de anticipación la reserva solo se registra como reserva asistida, en recepción.',
                'INV-R4',
            );
        }

        $reserva = new self(
            $id, $responsable, $salaId, $franja, $asistentes, $ahora,
            PoliticaDeCancelacion::estandar(), EstadoDeReserva::Solicitada,
        );

        $reserva->registrar(new ReservaSolicitada($id, $responsable->titularId, $salaId, $franja, $asistentes->cantidad));

        return $reserva;
    }

    public static function reconstituir(
        ReservaId $id,
        Responsable $responsable,
        SalaId $salaId,
        Franja $franja,
        Asistentes $asistentes,
        DateTimeImmutable $solicitadaEn,
        EstadoDeReserva $estado,
        ?Dinero $precio,
        ?Dinero $penalizacion,
        ?string $motivoDeRechazo,
        ?string $canceladaPor,
    ): self {
        return new self(
            $id, $responsable, $salaId, $franja, $asistentes, $solicitadaEn,
            PoliticaDeCancelacion::estandar(), $estado, $precio, $penalizacion, $motivoDeRechazo, $canceladaPor,
        );
    }

    /**
     * El motivo para rechazar la solicitud antes de ocupar la franja, o null
     * si se puede aceptar.
     */
    public function motivoParaNoAceptar(int $aforo, bool $miembroAlDia, DateTimeImmutable $ahora): ?string
    {
        if ($this->asistentes->superan($aforo)) {
            return "[INV-R5] Los asistentes ({$this->asistentes->cantidad}) superan el aforo de la sala ({$aforo}).";
        }

        if (! $miembroAlDia) {
            return '[INV-R3] El titular no está al día con su membresía.';
        }

        if (! $this->responsable->esAsistida() && ! Anticipacion::entre($ahora, $this->franja->inicio)->permiteConfirmacionAutomatica()) {
            return '[INV-R3] La confirmación automática exige 24 h o más de anticipación.';
        }

        return null;
    }

    /**
     * Se llama después de que la agenda ocupó la franja (INV-R1). Vuelve a
     * exigir las reglas: el aggregate no confía en quien lo llama.
     */
    public function confirmar(int $aforo, bool $miembroAlDia, Dinero $precio, DateTimeImmutable $ahora): void
    {
        $this->pasarA(EstadoDeReserva::Confirmada);

        $motivo = $this->motivoParaNoAceptar($aforo, $miembroAlDia, $ahora);

        if ($motivo !== null) {
            throw new ErrorDeDominio($motivo);
        }

        $this->estado = EstadoDeReserva::Confirmada;
        $this->precio = $precio;

        $this->registrar(new ReservaConfirmada(
            $this->id, $this->responsable->titularId, $this->responsable->registradaPor,
            $this->salaId, $this->franja, $precio,
        ));
    }

    public function rechazar(string $motivo): void
    {
        $this->pasarA(EstadoDeReserva::Rechazada);

        $this->estado = EstadoDeReserva::Rechazada;
        $this->motivoDeRechazo = $motivo;

        $this->registrar(new SolicitudRechazada($this->id, $this->responsable->titularId, $motivo));
    }

    public function cancelar(string $canceladaPor, DateTimeImmutable $ahora): void
    {
        $this->pasarA(EstadoDeReserva::Cancelada);

        $penalizacion = $this->politica->penalizacionPara($this->franja, $this->precio, $ahora);

        $this->estado = EstadoDeReserva::Cancelada;
        $this->canceladaPor = $canceladaPor;
        $this->penalizacion = $penalizacion;

        $this->registrar(new ReservaCancelada(
            $this->id, $this->responsable->titularId, $this->salaId, $this->franja, tardia: $penalizacion !== null,
        ));

        if ($penalizacion !== null) {
            $this->registrar(new PenalizacionAplicada($this->id, $this->responsable->titularId, $this->franja, $penalizacion));
        }
    }

    private function pasarA(EstadoDeReserva $nuevo): void
    {
        if (! $this->estado->puedePasarA($nuevo)) {
            throw new ErrorDeDominio(
                "Una reserva {$this->estado->value} no puede pasar a {$nuevo->value}.",
                'INV-R2',
            );
        }
    }

    public function id(): ReservaId
    {
        return $this->id;
    }

    public function responsable(): Responsable
    {
        return $this->responsable;
    }

    public function salaId(): SalaId
    {
        return $this->salaId;
    }

    public function franja(): Franja
    {
        return $this->franja;
    }

    public function asistentes(): Asistentes
    {
        return $this->asistentes;
    }

    public function solicitadaEn(): DateTimeImmutable
    {
        return $this->solicitadaEn;
    }

    public function estado(): EstadoDeReserva
    {
        return $this->estado;
    }

    public function precio(): ?Dinero
    {
        return $this->precio;
    }

    public function penalizacion(): ?Dinero
    {
        return $this->penalizacion;
    }

    public function motivoDeRechazo(): ?string
    {
        return $this->motivoDeRechazo;
    }

    public function canceladaPor(): ?string
    {
        return $this->canceladaPor;
    }
}
