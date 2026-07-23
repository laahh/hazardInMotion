<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hsecm_tasklist_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasklist_item_id')->constrained('hsecm_tasklist_items')->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('submission_batch')->default(1);
            $table->timestamps();

            $table->index(['tasklist_item_id', 'submission_batch'], 'idx_hsecm_tl_ev_item_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsecm_tasklist_evidences');
    }
};
