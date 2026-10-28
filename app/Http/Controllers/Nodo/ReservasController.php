<?php

namespace App\Http\Controllers\Nodo;

use App\Http\Controllers\Controller;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Infraestructura\BitacoraDeIntegracion;
use Nodo\Reserva\Aplicacion\CancelarReserva;
use Nodo\Reserva\Aplicacion\ListadoDeReservas;
use Nodo\Reserva\Aplicacion\Puertos\QuienActua;
use Nodo\Reserva\Aplicacion\SolicitarReserva;
use Nodo\Reserva\Aplicacion\SolicitarReservaComando;
use Nodo\Reserva\Dominio\EstadoDeReserva;

class ReservasController extends Controller
{
    public function index(ListadoDeReservas $listado, QuienActua $quienActua, BitacoraDeIntegracion $bitacora): Response
    {
        $actor = $quienActua->actual() ?? abort(403);
        $filas = $actor->esRecepcion() ? $listado->todas() : $listado->deTitular($actor->personaId);
        $dinero = fn (?int $centavos, ?string $moneda) => $centavos === null ? null : number_format($centavos / 100, 2).' '.$moneda;

        return Inertia::render('Nodo/Reservas', [
            'reservas' => array_map(fn (array $r) => [
                'id' => $r['id'],
                'sala' => Directorio::nombreDeSala($r['sala_id']),
                'fecha' => substr($r['inicio'], 0, 10),
                'inicio' => substr($r['inicio'], 11, 5),
                'fin' => substr($r['fin'], 11, 5),
                'titular' => Directorio::nombreDe($r['titular_id']),
                'registradaPor' => Directorio::nombreDe($r['registrada_por']),
                'asistentes' => $r['asistentes'],
                'estado' => $r['estado'],
                'precio' => $dinero($r['precio_centavos'], $r['moneda']),
                'penalizacion' => $dinero($r['penalizacion_centavos'], $r['moneda']),
                'motivoDeRechazo' => $r['motivo_de_rechazo'],
                'puedeCancelar' => $r['estado'] === EstadoDeReserva::Confirmada->value,
            ], $filas),
            'bitacora' => $bitacora->ultimos(),
        ]);
    }

    public function store(Request $request, SolicitarReserva $solicitarReserva, QuienActua $quienActua): RedirectResponse
    {
        $actor = $quienActua->actual() ?? abort(403);

        $datos = $request->validate([
            'sala_id' => ['required', 'string'],
            'fecha' => ['required', 'date_format:Y-m-d'],
            'inicio' => ['required', 'date_format:H:i'],
            'fin' => ['required', 'date_format:H:i'],
            'asistentes' => ['required', 'integer', 'min:1'],
            'titular_id' => [$actor->esRecepcion() ? 'required' : 'prohibited', 'string'],
        ]);

        // Reserva asistida (INV-R8): recepción reserva a nombre de un miembro.
        $comando = new SolicitarReservaComando(
            titularId: $actor->esRecepcion() ? $datos['titular_id'] : $actor->personaId,
            registradaPor: $actor->esRecepcion() ? $actor->personaId : null,
            salaId: $datos['sala_id'],
            inicio: new DateTimeImmutable("{$datos['fecha']} {$datos['inicio']}"),
            fin: new DateTimeImmutable("{$datos['fecha']} {$datos['fin']}"),
            asistentes: (int) $datos['asistentes'],
        );

        try {
            $reserva = $solicitarReserva->ejecutar($comando);
        } catch (ErrorDeDominio $error) {
            return $this->aviso('Solicitud no registrada: '.$error->getMessage(), 'danger');
        }

        $sala = Directorio::nombreDeSala((string) $reserva->salaId());

        return $reserva->estado() === EstadoDeReserva::Confirmada
            ? $this->aviso("Reserva confirmada: {$sala}, {$reserva->franja()} · {$reserva->precio()}")
            : $this->aviso("Solicitud rechazada: {$reserva->motivoDeRechazo()}", 'danger');
    }

    public function cancelar(string $reserva, CancelarReserva $cancelarReserva, QuienActua $quienActua): RedirectResponse
    {
        $actor = $quienActua->actual() ?? abort(403);

        try {
            $cancelada = $cancelarReserva->ejecutar($reserva, $actor);
        } catch (ErrorDeDominio $error) {
            return $this->aviso($error->getMessage(), 'danger');
        }

        return $cancelada->penalizacion() === null
            ? $this->aviso('Reserva cancelada sin penalización. La franja quedó libre.')
            : $this->aviso("Reserva cancelada con menos de 4 h: penalización de {$cancelada->penalizacion()} (INV-R7). La franja quedó libre.", 'danger');
    }

    private function aviso(string $mensaje, string $estilo = 'success'): RedirectResponse
    {
        return back()->with('flash', ['banner' => $mensaje, 'bannerStyle' => $estilo]);
    }
}
