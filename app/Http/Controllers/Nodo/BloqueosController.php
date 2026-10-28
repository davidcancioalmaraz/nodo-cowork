<?php

namespace App\Http\Controllers\Nodo;

use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Disponibilidad\Aplicacion\BloquearSala;
use Nodo\Disponibilidad\Aplicacion\BloquearSalaComando;
use Nodo\Disponibilidad\Aplicacion\RetirarBloqueo;
use Nodo\Disponibilidad\Dominio\MotivoDeBloqueo;
use Nodo\Reserva\Aplicacion\Puertos\QuienActua;

class BloqueosController extends Controller
{
    public function store(Request $request, BloquearSala $bloquearSala, QuienActua $quienActua): RedirectResponse
    {
        $actor = $quienActua->actual();
        abort_unless($actor?->esRecepcion(), 403, 'Solo recepción puede bloquear una sala.');

        $datos = $request->validate([
            'sala_id' => ['required', 'string'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'inicio' => ['required', 'date_format:H:i'],
            'fin' => ['required', 'date_format:H:i'],
            'motivo' => ['required', Rule::enum(MotivoDeBloqueo::class)],
        ]);

        try {
            $salaBloqueada = $bloquearSala->ejecutar(new BloquearSalaComando(
                salaId: $datos['sala_id'],
                inicio: new DateTimeImmutable("{$datos['fecha']} {$datos['inicio']}"),
                fin: new DateTimeImmutable("{$datos['fecha']} {$datos['fin']}"),
                motivo: MotivoDeBloqueo::from($datos['motivo']),
                bloqueadoPor: $actor->personaId,
            ));
        } catch (ErrorDeDominio $error) {
            return back()->with('flash', ['banner' => $error->getMessage(), 'bannerStyle' => 'danger']);
        }

        $afectadas = count($salaBloqueada->reservasAfectadas);
        $sala = Directorio::nombreDeSala((string) $salaBloqueada->salaId);
        $mensaje = "Sala bloqueada: {$sala}, {$salaBloqueada->franja}".
            ($afectadas > 0 ? " · {$afectadas} reserva(s) afectada(s) por resolver (P-09)" : '');

        return back()->with('flash', ['banner' => $mensaje, 'bannerStyle' => 'success']);
    }

    public function destroy(string $sala, string $fecha, string $bloqueo, RetirarBloqueo $retirarBloqueo, QuienActua $quienActua): RedirectResponse
    {
        abort_unless($quienActua->actual()?->esRecepcion(), 403, 'Solo recepción puede retirar un bloqueo.');

        try {
            $franjaLiberada = $retirarBloqueo->ejecutar($sala, $fecha, $bloqueo);
        } catch (ErrorDeDominio $error) {
            return back()->with('flash', ['banner' => $error->getMessage(), 'bannerStyle' => 'danger']);
        }

        $nombre = Directorio::nombreDeSala($sala);

        return back()->with('flash', [
            'banner' => "Bloqueo retirado: {$nombre}, {$franjaLiberada->franja}. La franja quedó libre.",
            'bannerStyle' => 'success',
        ]);
    }
}
