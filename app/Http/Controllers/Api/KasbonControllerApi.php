<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KasbonControllerApi extends Controller
{

 public function uploadBuktiTransfer(Request $request)
    {
        $id_kasbon = $request->input('id_kasbon');

        // ─── 1. Validasi ID kasbon ───
        if (empty($id_kasbon)) {
            return response()->json([
                'status'     => false,
                'statusCode' => 400,
                'message'    => 'ID kasbon tidak boleh kosong'
            ], 400);
        }

        // ─── 2. Validasi file bukti ───
        if (!$request->hasFile('bukti')) {
            return response()->json([
                'status'     => false,
                'statusCode' => 400,
                'message'    => 'File bukti tidak ditemukan'
            ], 400);
        }

        $file = $request->file('bukti');

        // Cek ukuran file (maks 2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return response()->json([
                'status'     => false,
                'statusCode' => 413,
                'message'    => 'Ukuran file melebihi 2 MB. Silakan unggah file yang lebih kecil.'
            ], 413);
        }

        // ─── 3. Simpan file ───
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions)) {
            return response()->json([
                'status'     => false,
                'statusCode' => 422,
                'message'    => 'Format file tidak valid. Hanya jpg, jpeg, png, pdf yang diperbolehkan.'
            ], 422);
        }

        $fileName = 'bukti_' . date('Ymd_His') . '.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('assets/file/bukti_transfer', $fileName, 'public');

        // ─── 4. Update database ───
        $updated = DB::table('tbl_kasbon')
            ->where('id_kasbon', $id_kasbon)
            ->update([
                'bukti_transfer' => 'storage/' . $filePath,
                'status'         => 'selesai',
            ]);

        if ($updated) {
            return response()->json([
                'status'     => true,
                'statusCode' => 200,
                'message'    => 'Bukti transfer berhasil diupload dan status diubah ke selesai'
            ], 200);
        }

        return response()->json([
            'status'     => false,
            'statusCode' => 500,
            'message'    => 'Gagal update data kasbon'
        ], 500);
    }

public function history(Request $request)
    {
        // 1. Ambil id_user dari query string atau body
        $id_user = $request->input('id_user');

        // 2. Validasi: id_user wajib ada
        if (empty($id_user)) {
            return response()->json([
                'status' => false,
                'message' => 'ID user wajib dikirim'
            ], 422); // Unprocessable Entity
        }

        // 3. Query data kasbon sesuai id_user
        $result = DB::table('tbl_kasbon')
            ->where('id_user', $id_user)
            ->orderBy('tanggal_pengajuan', 'DESC')
            ->get();

        // 4. Jika tidak ada riwayat, kembalikan status true + data kosong
        if ($result->isEmpty()) {
            return response()->json([
                'status' => true,
                'message' => 'Riwayat kasbon kosong',
                'data' => []
            ], 200);
        }

        // 5. Berhasil: kirim data
        return response()->json([
            'status' => true,
            'message' => 'Riwayat kasbon ditemukan',
            'data' => $result
        ], 200);
    }

 public function pengajuan(Request $request)
    {
        try {
            // ─── 1. Ambil input JSON ───
            $id_user    = trim($request->input('id_user'));
            $nama_user  = trim($request->input('nama_user'));
            $jumlah     = trim($request->input('jumlah'));
            $keterangan = trim($request->input('keterangan', ''));

            // ─── 2. Validasi ───
            if (empty($id_user) || empty($jumlah)) {
                return response()->json([
                    'status'  => false,
                    'message' => 'id_user dan jumlah wajib diisi'
                ], 422);
            }

            if (!is_numeric($jumlah) || $jumlah <= 0) {
                return response()->json([
                    'status'  => false,
                    'message' => 'jumlah harus angka positif'
                ], 422);
            }

            // ─── 3. Transaksi DB ───
            DB::beginTransaction();

            $data = [
                'id_user'    => $id_user,
                'nama_user'  => $nama_user,
                'jumlah'     => $jumlah,
                'keterangan' => $keterangan,
                'status'     => 'menunggu',
                'created_at' => now(),
            ];

            DB::table('tbl_kasbon')->insert($data);

            DB::commit();

            return response()->json([
                'status'  => true,
                'message' => 'Kasbon berhasil diajukan'
            ], 200);

        } catch (Throwable $e) {
            DB::rollBack();
            \Log::error('Exception (pengajuan): ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan pada server'
            ], 500);
        }
    }

 public function allHistory()
    {
        try {
            // ───── 1. Query semua kasbon ─────
            $result = DB::table('tbl_kasbon')->get();

            // ───── 2. Tidak ada data ─────
            if ($result->isEmpty()) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Data kasbon kosong.',
                    'data'    => []
                ], 200);
            }

            // ───── 3. Berhasil ─────
            return response()->json([
                'status'  => true,
                'message' => 'Data kasbon berhasil diambil.',
                'data'    => $result
            ], 200);

        } catch (Throwable $e) {
            \Log::error('Exception (allHistory): ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Terjadi kesalahan pada server.'
            ], 500);
        }
    }
}
