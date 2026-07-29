<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('typeUser', function (Blueprint $table) {
            $table->id('idType');
            $table->string('typeUser');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('typeUser');
    }
};
