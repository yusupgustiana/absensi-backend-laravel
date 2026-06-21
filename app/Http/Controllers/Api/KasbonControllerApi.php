<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Kasbon;

class KasbonControllerApi extends Controller
{

public function uploadBuktiTransfer(Request $request)
{
    $id_kasbon = $request->input('id_kasbon');

    if (empty($id_kasbon)) {
        return response()->json([
            'status' => false,
            'message' => 'ID kasbon tidak boleh kosong'
        ], 400);
    }

    if (!$request->hasFile('bukti')) {
        return response()->json([
            'status' => false,
            'message' => 'File bukti tidak ditemukan'
        ], 400);
    }

    $file = $request->file('bukti');

    if ($file->getSize() > 2 * 1024 * 1024) {
        return response()->json([
            'status' => false,
            'message' => 'File terlalu besar'
        ], 413);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];

    if (!in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions)) {
        return response()->json([
            'status' => false,
            'message' => 'Format file tidak valid'
        ], 422);
    }

    $fileName = 'bukti_' . date('Ymd_His') . '.' . $file->getClientOriginalExtension();

    $filePath = $file->storeAs(
        'assets/file/bukti_transfer',
        $fileName,
        'public'
    );

    $updated = DB::table('kasbon')
        ->where('id_kasbon', $id_kasbon)
        ->update([
            'bukti_transfer' => 'storage/' . $filePath,

            // 🔥 UPDATE STATUS BARU
            'status_pembayaran' => 'paid',
            'tanggal_lunas' => now(),
        ]);

    if ($updated) {
        return response()->json([
            'status' => true,
            'message' => 'Bukti transfer berhasil diupload'
        ], 200);
    }

    return response()->json([
        'status' => false,
        'message' => 'Gagal update data kasbon'
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
        $result = DB::table('kasbon')
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
        $id_user    = trim($request->input('id_user'));
        $jumlah     = trim($request->input('jumlah'));
        $keterangan = trim($request->input('keterangan', ''));

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

        // ─── Ambil nama dari tabel karyawan ───
        $karyawan = DB::table('karyawan')->where('id_user', $id_user)->first();
        if (!$karyawan) {
            return response()->json([
                'status'  => false,
                'message' => 'Data karyawan tidak ditemukan'
            ], 404);
        }

        DB::beginTransaction();

  $data = [
    'id_user'    => $id_user,
    'nama_user'  => $karyawan->nama,
    'jumlah'     => $jumlah,
    'keterangan' => $keterangan,

    // 🔥 STATUS BARU
    'status_pengajuan'  => 'pending',
    'status_pembayaran' => 'unpaid',

    'tanggal_pengajuan' => now(),
    'created_at' => now(),
];

        DB::table('kasbon')->insert($data);

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

//admin
 public function allHistory()
    {
        try {
            // ───── 1. Query semua kasbon ─────
            $result = DB::table('kasbon')->get();

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


    public function setujui(Request $request)
{

    $kasbon = Kasbon::find($request->id_kasbon);


    if(!$kasbon){

        return response()->json([
            'status'=>false,
            'message'=>'Kasbon tidak ditemukan'
        ]);
    }


    $kasbon->update([

        'status_pengajuan'=>'approved',

        'tanggal_disetujui'=>now()

    ]);


    return response()->json([
        'status'=>true,
        'message'=>'Kasbon disetujui'
    ]);

}

public function tolak(Request $request)
{

    $kasbon = Kasbon::find($request->id_kasbon);


    $kasbon->update([

        'status_pengajuan'=>'rejected',

        'tanggal_ditolak'=>now()

    ]);


    return response()->json([
        'status'=>true,
        'message'=>'Kasbon ditolak'
    ]);

}

public function lunas(Request $request)
{

    $kasbon = Kasbon::find($request->id_kasbon);


    $kasbon->update([

        'status_pembayaran'=>'paid',

        'tanggal_lunas'=>now()

    ]);


    return response()->json([
        'status'=>true,
        'message'=>'Kasbon sudah lunas'
    ]);

}

    }
