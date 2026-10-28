<?php

namespace Tests\Unit\Compartido;

use DateTimeImmutable;
use Nodo\Compartido\Dominio\ErrorDeDominio;
use Nodo\Compartido\Dominio\Franja;
use PHPUnit\Framework\TestCase;

class FranjaTest extends TestCase
{
    private function franja(string $inicio, string $fin): Franja
    {
        return Franja::de(new DateTimeImmutable("2026-11-05 {$inicio}"), new DateTimeImmutable("2026-11-05 {$fin}"));
    }

    public function test_el_inicio_debe_ser_anterior_al_fin(): void
    {
        $this->expectException(ErrorDeDominio::class);

        $this->franja('11:00', '10:00');
    }

    public function test_una_franja_empieza_y_termina_el_mismo_dia(): void
    {
        $this->expectException(ErrorDeDominio::class);

        Franja::de(new DateTimeImmutable('2026-11-05 23:00'), new DateTimeImmutable('2026-11-06 01:00'));
    }

    public function test_sabe_si_se_solapa_con_otra(): void
    {
        $diezAOnce = $this->franja('10:00', '11:00');

        $this->assertTrue($diezAOnce->seSolapaCon($this->franja('10:30', '11:30')));
        $this->assertFalse($diezAOnce->seSolapaCon($this->franja('11:00', '12:00')));
        $this->assertTrue($diezAOnce->ampliadaEn(15)->seSolapaCon($this->franja('11:00', '12:00')));
    }

    public function test_calcula_sus_horas_y_su_dia(): void
    {
        $franja = $this->franja('09:00', '10:30');

        $this->assertSame(1.5, $franja->horas());
        $this->assertSame('2026-11-05', $franja->dia());
    }
}
