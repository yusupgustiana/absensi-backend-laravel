<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('karyawan', function (Blueprint $table) {
            $table->increments('id_user');
            $table->integer('id_karyawan')->nullable();

            $table->string('nama', 130);
            $table->string('email', 200)->nullable();
            $table->string('username', 50);

            $table->string('image', 130);
            $table->string('password', 250);

            $table->integer('role_id');
            $table->tinyInteger('is_active');

            $table->integer('jenisakun');

            $table->date('tanggal_daftar');

            $table->string('reset_token', 100)->nullable();
            $table->dateTime('reset_token_expiration')->nullable();
        });

        DB::statement("ALTER TABLE karyawan AUTO_INCREMENT = 68");
    }

    public function down(): void
    {
        Schema::dropIfExists('karyawan');
    }
};