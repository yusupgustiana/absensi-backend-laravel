<?php

namespace Database\Seeders;

use App\Models\Karyawan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class KaryawanSeeder extends Seeder
{
    public function run(): void
    {
        Karyawan::create([
            'id_karyawan' => 1,
            'nama' => 'Administrator',
            'email' => 'admin@example.com',
            'username' => 'admin',
            'image' => 'default.png',
            'password' => Hash::make('admin123'),
            'role_id' => 1,
            'is_active' => 1,
            'jenisakun' => 1,
            'tanggal_daftar' => now()->toDateString(),
        ]);
    }
}