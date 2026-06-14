<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        // Menampilkan halaman login (Blade view)
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Kirim request ke API login yang sudah kamu buat
        $response = Http::post(url('/api/login'), [
            'username' => $request->input('username'),
            'password' => $request->input('password'),
        ]);

        $data = $response->json();

        if (!$data['status']) {
            return back()->withErrors([
                'login' => $data['message'],
            ]);
        }

        // Kalau login berhasil, simpan data user ke session
        session([
            'user' => $data['user'],
        ]);

        // Redirect ke dashboard
        return redirect()->route('dashboard')->with('success', 'Login berhasil!');
    }

    public function logout()
    {
        session()->forget('user');
        return redirect()->route('login')->with('success', 'Logout berhasil!');
    }
}
