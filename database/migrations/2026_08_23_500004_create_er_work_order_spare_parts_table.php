<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_work_order_spare_parts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('work_order_id');
            $table->uuid('spare_part_id');
            $table->unsignedInteger('quantity_used')->default(1);
            $table->decimal('unit_cost_snapshot', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('work_order_id')->references('id')->on('er_work_orders')->cascadeOnDelete();
            $table->foreign('spare_part_id')->references('id')->on('er_spare_parts')->restrictOnDelete();
            $table->index('work_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_work_order_spare_parts');
    }
};
