<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hsecm_tasklists', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('site', 100)->nullable();
            $table->string('perusahaan', 255);
            $table->dateTime('batch_slot');
            $table->string('status', 32)->default('open'); // open|partial|closed
            $table->unsignedInteger('escalate_count')->default(0);
            $table->dateTime('last_escalated_at')->nullable();
            $table->dateTime('next_escalate_at')->nullable()->index(name: 'idx_hsecm_tl_next_escalate');
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_slot', 'site', 'perusahaan'], 'uq_hsecm_tl_slot_scope');
            $table->index('status', 'idx_hsecm_tl_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hsecm_tasklists');
    }
};
