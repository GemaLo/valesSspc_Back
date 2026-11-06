<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $table) {
            $table->id('idPerson');
            $table->unsignedBigInteger('idTypePerson');
            $table->string('nomPerson', 200);
            $table->string('appPerson', 200);
            $table->string('apmPerson', 200);
            $table->string('curp', 18)->nullable();
            $table->string('rfc')->nullable();
            $table->unsignedBigInteger('idregister')->nullable();
            $table->foreign('idTypePerson')->references('idTypePerson')->on('typePerson');
            $table->foreign('idregister')->references('idRegister')->on('registers');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas');
    }
};
