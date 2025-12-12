<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('telegram_messages')) {
            return;
        }

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('update_id')->unique();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->unsignedBigInteger('chat_id')->nullable();
            $table->string('chat_type')->nullable();
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->text('text')->nullable();
            // Use longText instead of json for MySQL compatibility
            $table->longText('raw_payload');
            $table->timestamp('message_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};


