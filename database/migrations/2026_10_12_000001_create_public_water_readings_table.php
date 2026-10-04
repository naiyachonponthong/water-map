<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_water_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('province_id')->constrained()->cascadeOnDelete();
            $table->string('station_id', 32);
            $table->string('name', 200);
            $table->string('datum', 16);
            $table->decimal('value', 12, 3);
            $table->decimal('bank', 12, 3)->nullable();
            $table->dateTime('measured_at');
            $table->unique(['province_id', 'station_id', 'datum', 'measured_at'], 'public_water_observation_unique');
            $table->index(['province_id', 'measured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_water_readings');
    }
};
