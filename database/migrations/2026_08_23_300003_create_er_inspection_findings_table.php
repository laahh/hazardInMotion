<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_inspection_findings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inspection_id');
            $table->uuid('inspection_result_id')->nullable();
            $table->text('description');
            $table->unsignedBigInteger('pic_id')->nullable(); // PIC penanggung jawab tindak lanjut
            $table->date('target_date')->nullable();
            $table->string('status')->default('open'); // open, in_progress, resolved
            // work_order_id sengaja BUKAN foreign key: tabel er_work_orders baru dibuat di Fase 5.
            // Fase 5 akan menambahkan constraint FK via migration terpisah setelah tabelnya ada.
            $table->uuid('work_order_id')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolved_notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('inspection_id')->references('id')->on('er_inspections')->cascadeOnDelete();
            $table->foreign('inspection_result_id')->references('id')->on('er_inspection_results')->nullOnDelete();
            $table->foreign('pic_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_inspection_findings');
    }
};
