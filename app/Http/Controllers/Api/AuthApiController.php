<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Karyawan;

class AuthApiController extends Controller
{
    public function login(Request $request)
    {
        $username = $request->input('username');
        $password = $request->input('password');

        if (!$username || !$password) {
            return response()->json([
                'status' => false,
                'message' => 'Username dan password wajib diisi.'
            ]);
        }

        $user = Karyawan::where('username', $username)->first();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Username tidak terdaftar.'
            ]);
        }

        if ($user->is_active != 1) {
            return response()->json([
                'status' => false,
                'message' => 'Akun belum aktif.'
            ]);
        }

        if (!password_verify($password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Password salah.'
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Login berhasil',
            'user' => [
                'id_user'     => (string) $user->id_user,
                'id_absensi' => (string) $user->id_absensi,
                'username'    => $user->username,
                'nama'        => $user->nama,
                'role_id'     => (string) $user->role_id,
                'image'       => url('assets/img/profile/' . $user->image),
            ]
        ]);
    }
}