<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_escalation_matrices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('applies_to'); // mis. "incident", "work_order"
            $table->unsignedTinyInteger('level'); // urutan eskalasi: 1, 2, 3, ...
            $table->unsignedInteger('delay_minutes'); // menit sejak level sebelumnya / sejak dibuat, jika belum ditangani
            $table->unsignedBigInteger('notify_role_id')->nullable();
            $table->string('channel')->default('in_app'); // in_app, email, both
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('notify_role_id')->references('id')->on('roles')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['applies_to', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_escalation_matrices');
    }
};
