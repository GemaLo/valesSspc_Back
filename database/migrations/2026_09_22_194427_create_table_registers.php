<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registers', function (Blueprint $table) {
            $table->id('idregister');
            $table->string('numEmpleado');
            $table->string('nomEmpleado');
            $table->string('appEmpleado');
            $table->string('rfc');
            $table->string('curp');
            $table->string('unidad');
            $table->string('cargo')->nullable();
            $table->string('ss')->nullable();
            $table->string('nivel')->nullable();
            $table->string('ubicacion')->nullable();
            $table->string('clave_UR')->nullable();
            $table->string('modalidad')->nullable();
            $table->string('cuentaInicial')->nullable();
            $table->unsignedBigInteger('idStatus');
            $table->foreign('idStatus')->references('idStatus')->on('status');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};
