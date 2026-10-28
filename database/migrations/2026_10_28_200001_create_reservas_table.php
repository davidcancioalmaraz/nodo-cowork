<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reserva: el aggregate Reserva como una fila. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('titular_id')->index();
            $table->string('registrada_por')->nullable();
            $table->string('sala_id');
            $table->dateTime('inicio');
            $table->dateTime('fin');
            $table->unsignedInteger('asistentes');
            $table->string('estado');
            $table->dateTime('solicitada_en');
            $table->string('moneda', 3)->nullable();
            $table->unsignedBigInteger('precio_centavos')->nullable();
            $table->unsignedBigInteger('penalizacion_centavos')->nullable();
            $table->text('motivo_de_rechazo')->nullable();
            $table->string('cancelada_por')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
    }
};
