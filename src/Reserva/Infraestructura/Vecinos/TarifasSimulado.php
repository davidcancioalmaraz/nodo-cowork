<?php

namespace Nodo\Reserva\Infraestructura\Vecinos;

use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Aplicacion\Puertos\PrecioDeFranja;
use Nodo\Reserva\Dominio\Dinero;

/**
 * ACL ④ de Tarifas y precios, con las tarifas simuladas (config/nodo.php).
 * Traductor: la tarifa por hora se convierte en el precio de la franja, que
 * la reserva conserva aunque la tarifa cambie después.
 */
final class TarifasSimulado implements PrecioDeFranja
{
    /** @param  array<string, array<string, mixed>>  $tarifas */
    public function __construct(private readonly array $tarifas) {}

    public function vigente(SalaId $salaId, Franja $franja): Dinero
    {
        $tarifa = $this->tarifas[(string) $salaId]
            ?? throw new ErrorDeDominio("No hay una tarifa vigente para {$salaId}.");

        return new Dinero((int) round($tarifa['amount'] * 100 * $franja->horas()), $tarifa['currency']);
    }
}
