<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_employees', function (Blueprint $table): void {
            $table->string('emp_id')->primary();
            $table->string('sid')->nullable()->index();
            $table->string('emp_name')->index();
            $table->string('position')->nullable();
            $table->string('team')->nullable()->index();
            $table->string('site_dedicated')->nullable()->index();
            $table->string('company')->nullable()->index();
            $table->string('photo_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_employees');
    }
};
