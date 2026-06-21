<?php

namespace App\Http\Controllers\Api;

use App\Models\Karyawan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProfileControllerApi extends BaseControllerApi
{
    /**
     * Get Profile
     */
    public function profile(Request $request): JsonResponse
    {
        $idUser = $request->query('id_user');

        if (!$idUser) {
            return $this->responseApiProfile(
                false,
                'ID user wajib diisi',
                null,
                Response::HTTP_BAD_REQUEST
            );
        }

        $user = Karyawan::where(
            'id_user',
            $idUser
        )->first();

        if (!$user) {
            return $this->responseApiProfile(
                false,
                'User tidak ditemukan',
                null,
                Response::HTTP_NOT_FOUND
            );
        }

        return $this->responseApiProfile(
            true,
            'Profile berhasil diambil',
            $user,
            Response::HTTP_OK
        );
    }

    /**
     * Update Profile
     */
public function updateProfile(Request $request): JsonResponse
{
    // DEBUG (hapus setelah selesai)
    // dd($request->all(), $request->file('image'));

    $idUser = $request->input('id');   // 👈 mapping dari Flutter
    $nama   = $request->input('name'); // 👈 mapping dari Flutter

    $request->validate([
        'id' => 'required',
    ]);

    $user = Karyawan::where('id_user', $idUser)->first();

    if (!$user) {
        return $this->responseApiProfile(
            false,
            'User tidak ditemukan',
            null,
            Response::HTTP_NOT_FOUND
        );
    }

    // UPDATE FIELD (pakai mapping baru)
    $user->nama = $nama ?? $user->nama;
    $user->username = $request->username ?? $user->username;
    $user->email = $request->email ?? $user->email;

    // IMAGE UPLOAD
    if ($request->hasFile('image')) {

        if (!empty($user->image)) {
            Storage::disk('public')
                ->delete('profile/' . $user->image);
        }

        $file = $request->file('image');

        $filename = time() . '_' . $file->getClientOriginalName();

        $file->storeAs(
            'profile',
            $filename,
            'public'
        );

        $user->image = $filename;
    }

    $user->save();

    return $this->responseApiProfile(
        true,
        'Profil berhasil diperbarui',
        $user,
        Response::HTTP_OK
    );
}
    /**
     * Change Password
     */
    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'id_user' => 'required',
            'current_password' => 'required',
            'new_password' => 'required|min:5',
            'new_password_confirmation' => 'required|same:new_password',
        ]);

        $user = Karyawan::where(
            'id_user',
            $request->id_user
        )->first();

        if (!$user) {
            return $this->responseApiProfile(
                false,
                'User tidak ditemukan',
                null,
                Response::HTTP_NOT_FOUND
            );
        }

        if (!Hash::check(
            $request->current_password,
            $user->password
        )) {
            return $this->responseApiProfile(
                false,
                'Password lama salah',
                null,
                Response::HTTP_BAD_REQUEST
            );
        }

        if (Hash::check(
            $request->new_password,
            $user->password
        )) {
            return $this->responseApiProfile(
                false,
                'Password baru tidak boleh sama dengan password lama',
                null,
                Response::HTTP_BAD_REQUEST
            );
        }

        $user->update([
            'password' => Hash::make(
                $request->new_password
            )
        ]);

        return $this->responseApiProfile(
            true,
            'Password berhasil diubah',
            null,
            Response::HTTP_OK
        );
    }

    /**
     * Update Email
     */
    public function updateEmail(Request $request): JsonResponse
    {
        $request->validate([
            'id_user' => 'required',
            'email' => 'required|email',
        ]);

        $user = Karyawan::where(
            'id_user',
            $request->id_user
        )->first();

        if (!$user) {
            return $this->responseApiProfile(
                false,
                'User tidak ditemukan',
                null,
                Response::HTTP_NOT_FOUND
            );
        }

        $user->update([
            'email' => $request->email
        ]);

        return $this->responseApiProfile(
            true,
            'Email berhasil diperbarui',
            $user,
            Response::HTTP_OK
        );
    }
}