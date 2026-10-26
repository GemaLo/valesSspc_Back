<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id('id_user');
            $table->string('numEmpleado');
            $table->string('firstName');
            $table->string('lastName');
            $table->string('middleName');
            $table->string('nivel');
            $table->string('unidad');
            $table->boolean('active');
            $table->unsignedBigInteger('idType');
            $table->foreign('idType')->references('idType')->on('typeUser');
            $table->string('email')->unique();
            $table->string('origen');
            $table->string('password');
            $table->string('must_change_password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
