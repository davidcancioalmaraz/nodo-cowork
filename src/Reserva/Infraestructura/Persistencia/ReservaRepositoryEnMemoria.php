<?php

namespace Nodo\Reserva\Infraestructura\Persistencia;

use Nodo\Reserva\Dominio\Reserva;
use Nodo\Reserva\Dominio\ReservaId;
use Nodo\Reserva\Dominio\ReservaRepository;

/** Para los tests: guarda copias planas para no compartir la instancia. */
final class ReservaRepositoryEnMemoria implements ReservaRepository
{
    /** @var array<string, array<string, mixed>> */
    private array $reservas = [];

    public bool $fallarAlGuardar = false;

    public function porId(ReservaId $id): ?Reserva
    {
        $fila = $this->reservas[(string) $id] ?? null;

        return $fila === null ? null : ReservaComoArreglo::reserva($fila);
    }

    public function guardar(Reserva $reserva): void
    {
        if ($this->fallarAlGuardar) {
            throw new \RuntimeException('Falla simulada al guardar la reserva.');
        }

        $this->reservas[(string) $reserva->id()] = ReservaComoArreglo::fila($reserva);
    }
}
