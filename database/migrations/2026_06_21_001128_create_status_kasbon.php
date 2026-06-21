<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up()
{
    Schema::table('kasbon', function (Blueprint $table) {


        $table->enum('status_pengajuan',[
            'pending',
            'approved',
            'rejected'
        ])
        ->default('pending')
        ->after('keterangan');


        $table->enum('status_pembayaran',[
            'unpaid',
            'paid'
        ])
        ->default('unpaid')
        ->after('status_pengajuan');


        $table->timestamp('tanggal_disetujui')
            ->nullable();


        $table->timestamp('tanggal_ditolak')
            ->nullable();


        $table->timestamp('tanggal_lunas')
            ->nullable();

    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('status_kasbon');
    }
};
