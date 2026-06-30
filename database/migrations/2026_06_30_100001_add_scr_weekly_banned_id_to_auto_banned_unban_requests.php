<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('auto_banned_unban_requests')) {
            return;
        }

        Schema::table('auto_banned_unban_requests', function (Blueprint $table): void {
            if (! Schema::hasColumn('auto_banned_unban_requests', 'scr_weekly_banned_id')) {
                $table->unsignedBigInteger('scr_weekly_banned_id')
                    ->nullable()
                    ->after('scr_daily_banned_id');

                $table->index(
                    'scr_weekly_banned_id',
                    'idx_auto_banned_unban_requests_scr_weekly'
                );
            }
        });

        if (Schema::hasTable('scr_weekly_banned')) {
            Schema::table('auto_banned_unban_requests', function (Blueprint $table): void {
                $table->foreign('scr_weekly_banned_id', 'fk_auto_banned_unban_scr_weekly')
                    ->references('id')
                    ->on('scr_weekly_banned')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('auto_banned_unban_requests')) {
            return;
        }

        Schema::table('auto_banned_unban_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('auto_banned_unban_requests', 'scr_weekly_banned_id')) {
                try {
                    $table->dropForeign('fk_auto_banned_unban_scr_weekly');
                } catch (\Throwable) {
                    // FK mungkin belum pernah dibuat jika scr_weekly_banned tidak ada saat migrate up.
                }

                $table->dropIndex('idx_auto_banned_unban_requests_scr_weekly');
                $table->dropColumn('scr_weekly_banned_id');
            }
        });
    }
};
