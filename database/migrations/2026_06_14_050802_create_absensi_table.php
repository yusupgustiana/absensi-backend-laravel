<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('id_user');
            $table->integer('id_headerspk')->nullable();

            $table->date('tanggal');

            $table->dateTime('checkin_time')->nullable();
            $table->string('checkin_foto')->nullable();
            $table->string('checkin_latitude', 100)->nullable();
            $table->string('checkin_longitude', 100)->nullable();
            $table->text('checkin_deskripsi')->nullable();

            $table->dateTime('checkout_time')->nullable();
            $table->string('checkout_foto')->nullable();
            $table->string('checkout_latitude', 100)->nullable();
            $table->string('checkout_longitude', 100)->nullable();
            $table->text('checkout_deskripsi')->nullable();

            $table->tinyInteger('deleted')->default(0);

            $table->integer('create_by')->nullable();
            $table->dateTime('create_date')
                ->useCurrent();

            $table->integer('update_by')->nullable();
            $table->dateTime('update_date')->nullable();

            $table->index('id_user', 'idx_user_date');
            $table->index('checkin_time', 'idx_checkin_time');
            $table->index('checkout_time', 'idx_checkout_time');
        });

        DB::statement('ALTER TABLE absensi AUTO_INCREMENT = 861');
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};