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
    Schema::table('karyawan', function (Blueprint $table) {
    $table->boolean('akses_kasbon')->default(false);
    $table->boolean('approved_absen')->default(false);
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karyawan', function (Blueprint $table) {
            $table->dropColumn(['akses_kasbon', 'approved_absen']);
        });
    }
};
