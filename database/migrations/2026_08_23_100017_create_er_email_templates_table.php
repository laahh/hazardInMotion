<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('er_email_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique(); // dipakai sebagai key pemanggilan dari kode, mis. "incident_new"
            $table->string('name');
            $table->string('subject');
            $table->longText('body_html'); // mendukung placeholder {{variabel}}
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
        Schema::dropIfExists('er_email_templates');
    }
};
