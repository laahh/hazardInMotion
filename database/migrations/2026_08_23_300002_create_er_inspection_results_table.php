<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_inspection_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inspection_id');
            $table->uuid('checklist_template_item_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('item_text_snapshot'); // salinan pertanyaan saat inspeksi, tetap terbaca walau template berubah
            $table->string('answer_type_snapshot')->default('compliance');
            $table->string('answer_value')->nullable(); // sesuai|tidak_sesuai|tidak_berlaku, atau angka, atau teks bebas
            $table->text('notes')->nullable();
            $table->string('photo_before_path')->nullable();
            $table->string('photo_after_path')->nullable();
            $table->timestamps();

            $table->foreign('inspection_id')->references('id')->on('er_inspections')->cascadeOnDelete();
            $table->foreign('checklist_template_item_id')->references('id')->on('er_checklist_template_items')->nullOnDelete();
            $table->index('inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_inspection_results');
    }
};
