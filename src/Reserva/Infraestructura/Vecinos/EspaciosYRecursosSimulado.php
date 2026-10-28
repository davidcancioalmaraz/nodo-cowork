<?php

namespace Nodo\Reserva\Infraestructura\Vecinos;

use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Aplicacion\Puertos\AforoDeSala;

/**
 * ACL ③ de Espacios y recursos, con el catálogo simulado (config/nodo.php).
 * Traductor: la «capacity» del catálogo es el aforo de la sala para Reserva.
 */
final class EspaciosYRecursosSimulado implements AforoDeSala
{
    private const TIPOS_RESERVABLES = ['SALA_REUNION', 'SALA_CAPACITACION'];

    /** @param  array<string, array<string, mixed>>  $catalogo */
    public function __construct(private readonly array $catalogo) {}

    public function consultar(SalaId $salaId): int
    {
        $recurso = $this->catalogo[(string) $salaId] ?? null;

        if ($recurso === null || ! in_array($recurso['type'], self::TIPOS_RESERVABLES, true)) {
            throw new ErrorDeDominio("La sala {$salaId} no es reservable.");
        }

        return (int) $recurso['capacity'];
    }
}
