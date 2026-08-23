<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_checklist_template_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('checklist_template_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('item_text');
            $table->string('answer_type')->default('compliance'); // compliance (Sesuai/Tidak Sesuai/Tidak Berlaku), measurement, text
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->foreign('checklist_template_id')->references('id')->on('er_checklist_templates')->cascadeOnDelete();
            $table->index('checklist_template_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_checklist_template_items');
    }
};
