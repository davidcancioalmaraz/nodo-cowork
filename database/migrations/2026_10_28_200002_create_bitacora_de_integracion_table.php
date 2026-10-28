<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Lo que cruza hacia los vecinos simulados, para mostrarlo en la demo. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora_de_integracion', function (Blueprint $table) {
            $table->id();
            $table->string('evento');
            $table->string('destino');
            $table->json('mensaje');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora_de_integracion');
    }
};
