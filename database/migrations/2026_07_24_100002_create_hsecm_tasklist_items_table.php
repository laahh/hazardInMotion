<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hsecm_tasklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tasklist_id')->constrained('hsecm_tasklists')->cascadeOnDelete();
            $table->string('program_key', 64);
            $table->string('title', 255);
            $table->string('business_key', 255);
            $table->text('action_hint')->nullable();
            $table->string('value_label', 255)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 32)->default('open'); // open|submitted|rejected|approved
            $table->text('remediation_notes')->nullable();
            $table->string('submitted_by_name', 150)->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable()->index(name: 'idx_hsecm_tl_item_reviewed_by');
            $table->string('reviewed_by_name', 150)->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedInteger('submission_batch')->default(0);
            $table->timestamps();

            $table->index(['tasklist_id', 'status'], 'idx_hsecm_tl_items_tl_status');
            $table->unique(['tasklist_id', 'program_key', 'business_key'], 'uq_hsecm_tl_item_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsecm_tasklist_items');
    }
};
