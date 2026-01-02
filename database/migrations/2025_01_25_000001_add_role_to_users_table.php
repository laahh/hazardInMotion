<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Opsi 1: Menggunakan kolom role (lebih fleksibel untuk multiple roles)
            $table->string('role')->default('user')->after('email');
            // Opsi 2: Atau menggunakan kolom is_admin (lebih sederhana)
            // $table->boolean('is_admin')->default(false)->after('email');
        });

        // Set user dengan email admin sebagai admin
        DB::table('users')
            ->where('email', 'admin@gmail.com')
            ->orWhere('email', 'like', '%admin%')
            ->update(['role' => 'admin']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
            // Jika menggunakan is_admin:
            // $table->dropColumn('is_admin');
        });
    }
};

