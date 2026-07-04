<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_safety_engineering_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_id')
                ->constrained('monitoring_safety_engineering_records')
                ->cascadeOnDelete();
            $table->string('phase', 30);
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('phase', 'idx_mse_ev_phase');
            $table->index(['record_id', 'phase'], 'idx_mse_ev_record_phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_safety_engineering_evidences');
    }
};
