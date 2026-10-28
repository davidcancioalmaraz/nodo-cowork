<?php

namespace Nodo\Disponibilidad\Dominio;

use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\RegistraEventos;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\Eventos\FranjaLiberada;
use Nodo\Disponibilidad\Dominio\Eventos\SalaBloqueada;

/**
 * Aggregate raíz de Disponibilidad: una sala en un día (salaId + fecha), con
 * sus ocupaciones y bloqueos.
 *
 * Protege:
 *  - INV-D1 Nunca dos ocupaciones se solapan en la misma sala.
 *  - INV-D2 Siempre quedan 15 min de margen, también frente a un bloqueo.
 *  - INV-D3 Una franja bloqueada no admite nuevas ocupaciones.
 *  - INV-D4 Solo se libera una ocupación existente, y una sola vez.
 *
 * Un bloqueo se puede retirar; al hacerlo, la franja vuelve a estar libre.
 *
 * Tiene versión para el bloqueo optimista: si dos solicitudes compiten por el
 * último cupo, una gana y la otra reintenta.
 */
final class AgendaDelRecurso
{
    use RegistraEventos;

    /**
     * @param  array<string, Ocupacion>  $ocupaciones  indexadas por reservaId
     * @param  array<string, Bloqueo>  $bloqueos  indexados por bloqueoId
     */
    private function __construct(
        private readonly SalaId $salaId,
        private readonly string $fecha,
        private readonly Margen $margen,
        private array $ocupaciones,
        private array $bloqueos,
        private readonly int $version,
    ) {}

    public static function abrir(SalaId $salaId, string $fecha): self
    {
        return new self($salaId, $fecha, Margen::deSala(), [], [], 0);
    }

    /**
     * @param  list<Ocupacion>  $ocupaciones
     * @param  list<Bloqueo>  $bloqueos
     */
    public static function reconstituir(SalaId $salaId, string $fecha, array $ocupaciones, array $bloqueos, int $version): self
    {
        $agenda = new self($salaId, $fecha, Margen::deSala(), [], [], $version);

        foreach ($ocupaciones as $ocupacion) {
            $agenda->ocupaciones[$ocupacion->reservaId] = $ocupacion;
        }

        foreach ($bloqueos as $bloqueo) {
            $agenda->bloqueos[$bloqueo->bloqueoId] = $bloqueo;
        }

        return $agenda;
    }

    public function ocupar(Franja $franja, string $reservaId): ResultadoDeOcupar
    {
        $this->exigirQueSeaDelDia($franja);

        if (isset($this->ocupaciones[$reservaId])) {
            throw new ErrorDeDominio("La reserva {$reservaId} ya ocupa una franja en esta agenda.");
        }

        $motivo = $this->motivoDeNoDisponibilidad($franja);

        if ($motivo !== null) {
            return ResultadoDeOcupar::noDisponible($motivo);
        }

        $this->ocupaciones[$reservaId] = new Ocupacion($reservaId, $franja);

        return ResultadoDeOcupar::ocupada();
    }

    public function liberar(string $reservaId): void
    {
        $ocupacion = $this->ocupaciones[$reservaId] ?? null;

        if ($ocupacion === null) {
            throw new ErrorDeDominio(
                'Solo se libera una ocupación existente, y una sola vez.',
                'INV-D4',
            );
        }

        unset($this->ocupaciones[$reservaId]);

        $this->registrar(new FranjaLiberada($this->salaId, $ocupacion->franja, reservaId: $reservaId));
    }

    /**
     * Se puede bloquear sobre una reserva confirmada: queda como reserva
     * afectada, que recepción resuelve después (P-09).
     */
    public function bloquear(Franja $franja, MotivoDeBloqueo $motivo, string $bloqueadoPor): Bloqueo
    {
        $this->exigirQueSeaDelDia($franja);

        foreach ($this->bloqueos as $existente) {
            if ($existente->franja->seSolapaCon($franja)) {
                throw new ErrorDeDominio("La sala ya está bloqueada en {$existente->franja}.");
            }
        }

        $bloqueo = new Bloqueo(bin2hex(random_bytes(8)), $franja, $motivo, $bloqueadoPor);
        $this->bloqueos[$bloqueo->bloqueoId] = $bloqueo;

        $afectadas = array_values(array_map(
            fn (Ocupacion $ocupacion) => $ocupacion->reservaId,
            array_filter($this->ocupaciones, fn (Ocupacion $ocupacion) => $ocupacion->franja->seSolapaCon($franja)),
        ));

        $this->registrar(new SalaBloqueada($this->salaId, $franja, $motivo, $afectadas));

        return $bloqueo;
    }

    /** Quien puede bloquear puede retirar el bloqueo antes de su fin (D-53). */
    public function retirarBloqueo(string $bloqueoId): void
    {
        $bloqueo = $this->bloqueos[$bloqueoId]
            ?? throw new ErrorDeDominio('Solo se retira un bloqueo existente.');

        unset($this->bloqueos[$bloqueoId]);

        $this->registrar(new FranjaLiberada($this->salaId, $bloqueo->franja, bloqueoId: $bloqueoId));
    }

    private function motivoDeNoDisponibilidad(Franja $franja): ?string
    {
        foreach ($this->bloqueos as $bloqueo) {
            if ($bloqueo->franja->seSolapaCon($franja)) {
                return "[INV-D3] La sala está bloqueada por {$bloqueo->motivo->value} en {$bloqueo->franja}.";
            }
        }

        foreach ($this->ocupaciones as $ocupacion) {
            if ($ocupacion->franja->seSolapaCon($franja)) {
                return "[INV-D1] La franja se solapa con otra ocupación ({$ocupacion->franja}).";
            }
        }

        $conMargen = $franja->ampliadaEn($this->margen->minutos);

        foreach ([...$this->bloqueos, ...$this->ocupaciones] as $tomada) {
            if ($tomada->franja->seSolapaCon($conMargen)) {
                return "[INV-D2] No quedan {$this->margen->minutos} min de margen con {$tomada->franja}.";
            }
        }

        return null;
    }

    private function exigirQueSeaDelDia(Franja $franja): void
    {
        if ($franja->dia() !== $this->fecha) {
            throw new ErrorDeDominio("La franja {$franja} no pertenece a la agenda del {$this->fecha}.");
        }
    }

    public function salaId(): SalaId
    {
        return $this->salaId;
    }

    public function fecha(): string
    {
        return $this->fecha;
    }

    public function version(): int
    {
        return $this->version;
    }

    /** @return list<Ocupacion> */
    public function ocupaciones(): array
    {
        return array_values($this->ocupaciones);
    }

    /** @return list<Bloqueo> */
    public function bloqueos(): array
    {
        return array_values($this->bloqueos);
    }
}
