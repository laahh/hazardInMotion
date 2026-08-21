<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_leave_types', function (Blueprint $table): void {
            $table->id();
            $table->string('leave_type')->unique();
            $table->string('available_days')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_leave_types');
    }
};
