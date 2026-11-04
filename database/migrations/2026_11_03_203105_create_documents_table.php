<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id('idDocument');
            $table->string('document');
            $table->unsignedBigInteger('idTypeDocument');
            $table->foreign('idTypeDocument')->references('idTypeDocument')->on('typeDocuments');
            $table->unsignedBigInteger('idRegister');
            $table->foreign('idRegister')->references('idRegister')->on('registers');
            $table->boolean('status')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
