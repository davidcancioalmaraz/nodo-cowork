<?php

namespace Nodo\Compartido\Infraestructura;

use Illuminate\Database\ConnectionInterface;
use Nodo\Compartido\Dominio\EventoDeDominio;

/**
 * Simula lo que cruza hacia los vecinos (Notificaciones ⑦⑧, Facturación ⑥,
 * Membresías ⑤, Control de acceso ⑨) y lo que reacciona en proceso. En vez
 * de enviar, registra cada mensaje para mostrarlo en la demo.
 */
final class BitacoraDeIntegracion
{
    private const TABLA = 'bitacora_de_integracion';

    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(EventoDeDominio $evento): void
    {
        foreach ($this->mensajesPara($evento) as $destino => $mensaje) {
            $this->db->table(self::TABLA)->insert([
                'evento' => $evento->nombre(),
                'destino' => $destino,
                'mensaje' => json_encode($mensaje, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
            ]);
        }
    }

    /** @return list<array<string, mixed>> */
    public function ultimos(int $cantidad = 15): array
    {
        return $this->db->table(self::TABLA)
            ->orderByDesc('id')
            ->limit($cantidad)
            ->get()
            ->map(fn ($fila) => [
                'id' => $fila->id,
                'evento' => $fila->evento,
                'destino' => $fila->destino,
                'mensaje' => json_decode($fila->mensaje, true),
                'ocurrido' => (string) $fila->created_at,
            ])
            ->all();
    }

    /** @return array<string, array<string, mixed>> destino → lo que se le envía */
    private function mensajesPara(EventoDeDominio $evento): array
    {
        $d = $evento->datos();

        return match ($evento->nombre()) {
            'Reserva confirmada' => [
                'Notificaciones ⑦' => ['plantilla' => 'reserva_confirmada', 'destinatario' => $d['titularId'], 'recordatorio' => 'un día antes'],
                'Control de acceso ⑨' => ['zone' => $d['salaId'], 'franja' => $d['franja'], 'holder' => $d['titularId']],
                'Membresías y planes ⑤' => ['informar_horas_consumidas' => $d['franja'], 'persona' => $d['titularId']],
            ],
            'Solicitud rechazada' => [
                'Notificaciones ⑦' => ['plantilla' => 'solicitud_rechazada', 'destinatario' => $d['titularId'], 'motivo' => $d['motivo']],
            ],
            'Reserva cancelada' => [
                'Disponibilidad (en proceso)' => ['liberar' => $d['reservaId']],
                'Notificaciones ⑦' => ['anular_recordatorio' => $d['reservaId']],
                'Control de acceso ⑨' => ['revocar_permiso' => $d['reservaId']],
            ],
            'Penalización aplicada' => [
                'Facturación y cobros ⑥' => ['concept_code' => 'PEN-CANC', 'qty' => 1, 'booking_ref' => $d['reservaId'], 'precio_conservado' => $d['monto']],
            ],
            default => ['En proceso' => $d],
        };
    }
}
