<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('safety_score_logs', function (Blueprint $table) {
            $table->id();
            $table->string('driver_id', 50);
            $table->string('trip_id', 50)->nullable();
            $table->timestamp('timestamp');
            $table->decimal('ear', 10, 6)->nullable();
            $table->decimal('perclos_60s', 10, 6)->nullable();
            $table->unsignedInteger('blink_60s')->default(0);
            $table->unsignedInteger('microsleep_60s')->default(0);
            $table->decimal('fatigue', 10, 6)->nullable();
            $table->decimal('drift', 10, 6)->nullable();
            $table->decimal('safety_score', 10, 6)->nullable();
            $table->string('status', 20)->nullable(); // Safe, Caution, Attention
            $table->timestamps();

            $table->index(['driver_id', 'timestamp']);
            $table->index('trip_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_score_logs');
    }
};

