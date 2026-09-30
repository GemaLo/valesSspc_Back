<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statusCards', function (Blueprint $table) {
            $table->id('idStatusCard');
            $table->string('folioCard');
            $table->string('statusCard');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statusCards');
    }
};
