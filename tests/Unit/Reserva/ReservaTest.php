<?php

namespace Tests\Unit\Reserva;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Franja;
use Nodo\Compartido\Dominio\SalaId;
use Nodo\Reserva\Dominio\Asistentes;
use Nodo\Reserva\Dominio\Dinero;
use Nodo\Reserva\Dominio\EstadoDeReserva;
use Nodo\Reserva\Dominio\Eventos\PenalizacionAplicada;
use Nodo\Reserva\Dominio\Eventos\ReservaCancelada;
use Nodo\Reserva\Dominio\Reserva;
use Nodo\Reserva\Dominio\ReservaId;
use Nodo\Reserva\Dominio\Responsable;
use PHPUnit\Framework\TestCase;

class ReservaTest extends TestCase
{
    // La franja de todos los casos: jueves 5 de noviembre, de 10:00 a 11:00.
    private const AFORO = 8;

    private function franja(): Franja
    {
        return Franja::de(new DateTimeImmutable('2026-11-05 10:00'), new DateTimeImmutable('2026-11-05 11:00'));
    }

    private function solicitar(string $ahora, ?Responsable $responsable = null, int $asistentes = 4): Reserva
    {
        return Reserva::solicitar(
            ReservaId::nueva(),
            $responsable ?? Responsable::titular('m-ana'),
            new SalaId('sala-b'),
            $this->franja(),
            new Asistentes($asistentes),
            new DateTimeImmutable($ahora),
        );
    }

    private function confirmada(): Reserva
    {
        $reserva = $this->solicitar('2026-11-03 09:00');
        $reserva->confirmar(self::AFORO, true, new Dinero(8000, 'BOB'), new DateTimeImmutable('2026-11-03 09:00'));
        $reserva->extraerEventos();

        return $reserva;
    }

    public function test_un_miembro_al_dia_con_24_horas_confirma(): void
    {
        $reserva = $this->confirmada();

        $this->assertSame(EstadoDeReserva::Confirmada, $reserva->estado());
        $this->assertSame('80.00 BOB', (string) $reserva->precio());
    }

    public function test_inv_r4_con_menos_de_3_horas_no_se_registra(): void
    {
        $this->expectException(ErrorDeDominio::class);
        $this->expectExceptionMessage('INV-R4');

        $this->solicitar('2026-11-05 08:00', Responsable::asistida('m-ana', 'r-carla'));
    }

    public function test_inv_r4_de_3_a_24_horas_solo_como_reserva_asistida(): void
    {
        try {
            $this->solicitar('2026-11-04 20:00');
            $this->fail('Un miembro no puede solicitar por sí mismo con menos de 24 h.');
        } catch (ErrorDeDominio $error) {
            $this->assertSame('INV-R4', $error->invariante);
        }

        $asistida = $this->solicitar('2026-11-04 20:00', Responsable::asistida('m-ana', 'r-carla'));

        $this->assertSame(EstadoDeReserva::Solicitada, $asistida->estado());
    }

    public function test_inv_r8_la_reserva_asistida_guarda_titular_y_quien_la_registro(): void
    {
        $reserva = $this->solicitar('2026-11-04 20:00', Responsable::asistida('m-ana', 'r-carla'));
        $reserva->confirmar(self::AFORO, true, new Dinero(8000, 'BOB'), new DateTimeImmutable('2026-11-04 20:00'));

        $this->assertSame('m-ana', $reserva->responsable()->titularId);
        $this->assertSame('r-carla', $reserva->responsable()->registradaPor);

        $this->expectException(ErrorDeDominio::class);
        $this->expectExceptionMessage('INV-R8');
        Responsable::asistida('m-ana', ' ');
    }

    public function test_inv_r5_los_asistentes_nunca_superan_el_aforo(): void
    {
        $reserva = $this->solicitar('2026-11-03 09:00', asistentes: 9);

        $this->assertStringContainsString('INV-R5', $reserva->motivoParaNoAceptar(self::AFORO, true, new DateTimeImmutable('2026-11-03 09:00')));

        $this->expectException(ErrorDeDominio::class);
        $this->expectExceptionMessage('INV-R5');
        $reserva->confirmar(self::AFORO, true, new Dinero(8000, 'BOB'), new DateTimeImmutable('2026-11-03 09:00'));
    }

    public function test_inv_r3_no_confirma_si_el_miembro_no_esta_al_dia(): void
    {
        $reserva = $this->solicitar('2026-11-03 09:00');

        $this->expectException(ErrorDeDominio::class);
        $this->expectExceptionMessage('INV-R3');
        $reserva->confirmar(self::AFORO, false, new Dinero(8000, 'BOB'), new DateTimeImmutable('2026-11-03 09:00'));
    }

    public function test_inv_r3_la_confirmacion_automatica_exige_24_horas_al_confirmar(): void
    {
        $reserva = $this->solicitar('2026-11-03 09:00');

        $this->expectException(ErrorDeDominio::class);
        $this->expectExceptionMessage('INV-R3');
        $reserva->confirmar(self::AFORO, true, new Dinero(8000, 'BOB'), new DateTimeImmutable('2026-11-04 12:00'));
    }

    public function test_inv_r2_solo_pasa_por_estados_validos(): void
    {
        $reserva = $this->solicitar('2026-11-03 09:00');
        $reserva->rechazar('franja ocupada');

        $this->expectException(ErrorDeDominio::class);
        $this->expectExceptionMessage('INV-R2');
        $reserva->confirmar(self::AFORO, true, new Dinero(8000, 'BOB'), new DateTimeImmutable('2026-11-03 09:00'));
    }

    public function test_cancelar_a_tiempo_no_genera_penalizacion(): void
    {
        $reserva = $this->confirmada();

        $reserva->cancelar('m-ana', new DateTimeImmutable('2026-11-04 10:00'));
        $eventos = $reserva->extraerEventos();

        $this->assertSame(EstadoDeReserva::Cancelada, $reserva->estado());
        $this->assertNull($reserva->penalizacion());
        $this->assertCount(1, $eventos);
        $this->assertFalse($eventos[0]->tardia);
    }

    public function test_inv_r7_cancelar_con_menos_de_4_horas_genera_exactamente_una_penalizacion(): void
    {
        $reserva = $this->confirmada();

        $reserva->cancelar('m-ana', new DateTimeImmutable('2026-11-05 07:00'));
        $eventos = $reserva->extraerEventos();

        $this->assertInstanceOf(ReservaCancelada::class, $eventos[0]);
        $this->assertTrue($eventos[0]->tardia);
        $this->assertInstanceOf(PenalizacionAplicada::class, $eventos[1]);
        $this->assertSame('80.00 BOB', (string) $reserva->penalizacion());

        // Una segunda cancelación no genera otra penalización: INV-R2 la impide.
        $this->expectException(ErrorDeDominio::class);
        $this->expectExceptionMessage('INV-R2');
        $reserva->cancelar('m-ana', new DateTimeImmutable('2026-11-05 07:30'));
    }

    public function test_no_se_cancela_despues_del_inicio(): void
    {
        $reserva = $this->confirmada();

        $this->expectException(ErrorDeDominio::class);
        $reserva->cancelar('m-ana', new DateTimeImmutable('2026-11-05 10:15'));
    }
}
