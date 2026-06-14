<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->tinyInteger('checkin_approved')
                ->default(0)
                ->after('checkin_deskripsi');

            $table->tinyInteger('checkout_approved')
                ->default(0)
                ->after('checkout_deskripsi');
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropColumn([
                'checkin_approved',
                'checkout_approved',
            ]);
        });
    }
};