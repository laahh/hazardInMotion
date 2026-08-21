<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ohs_event_action_items', function (Blueprint $table): void {
            $table->string('action_item_id')->primary();
            $table->dateTime('timestamp')->index();
            $table->string('event_id')->index();
            $table->text('task');
            $table->string('pic_emp_id')->nullable()->index();
            $table->string('pic_name')->nullable();
            $table->date('due_date')->nullable()->index();
            $table->string('status')->default('Open')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ohs_event_action_items');
    }
};
