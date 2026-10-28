<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Disponibilidad: una agenda por sala y día, con versión para el bloqueo optimista. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendas_del_recurso', function (Blueprint $table) {
            $table->id();
            $table->string('sala_id');
            $table->date('fecha');
            $table->unsignedInteger('version');
            $table->json('ocupaciones');
            $table->json('bloqueos');
            $table->timestamps();

            $table->unique(['sala_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendas_del_recurso');
    }
};
