<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kasbon', function (Blueprint $table) {
            $table->bigIncrements('id_kasbon');

            // relasi ke karyawan.id_user
            $table->unsignedInteger('id_user'); 

            $table->string('nama_user')->nullable();
            $table->decimal('jumlah', 15, 2);
            $table->text('keterangan')->nullable();
            $table->string('status')->default('menunggu');
            $table->string('bukti_transfer')->nullable();
            $table->timestamp('tanggal_pengajuan')->useCurrent();
            $table->timestamps();

            // foreign key ke tabel karyawan
            $table->foreign('id_user')
                  ->references('id_user')
                  ->on('karyawan')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kasbon');
    }
};
