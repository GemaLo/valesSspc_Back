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
            $table->unsignedBigInteger('id_user')->nullable();
            $table->foreign('id_user')->references('id_user')->on('users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
