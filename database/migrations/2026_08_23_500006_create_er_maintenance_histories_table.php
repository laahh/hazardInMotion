<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_maintenance_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_order_id');
            $table->string('target_type');
            $table->uuid('target_id');
            $table->string('work_type');
            $table->text('summary')->nullable(); // salinan result_notes work order saat closed
            $table->decimal('total_cost', 14, 2)->nullable();
            $table->unsignedBigInteger('technician_id')->nullable();
            $table->timestamp('completed_at');

            $table->foreign('work_order_id')->references('id')->on('er_work_orders')->cascadeOnDelete();
            $table->foreign('technician_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_maintenance_histories');
    }
};
