<?php

namespace Tests\Feature\Nodo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Nodo\Compartido\Dominio\Reloj;
use Tests\Support\RelojFijo;
use Tests\TestCase;

/** De punta a punta: login simulado, reserva, bloqueo y cancelación sobre la base de datos. */
class ReservarDesdeLaWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Reloj::class, RelojFijo::en('2026-11-03 09:00'));
    }

    private function ingresarComo(string $usuario): void
    {
        $this->post('/login', ['usuario' => $usuario, 'password' => 'cualquiera']);
        $this->assertAuthenticated();
    }

    private function solicitar(array $datos = []): void
    {
        $this->post('/reservas', $datos + [
            'sala_id' => 'sala-b', 'fecha' => '2026-11-05', 'inicio' => '10:00', 'fin' => '11:00', 'asistentes' => 4,
        ])->assertRedirect();
    }

    public function test_un_miembro_reserva_y_la_agenda_queda_ocupada(): void
    {
        $this->ingresarComo('miembro');

        $this->solicitar();

        $this->assertDatabaseHas('reservas', ['titular_id' => 'm-ana', 'estado' => 'confirmada', 'precio_centavos' => 8000]);
        $this->assertDatabaseHas('agendas_del_recurso', ['sala_id' => 'sala-b', 'version' => 1]);
        $this->assertDatabaseHas('bitacora_de_integracion', ['evento' => 'Reserva confirmada', 'destino' => 'Notificaciones ⑦']);

        $this->get('/reservas')->assertOk()->assertInertia(fn ($pagina) => $pagina
            ->component('Nodo/Reservas')
            ->has('reservas', 1)
            ->where('reservas.0.estado', 'confirmada'));
    }

    public function test_la_segunda_solicitud_sin_margen_se_rechaza(): void
    {
        $this->ingresarComo('miembro');

        $this->solicitar();
        $this->solicitar(['inicio' => '11:05', 'fin' => '12:00']);

        $this->assertDatabaseHas('reservas', ['estado' => 'rechazada']);
        $this->assertDatabaseCount('reservas', 2);
    }

    public function test_un_miembro_no_puede_bloquear_salas(): void
    {
        $this->ingresarComo('miembro');

        $this->post('/bloqueos', [
            'sala_id' => 'sala-a', 'fecha' => '2026-11-05', 'inicio' => '09:00', 'fin' => '10:00', 'motivo' => 'limpieza',
        ])->assertForbidden();
    }

    public function test_recepcion_retira_un_bloqueo_y_la_sala_vuelve_a_estar_disponible(): void
    {
        $this->ingresarComo('recepcion');
        $this->post('/bloqueos', [
            'sala_id' => 'sala-a', 'fecha' => '2026-11-05', 'inicio' => '09:00', 'fin' => '12:00', 'motivo' => 'mantenimiento',
        ]);
        $bloqueoId = json_decode(\DB::table('agendas_del_recurso')->value('bloqueos'), true)[0]['bloqueo_id'];

        $this->delete("/bloqueos/sala-a/2026-11-05/{$bloqueoId}")
            ->assertSessionHas('flash.bannerStyle', 'success');

        $this->assertSame([], json_decode(\DB::table('agendas_del_recurso')->value('bloqueos'), true));
        $this->assertDatabaseHas('bitacora_de_integracion', ['evento' => 'Franja liberada']);

        $this->solicitar(['sala_id' => 'sala-a', 'titular_id' => 'm-ana', 'asistentes' => 2]);
        $this->assertDatabaseHas('reservas', ['sala_id' => 'sala-a', 'estado' => 'confirmada']);
    }

    public function test_un_miembro_no_puede_retirar_bloqueos(): void
    {
        $this->ingresarComo('miembro');

        $this->delete('/bloqueos/sala-a/2026-11-05/cualquiera')->assertForbidden();
    }

    public function test_recepcion_bloquea_hace_una_reserva_asistida_y_cancela(): void
    {
        $this->ingresarComo('recepcion');

        $this->post('/bloqueos', [
            'sala_id' => 'sala-a', 'fecha' => '2026-11-05', 'inicio' => '09:00', 'fin' => '12:00', 'motivo' => 'mantenimiento',
        ])->assertSessionHas('flash.bannerStyle', 'success');

        $this->solicitar(['sala_id' => 'sala-a', 'titular_id' => 'm-ana', 'asistentes' => 2]);
        $this->assertDatabaseHas('reservas', ['sala_id' => 'sala-a', 'estado' => 'rechazada']);

        $this->solicitar(['titular_id' => 'm-ana']);
        $reserva = \DB::table('reservas')->where('sala_id', 'sala-b')->first();
        $this->assertSame('r-carla', $reserva->registrada_por);

        $this->post("/reservas/{$reserva->id}/cancelar")->assertRedirect();

        $this->assertDatabaseHas('reservas', ['id' => $reserva->id, 'estado' => 'cancelada', 'cancelada_por' => 'r-carla']);
        $this->assertDatabaseHas('bitacora_de_integracion', ['evento' => 'Franja liberada']);
    }
}
