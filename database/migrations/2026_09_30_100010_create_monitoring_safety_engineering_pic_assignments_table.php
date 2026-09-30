<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_safety_engineering_pic_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama', 255);
            $table->string('sid', 50)->nullable();
            $table->string('jabatan', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('catatan', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index('user_id', 'idx_mse_pic_asgn_user');
            $table->index('sid', 'idx_mse_pic_asgn_sid');
            $table->index('nama', 'idx_mse_pic_asgn_nama');
            $table->index('is_active', 'idx_mse_pic_asgn_is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_safety_engineering_pic_assignments');
    }
};
