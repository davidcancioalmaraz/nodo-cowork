<?php

namespace Tests\Unit\Disponibilidad;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Disponibilidad\Dominio\AgendaDelRecurso;
use Nodo\Disponibilidad\Dominio\Eventos\FranjaLiberada;
use Nodo\Disponibilidad\Dominio\Eventos\SalaBloqueada;
use Nodo\Disponibilidad\Dominio\MotivoDeBloqueo;
use PHPUnit\Framework\TestCase;

class AgendaDelRecursoTest extends TestCase
{
    private AgendaDelRecurso $agenda;

    protected function setUp(): void
    {
        $this->agenda = AgendaDelRecurso::abrir(new SalaId('sala-b'), '2026-11-05');
        $this->agenda->ocupar($this->franja('10:00', '11:00'), 'r-1');
    }

    private function franja(string $inicio, string $fin): Franja
    {
        return Franja::de(new DateTimeImmutable("2026-11-05 {$inicio}"), new DateTimeImmutable("2026-11-05 {$fin}"));
    }

    public function test_ocupa_una_franja_libre(): void
    {
        $resultado = $this->agenda->ocupar($this->franja('14:00', '15:00'), 'r-2');

        $this->assertTrue($resultado->ocupada);
        $this->assertCount(2, $this->agenda->ocupaciones());
    }

    public function test_inv_d1_nunca_dos_ocupaciones_se_solapan(): void
    {
        $resultado = $this->agenda->ocupar($this->franja('10:30', '11:30'), 'r-2');

        $this->assertFalse($resultado->ocupada);
        $this->assertStringContainsString('INV-D1', $resultado->motivo);
        $this->assertCount(1, $this->agenda->ocupaciones());
    }

    public function test_inv_d2_siempre_quedan_15_minutos_de_margen(): void
    {
        $pegada = $this->agenda->ocupar($this->franja('11:05', '12:00'), 'r-2');
        $conMargen = $this->agenda->ocupar($this->franja('11:15', '12:00'), 'r-3');

        $this->assertFalse($pegada->ocupada);
        $this->assertStringContainsString('INV-D2', $pegada->motivo);
        $this->assertTrue($conMargen->ocupada);
    }

    public function test_inv_d3_una_franja_bloqueada_no_admite_nuevas_ocupaciones(): void
    {
        $this->agenda->bloquear($this->franja('14:00', '16:00'), MotivoDeBloqueo::Mantenimiento, 'r-carla');

        $dentro = $this->agenda->ocupar($this->franja('15:00', '15:30'), 'r-2');
        $pegada = $this->agenda->ocupar($this->franja('16:05', '17:00'), 'r-3');

        $this->assertStringContainsString('INV-D3', $dentro->motivo);
        $this->assertStringContainsString('INV-D2', $pegada->motivo);
    }

    public function test_bloquear_sobre_una_reserva_la_deja_como_afectada(): void
    {
        $this->agenda->bloquear($this->franja('10:30', '12:00'), MotivoDeBloqueo::Limpieza, 'r-carla');

        [$evento] = $this->agenda->extraerEventos();

        $this->assertInstanceOf(SalaBloqueada::class, $evento);
        $this->assertSame(['r-1'], $evento->reservasAfectadas);
    }

    public function test_inv_d4_solo_se_libera_una_ocupacion_existente_y_una_sola_vez(): void
    {
        $this->agenda->liberar('r-1');

        $this->assertInstanceOf(FranjaLiberada::class, $this->agenda->extraerEventos()[0]);
        $this->assertTrue($this->agenda->ocupar($this->franja('10:00', '11:00'), 'r-2')->ocupada);

        $this->expectException(ErrorDeDominio::class);
        $this->expectExceptionMessage('INV-D4');
        $this->agenda->liberar('r-1');
    }

    public function test_retirar_un_bloqueo_libera_la_franja(): void
    {
        $bloqueo = $this->agenda->bloquear($this->franja('14:00', '16:00'), MotivoDeBloqueo::Mantenimiento, 'r-carla');
        $this->agenda->extraerEventos();

        $this->agenda->retirarBloqueo($bloqueo->bloqueoId);

        [$evento] = $this->agenda->extraerEventos();
        $this->assertInstanceOf(FranjaLiberada::class, $evento);
        $this->assertSame($bloqueo->bloqueoId, $evento->bloqueoId);
        $this->assertSame([], $this->agenda->bloqueos());
        $this->assertTrue($this->agenda->ocupar($this->franja('14:30', '15:30'), 'r-2')->ocupada);
    }

    public function test_solo_se_retira_un_bloqueo_existente(): void
    {
        $bloqueo = $this->agenda->bloquear($this->franja('14:00', '16:00'), MotivoDeBloqueo::Limpieza, 'r-carla');
        $this->agenda->retirarBloqueo($bloqueo->bloqueoId);

        $this->expectException(ErrorDeDominio::class);
        $this->agenda->retirarBloqueo($bloqueo->bloqueoId);
    }

    public function test_ocupar_no_emite_eventos(): void
    {
        $this->assertSame([], $this->agenda->extraerEventos());
    }

    public function test_no_acepta_franjas_de_otro_dia(): void
    {
        $this->expectException(ErrorDeDominio::class);

        $this->agenda->ocupar(
            Franja::de(new DateTimeImmutable('2026-11-06 10:00'), new DateTimeImmutable('2026-11-06 11:00')),
            'r-2',
        );
    }
}
