<?php

namespace Nodo\Reserva\Dominio;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Franja;

/**
 * Con menos de 4 h de anticipación, cancelar cuesta la franja completa;
 * después del inicio ya no se cancela.
 */
final class PoliticaDeCancelacion
{
    private function __construct(public readonly int $horasMinimasSinPenalizacion) {}

    public static function estandar(): self
    {
        return new self(4);
    }

    /** La penalización que corresponde, o null si se cancela a tiempo. */
    public function penalizacionPara(Franja $franja, Dinero $precioConservado, DateTimeImmutable $ahora): ?Dinero
    {
        if ($ahora >= $franja->inicio) {
            throw new ErrorDeDominio('Una reserva no se cancela después de su inicio.');
        }

        $horasHastaElInicio = ($franja->inicio->getTimestamp() - $ahora->getTimestamp()) / 3600;

        return $horasHastaElInicio < $this->horasMinimasSinPenalizacion ? $precioConservado : null;
    }
}
