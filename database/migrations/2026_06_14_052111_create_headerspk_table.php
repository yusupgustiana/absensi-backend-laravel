<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('headerspk', function (Blueprint $table) {
            $table->integer('id_headerspk')->primary();

            $table->integer('id_headerproject');
            $table->string('nomorspk', 50);

            $table->string('kodecostcenter', 50);
            $table->integer('kodecostcenterspk');

            $table->date('tanggalmulai');
            $table->date('tanggalselesai');

            $table->integer('id_karyawan');
            $table->integer('id_client')->nullable();

            $table->string('jumlah', 50);

            $table->integer('status');

            $table->string('memo', 10000)->nullable();

            $table->string('latitude', 100);
            $table->string('longitude', 100);

            $table->tinyInteger('deleted');

            $table->string('create_by', 50);
            $table->dateTime('create_date');

            $table->string('update_by', 50);
            $table->dateTime('update_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('headerspk');
    }
};