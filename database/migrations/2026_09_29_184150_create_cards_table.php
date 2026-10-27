<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id('idCard');
            $table->string('cuenta');
            $table->string('folioCard');
            $table->unsignedBigInteger('idRegister')->nullable();
            $table->foreign('idRegister')->references('idRegister')->on('registers');
            $table->unsignedBigInteger('idStatus')->nullable();
            $table->foreign('idStatus')->references('idStatus')->on('status');
            $table->longtext('observaciones')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
