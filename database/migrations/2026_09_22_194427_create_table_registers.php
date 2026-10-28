<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registers', function (Blueprint $table) {
            $table->id('IDREGISTER');

            $table->string('NOMBRECOMPLETO');
            $table->string('NUMEMPLEADO')->nullable();
            $table->string('NOMEMPLEADO')->nullable();
            $table->string('APPEMPLEADO')->nullable();
            $table->string('APMEMPLEADO')->nullable();
            $table->string('UR');
            $table->string('RFC');
            $table->string('CURP');
            $table->string('NSS')->nullable();
            $table->string('NIVEL')->nullable();
            $table->string('PUESTO')->nullable();
            $table->string('UBICACION')->nullable();
            $table->string('IDENLACE')->nullable();
            $table->string('CLAVE')->nullable();
            $table->string('CUENTAINICIAL')->nullable();
            $table->string('MODALIDAD')->nullable();
            $table->unsignedBigInteger('IDSTATUS');
            $table->string('ORIGEN')->nullable();
            $table->string('PENSION')->nullable();
            $table->string('IMPORTE')->nullable();

            $table->foreign('IDSTATUS')
                ->references('IDSTATUS')
                ->on('status');
            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registers');
    }
};