<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_notification_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // mis. "apar_expiring", "wo_overdue"
            $table->string('name');
            $table->string('channel')->default('in_app'); // in_app, email, both
            $table->string('title');
            $table->text('message'); // mendukung placeholder {{variabel}}
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('er_notification_templates');
    }
};
