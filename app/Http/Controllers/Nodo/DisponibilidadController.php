<?php

namespace App\Http\Controllers\Nodo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nodo\Compartido\Dominio\Reloj;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Compartido\Infraestructura\BitacoraDeIntegracion;
use Nodo\Disponibilidad\Aplicacion\SalasDisponibles;
use Nodo\Disponibilidad\Aplicacion\SalasReservables;
use Nodo\Reserva\Aplicacion\Puertos\AforoDeSala;

class DisponibilidadController extends Controller
{
    public function index(
        Request $request,
        SalasReservables $salasReservables,
        SalasDisponibles $salasDisponibles,
        AforoDeSala $aforoDeSala,
        BitacoraDeIntegracion $bitacora,
        Reloj $reloj,
    ): Response {
        $ahora = $reloj->ahora();
        $fecha = $request->date('fecha')?->format('Y-m-d') ?? $ahora->modify('+1 day')->format('Y-m-d');
        $agendas = $salasDisponibles->delDia($fecha);
        $hora = fn (string $fechaHora) => substr($fechaHora, 11, 5);

        $salas = array_map(fn (array $sala) => [
            ...$sala,
            'aforo' => $aforoDeSala->consultar(new SalaId($sala['id'])),
            'ocupaciones' => array_map(fn (array $o) => [
                'inicio' => $hora($o['inicio']),
                'fin' => $hora($o['fin']),
            ], $agendas[$sala['id']]['ocupaciones'] ?? []),
            'bloqueos' => array_map(fn (array $b) => [
                'id' => $b['bloqueo_id'],
                'inicio' => $hora($b['inicio']),
                'fin' => $hora($b['fin']),
                'motivo' => $b['motivo'],
            ], $agendas[$sala['id']]['bloqueos'] ?? []),
        ], $salasReservables->todas());

        return Inertia::render('Nodo/Disponibilidad', [
            'fecha' => $fecha,
            'hoy' => $ahora->format('Y-m-d'),
            'ahora' => $ahora->format('H:i'),
            'salas' => $salas,
            'miembros' => Directorio::miembros(),
            'bitacora' => $bitacora->ultimos(),
        ]);
    }
}
