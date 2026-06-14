<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\Absensi;

class AbsensiApiController extends Controller
{
    // POST: /api/absensi/listAbsensi
public function listAbsensi(Request $request)
{
    $id_user = $request->input('id_user');

    if (!$id_user) {
        return response()->json([
            'status' => false,
            'message' => 'Parameter id_user wajib diisi.'
        ], 400);
    }

    // Ambil data user
    $user = DB::table('user')->where('id_user', $id_user)->first();

    if (!$user) {
        return response()->json([
            'status' => false,
            'message' => 'User tidak ditemukan.'
        ], 404);
    }

    // Ambil absensi user sesuai id_user
    $absensi = DB::table('absensi')
        ->where('id_user', $id_user)
        ->where('deleted', 0)
        ->orderBy('tanggal', 'DESC')
        ->get();

    // Cek akses cetak semua
    $aksescetaksemua = DB::table('user_access')
        ->where('action_id', 14)
        ->where('user_id', $id_user)
        ->count();

    return response()->json([
        'user' => $user,
        'title' => 'Intelligent Group | Data Absensi',
        'absensi' => $absensi,
        'aksescetaksemua' => $aksescetaksemua
    ]);
}

     public function getAbsensiLastTwoWeeks(Request $request)
    {
        $iduser = $request->input('iduser');
        $tanggaldari = $request->input('tanggaldari');
        $tanggalsampai = $request->input('tanggalsampai');

        if (!$iduser) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter iduser diperlukan',
            ]);
        }

        $query = DB::table('absensi')
            ->select('tanggal', 'checkin_time', 'checkout_time', 'approved')
            ->where('id_user', $iduser)
            ->where('deleted', 0);

        if (!empty($tanggaldari) && !empty($tanggalsampai)) {
            $query->whereBetween('tanggal', [
                date('Y-m-d', strtotime($tanggaldari)),
                date('Y-m-d', strtotime($tanggalsampai))
            ]);
        } else {
            $query->where('tanggal', '>=', date('Y-m-d', strtotime('-14 days')));
        }

        $data = $query->orderBy('tanggal', 'DESC')->get();

        if ($data->isNotEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'Data absensi ditemukan',
                'data' => $data
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Data absensi tidak ditemukan',
                'data' => []
            ]);
        }
    }
     public function getLastAbsensi(Request $request)
    {
        $iduser = $request->input('iduser');

        if (!$iduser) {
            return response()->json([
                'success' => false,
                'message' => 'ID user tidak boleh kosong',
            ]);
        }

        $last = DB::table('absensi')
            ->where('id_user', $iduser)
            ->where('deleted', 0)
            ->orderBy('id', 'DESC')
            ->first();

        if ($last) {
            $jenisabsensi = $last->checkout_time === null ? 'Checkin' : 'Checkout';

            return response()->json([
                'success' => true,
                'message' => 'Data absensi ditemukan',
                'data' => [
                    'jenisabsensi' => $jenisabsensi,
                    'tanggal' => $last->tanggal,
                    'checkin_time' => $last->checkin_time,
                    'checkout_time' => $last->checkout_time,
                ]
            ]);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'Belum ada data absensi',
                'data' => null,
            ]);
        }
    }

    // POST: /api/absensi/duplicateAndUpdateAbsensi
    public function duplicateAndUpdateAbsensi(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');
        $now = date('Y-m-d H:i:s');

        $checkindate = $request->input('checkindate');
        $checkintime = $request->input('checkintime');
        $checkoutdate = $request->input('checkoutdate');
        $checkouttime = $request->input('checkouttime');
        $id = $request->input('idabsensi');

        DB::beginTransaction();
        try {
            // Ambil data original
            $original = DB::table('absensi')
                ->where('id', $id)
                ->where('deleted', 0)
                ->first();

            if ($original) {
                $newRow = (array) $original;
                unset($newRow['id']); // hapus primary key

                $newRow['checkin_time'] = date('Y-m-d H:i:s', strtotime("$checkindate $checkintime"));
                $newRow['checkout_time'] = date('Y-m-d H:i:s', strtotime("$checkoutdate $checkouttime"));
                $newRow['update_by'] = Auth::id() ?? null; // gunakan user login Laravel
                $newRow['update_date'] = $now;

                DB::table('absensi')->insert($newRow);

                // Tandai data lama sebagai deleted
                DB::table('absensi')
                    ->where('id', $id)
                    ->update(['deleted' => 1]);
            }

            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Absensi updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Update failed',
                'error' => $e->getMessage()
            ]);
        }
    }

    // POST: /api/absensi/getAbsensi
    public function getAbsensi(Request $request)
    {
        $start = $request->input('start', 0);
        $length = $request->input('length', 10);
        $draw = $request->input('draw', 1);

        // Query absensi dengan limit & offset
        $query = DB::table('absensi')
            ->select('user.nama', 'absensi.tanggal', 'absensi.checkin_time', 'absensi.checkin_foto',
                     'absensi.checkout_time', 'absensi.checkout_foto')
            ->join('user', 'user.id_user', '=', 'absensi.id_user')
            ->where('absensi.deleted', 0)
            ->orderBy('absensi.tanggal', 'DESC');

        if ($length != -1) {
            $query->offset($start)->limit($length);
        }

        $results = $query->get();

        $data = [];
        foreach ($results as $rs) {
            $item = [
                'namaabsensi' => $rs->nama,
                'tanggal' => $this->formatIndo($rs->tanggal),
                'absenmasuk' => $rs->checkin_time,
                'fotoabsenmasuk' => url('assets/file/absensi/' . $rs->checkin_foto),
                'absenkeluar' => $rs->checkout_time,
                'fotoabsenkeluar' => url('assets/file/absensi/' . $rs->checkout_foto),
            ];

            if ($rs->checkin_time && $rs->checkout_time) {
                $hasil = $this->calculateWorkAndOvertime($rs->checkin_time, $rs->checkout_time);
                $item['jam_kerja'] = $hasil['jam_kerja'];
                $item['jam_lembur'] = $hasil['jam_lembur'];
            } else {
                $item['jam_kerja'] = '-';
                $item['jam_lembur'] = '-';
            }

            $data[] = $item;
        }

        $output = [
            'draw' => intval($draw),
            'recordsTotal' => DB::table('absensi')->count(),
            'recordsFiltered' => DB::table('absensi')->where('deleted', 0)->count(),
            'data' => $data
        ];

        return response()->json($output);
    }

    private function calculateWorkAndOvertime($checkinStr, $checkoutStr)
    {
        $checkin = new DateTime($checkinStr);
        $checkout = new DateTime($checkoutStr);

        if ($checkout < $checkin) {
            $checkout->modify('+1 day');
        }

        $startKerja = new DateTime($checkin->format('Y-m-d') . ' 09:00:00');
        $endKerja = new DateTime($checkin->format('Y-m-d') . ' 18:00:00');
        $startLembur = new DateTime($checkin->format('Y-m-d') . ' 19:00:00');

        // Hitung jam kerja
        $jamKerjaMenit = 0;
        if ($checkout > $startKerja) {
            $mulaiKerja = max($checkin, $startKerja);
            $akhirKerja = min($checkout, $endKerja);
            if ($akhirKerja > $mulaiKerja) {
                $interval = $akhirKerja->diff($mulaiKerja);
                $jamKerjaMenit = ($interval->h * 60) + $interval->i;

                // Potong istirahat siang
                $istirahatStart = new DateTime($checkin->format('Y-m-d') . ' 12:00:00');
                $istirahatEnd = new DateTime($checkin->format('Y-m-d') . ' 13:00:00');
                if ($mulaiKerja < $istirahatEnd && $akhirKerja > $istirahatStart) {
                    $jamKerjaMenit -= 60;
                }
            }
        }

        // Hitung lembur
        $jamLemburMenit = 0;
        if ($checkout > $startLembur) {
            $mulaiLembur = max($checkin, $startLembur);
            $akhirLembur = $checkout;
            if ($akhirLembur > $mulaiLembur) {
                $intervalLembur = $akhirLembur->diff($mulaiLembur);
                $jamLemburMenit = ($intervalLembur->h * 60) + $intervalLembur->i;
            }

            // Potong istirahat malam (01:00 - 02:00)
            $istirahatMulai = new DateTime($checkin->format('Y-m-d') . ' 01:00:00');
            $istirahatSelesai = (clone $istirahatMulai)->modify('+1 hour');

            if ($akhirLembur > $istirahatSelesai) {
                $istirahatMulai->modify('+1 day');
                $istirahatSelesai->modify('+1 day');
            }

            if ($mulaiLembur < $istirahatSelesai && $akhirLembur > $istirahatMulai) {
                $jamLemburMenit -= 60;
            }
        }

        return [
            'jam_kerja' => $this->formatWaktu($jamKerjaMenit),
            'jam_lembur' => $this->formatWaktu($jamLemburMenit),
        ];
    }

    private function formatWaktu($totalMenit)
    {
        if ($totalMenit <= 0) {
            return '0 jam';
        }
        $jam = floor($totalMenit / 60);
        $menit = round(($totalMenit % 60) / 30) * 30;
        if ($menit == 60) {
            $jam += 1;
            $menit = 0;
        }
        $result = [];
        if ($jam > 0) {
            $result[] = $jam . ' jam';
        }
        if ($menit > 0) {
            $result[] = $menit . ' menit';
        }
        return implode(' ', $result);
    }

    private function formatIndo($tanggal)
    {
        return date('d-m-Y', strtotime($tanggal)); // bisa diganti dengan helper format Indo
    }


    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }

    // POST: /api/absensi/kirim
    public function sendAbsensi(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');
        $id_user   = $request->input('id_user');
        $latitude  = (float) $request->input('latitude');
        $longitude = (float) $request->input('longitude');
        $status    = strtolower(trim($request->input('status')));
        $image     = $request->input('image');
        $now       = date('Y-m-d H:i:s');

        if (empty($id_user) || empty($image)) {
            return response()->json(['status' => false, 'message' => 'ID user atau gambar tidak ditemukan']);
        }

        // Decode base64 image
        if (strpos($image, 'data:image') !== false) {
            $image = preg_replace('/^data:image\/\w+;base64,/', '', $image);
        }
        $image = str_replace(' ', '+', $image);
        $data = base64_decode($image);
        if ($data === false) {
            return response()->json(['status' => false, 'message' => 'Base64 tidak valid']);
        }

        $folder = public_path('assets/file/absensi/');
        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        $fileName = uniqid() . '.png';
        $filePath = $folder . $fileName;
        if (file_put_contents($filePath, $data) === false) {
            return response()->json(['status' => false, 'message' => 'Gagal menyimpan file']);
        }

        // Cari SPK terdekat
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();
        $nearest_spk_id = null;
        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($latitude, $longitude, (float)$spk->latitude, (float)$spk->longitude);
            if ($distance <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        // Ambil absensi terakhir
        $lastAbsen = DB::table('absensi')
            ->where('id_user', $id_user)
            ->where('deleted', 0)
            ->orderBy('id', 'DESC')
            ->first();

        // Logic checkin/checkout otomatis
        if (empty($status)) {
            if (!$lastAbsen || ($lastAbsen->checkin_time && $lastAbsen->checkout_time)) {
                DB::table('absensi')->insert([
                    'id_user' => $id_user,
                    'id_headerspk' => $nearest_spk_id,
                    'tanggal' => date('Y-m-d'),
                    'checkin_time' => $now,
                    'checkin_latitude' => $latitude,
                    'checkin_longitude' => $longitude,
                    'checkin_foto' => $fileName,
                    'create_by' => $id_user,
                    'create_date' => $now,
                    'deleted' => 0,
                ]);
                return response()->json(['status' => true, 'message' => 'Checkin berhasil.']);
            }

            if ($lastAbsen->checkin_time && !$lastAbsen->checkout_time) {
                DB::table('absensi')->where('id', $lastAbsen->id)->update([
                    'checkout_time' => $now,
                    'checkout_latitude' => $latitude,
                    'checkout_longitude' => $longitude,
                    'checkout_foto' => $fileName,
                    'update_by' => $id_user,
                    'update_date' => $now,
                ]);
                return response()->json(['status' => true, 'message' => 'Checkout berhasil.']);
            }
        }

        // Manual checkin/checkout
        if ($status === 'checkin') {
            DB::table('absensi')->insert([
                'id_user' => $id_user,
                'id_headerspk' => $nearest_spk_id,
                'tanggal' => date('Y-m-d'),
                'checkin_time' => $now,
                'checkin_latitude' => $latitude,
                'checkin_longitude' => $longitude,
                'checkin_foto' => $fileName,
                'create_by' => $id_user,
                'create_date' => $now,
                'deleted' => 0,
            ]);
            return response()->json(['status' => true, 'message' => 'Checkin manual berhasil.']);
        }

        if ($status === 'checkout') {
            DB::table('absensi')->insert([
                'id_user' => $id_user,
                'id_headerspk' => $nearest_spk_id,
                'tanggal' => date('Y-m-d'),
                'checkout_time' => $now,
                'checkout_latitude' => $latitude,
                'checkout_longitude' => $longitude,
                'checkout_foto' => $fileName,
                'create_by' => $id_user,
                'create_date' => $now,
                'deleted' => 0,
            ]);
            return response()->json(['status' => true, 'message' => 'Checkout manual berhasil.']);
        }

        return response()->json(['status' => false, 'message' => 'Status tidak dikenali.']);
    }

    // POST: /api/absensi/approved
    public function approveAbsensi(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $status     = strtolower($request->input('status'));
        $id_user    = $request->input('id_user');
        $tanggal    = $request->input('tanggal');
        $waktu      = $request->input('waktu');
        $keterangan = $request->input('keterangan');
        $latitude   = $request->input('latitude');
        $longitude  = $request->input('longitude');

        if (!$id_user || !$status || !$tanggal || !$waktu) {
            return response()->json(['status' => false, 'message' => 'Parameter tidak lengkap.']);
        }

        $datetime = date('Y-m-d H:i:s', strtotime("$tanggal $waktu"));

        // Cari SPK terdekat
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();
        $nearest_spk_id = null;
        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($latitude, $longitude, (float)$spk->latitude, (float)$spk->longitude);
            if ($distance <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        $data = [
            'id_user' => $id_user,
            'id_headerspk' => $nearest_spk_id,
            'tanggal' => $tanggal,
            'create_by' => $id_user,
            'create_date' => date('Y-m-d H:i:s'),
            'approved' => 0,
        ];

        if ($status == 'checkin') {
            $data['checkin_time'] = $datetime;
            $data['checkin_deskripsi'] = $keterangan;
            $data['checkin_latitude'] = $latitude;
            $data['checkin_longitude'] = $longitude;
        } elseif ($status == 'checkout') {
            $data['checkout_time'] = $datetime;
            $data['checkout_deskripsi'] = $keterangan;
            $data['checkout_latitude'] = $latitude;
            $data['checkout_longitude'] = $longitude;
        } else {
            return response()->json(['status' => false, 'message' => 'Status tidak valid.']);
        }

        DB::table('absensi')->insert($data);
        return response()->json(['status' => true, 'message' => ucfirst($status) . ' berhasil disimpan. Menunggu persetujuan.']);
    }


    // POST: /api/absensi/checkin
    public function saveAbsensiCheckin(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $id_user    = $request->input('id_user');
        $tanggal    = $request->input('tanggal');
        $waktu      = $request->input('waktu');
        $keterangan = $request->input('keterangan');
        $latitude   = $request->input('latitude');
        $longitude  = $request->input('longitude');

        $datetime = date('Y-m-d H:i:s', strtotime("$tanggal $waktu"));

        // Cari SPK terdekat
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();
        $nearest_spk_id = null;
        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($latitude, $longitude, (float)$spk->latitude, (float)$spk->longitude);
            if ($distance <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        // Cek apakah ada absen yang belum checkout
        $absen = DB::table('absensi')
            ->where('id_user', $id_user)
            ->whereNull('checkout_time')
            ->orderBy('checkin_time', 'DESC')
            ->first();

        if (!$absen) {
            DB::table('absensi')->insert([
                'id_headerspk'       => $nearest_spk_id,
                'id_user'            => $id_user,
                'tanggal'            => $tanggal,
                'checkin_time'       => $datetime,
                'checkin_deskripsi'  => $keterangan,
                'checkin_latitude'   => $latitude,
                'checkin_longitude'  => $longitude,
                'create_by'          => $id_user,
                'create_date'        => date('Y-m-d H:i:s'),
                'deleted'            => 0,
            ]);
            return response()->json(['status' => true, 'message' => 'Check-in berhasil disimpan.']);
        } else {
            return response()->json(['status' => false, 'message' => 'Masih ada absen yang belum checkout.']);
        }
    }

    // POST: /api/absensi/checkout
    public function saveAbsensiCheckout(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $id_user    = $request->input('id_user');
        $tanggal    = $request->input('tanggal');
        $waktu      = $request->input('waktu');
        $keterangan = $request->input('keterangan');
        $latitude   = $request->input('latitude');
        $longitude  = $request->input('longitude');

        $datetime = date('Y-m-d H:i:s', strtotime("$tanggal $waktu"));

        // Cari SPK terdekat
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();
        $nearest_spk_id = null;
        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($latitude, $longitude, (float)$spk->latitude, (float)$spk->longitude);
            if ($distance <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        // Cari absen terakhir yang belum checkout
        $absen = DB::table('absensi')
            ->where('id_user', $id_user)
            ->whereNull('checkout_time')
            ->orderBy('checkin_time', 'DESC')
            ->first();

        if ($absen) {
            DB::table('absensi')->where('id', $absen->id)->update([
                'id_headerspk'        => $nearest_spk_id,
                'checkout_time'       => $datetime,
                'checkout_deskripsi'  => $keterangan,
                'checkout_latitude'   => $latitude,
                'checkout_longitude'  => $longitude,
                'update_by'           => $id_user,
                'update_date'         => date('Y-m-d H:i:s'),
                'deleted'             => 0,
            ]);
            return response()->json(['status' => true, 'message' => 'Checkout berhasil disimpan.']);
        } else {
            return response()->json(['status' => false, 'message' => 'Tidak ada data check-in yang ditemukan.']);
        }
    }

    // POST: /api/absensi/approved-checkin
    public function approveAbsensiCheckin(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $id         = $request->input('id'); // opsional
        $id_user    = $request->input('id_user');
        $tanggal    = $request->input('tanggal');
        $waktu      = $request->input('waktu');
        $keterangan = $request->input('keterangan');
        $latitude   = $request->input('latitude');
        $longitude  = $request->input('longitude');

        $datetime = date('Y-m-d H:i:s', strtotime("$tanggal $waktu"));

        // Cari SPK terdekat
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();
        $nearest_spk_id = null;
        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($latitude, $longitude, (float)$spk->latitude, (float)$spk->longitude);
            if ($distance <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        if ($id) {
            // Update berdasarkan ID
            DB::table('absensi')->where('id', $id)->update([
                'id_headerspk'       => $nearest_spk_id,
                'checkin_time'       => $datetime,
                'checkin_deskripsi'  => $keterangan,
                'checkin_latitude'   => $latitude,
                'checkin_longitude'  => $longitude,
                'update_by'          => $id_user,
                'update_date'        => date('Y-m-d H:i:s'),
                'approved'           => 0,
                'deleted'            => 0,
            ]);
            return response()->json(['status' => true, 'message' => 'Check-in berhasil diperbarui. Menunggu persetujuan.']);
        } else {
            // Insert baru
            DB::table('absensi')->insert([
                'id_headerspk'       => $nearest_spk_id,
                'id_user'            => $id_user,
                'tanggal'            => $tanggal,
                'checkin_time'       => $datetime,
                'checkin_deskripsi'  => $keterangan,
                'checkin_latitude'   => $latitude,
                'checkin_longitude'  => $longitude,
                'create_by'          => $id_user,
                'create_date'        => date('Y-m-d H:i:s'),
                'approved'           => 0,
                'deleted'            => 0,
            ]);
            return response()->json(['status' => true, 'message' => 'Check-in berhasil disimpan. Menunggu persetujuan.']);
        }
    }

    // POST: /api/absensi/approved-checkout
    public function approveAbsensiCheckout(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $id         = $request->input('id'); // opsional
        $id_user    = $request->input('id_user');
        $tanggal    = $request->input('tanggal');
        $waktu      = $request->input('waktu');
        $keterangan = $request->input('keterangan');
        $latitude   = $request->input('latitude');
        $longitude  = $request->input('longitude');

        $datetime = date('Y-m-d H:i:s', strtotime("$tanggal $waktu"));

        // Cari SPK terdekat
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();
        $nearest_spk_id = null;
        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($latitude, $longitude, (float)$spk->latitude, (float)$spk->longitude);
            if ($distance <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        // Cari absen berdasarkan ID atau fallback ke absen terakhir
        if ($id) {
            $absen = DB::table('absensi')->where('id', $id)->first();
        } else {
            $absen = DB::table('absensi')
                ->where('id_user', $id_user)
                ->whereNull('checkout_time')
                ->orderBy('checkin_time', 'DESC')
                ->first();
        }

        if ($absen) {
            DB::table('absensi')->where('id', $absen->id)->update([
                'id_headerspk'        => $nearest_spk_id,
                'checkout_time'       => $datetime,
                'checkout_deskripsi'  => $keterangan,
                'checkout_latitude'   => $latitude,
                'checkout_longitude'  => $longitude,
                'update_by'           => $id_user,
                'update_date'         => date('Y-m-d H:i:s'),
                'approved'            => 0,
                'deleted'             => 0,
            ]);
            return response()->json(['status' => true, 'message' => 'Checkout berhasil disimpan. Menunggu persetujuan.']);
        } else {
            return response()->json(['status' => false, 'message' => 'Data absensi untuk checkout tidak ditemukan.']);
        }
    }
    // POST: /api/absensi/update
    public function updateAbsensi(Request $request)
    {
        $id       = $request->input('id');
        $checkin  = $request->input('checkin_time');
        $checkout = $request->input('checkout_time');

        if (empty($id)) {
            return response()->json(['success' => false, 'message' => 'Parameter id tidak boleh kosong.']);
        }

        if (empty($checkin) && empty($checkout)) {
            return response()->json(['success' => false, 'message' => 'Harus ada minimal salah satu dari checkin_time atau checkout_time.']);
        }

        $updateData = [];
        if (!empty($checkin)) {
            $updateData['checkin_time'] = $checkin;
        }
        if (!empty($checkout)) {
            $updateData['checkout_time'] = $checkout;
        }

        DB::table('absensi')->where('id', $id)->update($updateData);

        return response()->json(['success' => true, 'message' => 'Data absensi berhasil diperbarui.']);
    }

    // POST: /api/absensi/approve-checkout-admin
    public function approveCheckoutByAdmin(Request $request)
    {
        $id_absensi = $request->input('id');
        $id_user    = $request->input('id_user');

        // Validasi akses admin
        if (!in_array($id_user, [1, 2])) {
            return response()->json(['success' => false, 'message' => 'Hanya admin tertentu yang dapat menyetujui absensi.']);
        }

        if (empty($id_absensi)) {
            return response()->json(['success' => false, 'message' => 'Parameter id tidak boleh kosong.']);
        }

        $updated = DB::table('absensi')->where('id', $id_absensi)->update([
            'approved'    => 1,
            'update_by'   => $id_user,
            'update_date' => date('Y-m-d H:i:s'),
            'deleted'     => 0
        ]);

        if ($updated) {
            return response()->json(['success' => true, 'message' => 'Checkout berhasil disetujui oleh admin.']);
        } else {
            return response()->json(['success' => false, 'message' => 'Gagal menyetujui absensi.']);
        }
    }

    // POST: /api/absensi/filter
    public function filterAbsensi(Request $request)
    {
        $iduser       = $request->input('iduser');
        $tanggaldari  = $request->input('tanggaldari');
        $tanggalsampai= $request->input('tanggalsampai');

        if (empty($iduser)) {
            return response()->json(['success' => false, 'message' => 'Parameter iduser tidak boleh kosong.']);
        }

        $seven_days_ago = date('Y-m-d', strtotime('-14 days'));

        $query = DB::table('absensi')
            ->select('id', 'tanggal', 'checkin_time', 'checkout_time', 'approved')
            ->where('id_user', $iduser)
            ->where('deleted', 0);

        if (!empty($tanggaldari) && !empty($tanggalsampai)) {
            $query->whereBetween('tanggal', [
                date('Y-m-d', strtotime($tanggaldari)),
                date('Y-m-d', strtotime($tanggalsampai))
            ]);
        } else {
            $query->where('tanggal', '>=', $seven_days_ago);
        }

        $result = $query->orderBy('tanggal', 'DESC')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data absensi berhasil diambil.',
            'data'    => $result
        ]);
    }
    // POST: /api/absensi/belum-approved
    public function getUnapproveAbsensi(Request $request)
    {
        $iduser       = $request->input('iduser');
        $tanggaldari  = $request->input('tanggaldari');
        $tanggalsampai= $request->input('tanggalsampai');

        if (empty($iduser)) {
            return response()->json(['success' => false, 'message' => 'Parameter iduser tidak boleh kosong.']);
        }

        $seven_days_ago = date('Y-m-d', strtotime('-14 days'));

        $query = DB::table('absensi')
            ->select('id', 'tanggal', 'checkin_time', 'checkout_time', 'approved')
            ->where('id_user', $iduser)
            ->where('deleted', 0)
            ->where('approved', 0);

        if (!empty($tanggaldari) && !empty($tanggalsampai)) {
            $query->whereBetween('tanggal', [
                date('Y-m-d', strtotime($tanggaldari)),
                date('Y-m-d', strtotime($tanggalsampai))
            ]);
        } else {
            $query->where('tanggal', '>=', $seven_days_ago);
        }

        $result = $query->orderBy('tanggal', 'DESC')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data absensi belum di-approve berhasil diambil.',
            'data'    => $result
        ]);
    }

    // POST: /api/absensi/belum-lengkap
    public function getIncompleteAbsensi(Request $request)
    {
        $iduser       = $request->input('iduser');
        $tanggaldari  = $request->input('tanggaldari');
        $tanggalsampai= $request->input('tanggalsampai');

        if (empty($iduser)) {
            return response()->json(['success' => false, 'message' => 'Parameter iduser tidak boleh kosong.']);
        }

        $seven_days_ago = date('Y-m-d', strtotime('-14 days'));

        $query = DB::table('absensi')
            ->select('id', 'tanggal', 'checkin_time', 'checkout_time', 'approved')
            ->where('id_user', $iduser)
            ->where('deleted', 0)
            ->where(function ($q) {
                $q->whereNull('checkin_time')
                  ->orWhere('checkin_time', '')
                  ->orWhereNull('checkout_time')
                  ->orWhere('checkout_time', '')
                  ->orWhere('approved', 0);
            });

        if (!empty($tanggaldari) && !empty($tanggalsampai)) {
            $query->whereBetween('tanggal', [
                date('Y-m-d', strtotime($tanggaldari)),
                date('Y-m-d', strtotime($tanggalsampai))
            ]);
        } else {
            $query->where('tanggal', '>=', $seven_days_ago);
        }

        $result = $query->orderBy('tanggal', 'DESC')->get();

        return response()->json([
            'success' => true,
            'message' => 'Data absensi belum lengkap atau belum disetujui berhasil diambil.',
            'data'    => $result
        ]);
    }

    // POST: /api/absensi/approve-admin
    public function approveAbsensiByAdmin(Request $request)
    {
        $id_user = $request->input('id_user');
        $ids     = $request->input('id', []);

        if (!in_array($id_user, [1, 2])) {
            return response()->json([
                'success' => true,
                'message' => count($ids) . ' data absensi berhasil di-approve oleh admin ID ' . $id_user
            ]);
        }

        if (empty($ids) || !is_array($ids)) {
            return response()->json(['success' => false, 'message' => 'Parameter id harus berupa array dan tidak boleh kosong.']);
        }

        DB::table('absensi')->whereIn('id', $ids)->update(['approved' => 1]);

        return response()->json([
            'success' => true,
            'message' => count($ids) . ' data absensi berhasil di-approve oleh admin ID ' . $id_user
        ]);
    }

    // POST: /api/absensi/cetak
    public function printAbsensi(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $from   = $request->input('fromdate');
        $to     = $request->input('todate');
        $tampil = $request->input('tampilcetakabsensi'); // "Workshop" / lainnya
        $jenis  = $request->input('jeniscetakabsensi');  // "Nama absensi" atau "Tanggal"

        if (!$from || !$to || !$jenis) {
            return response()->json(['status' => false, 'message' => 'Parameter wajib tidak lengkap']);
        }

        $result = [];

        if ($jenis == 'Nama absensi') {
            $query = DB::table('absensi')
                ->join('user', 'user.id_user', '=', 'absensi.id_user')
                ->join('absensi', 'absensi.id_absensi', '=', 'user.id_absensi')
                ->leftJoin('masterid', 'masterid.masterid', '=', 'absensi.lokasi')
                ->select('user.id_user', 'user.nama')
                ->whereBetween('absensi.tanggal', ["$from 00:00:00", "$to 23:59:59"])
                ->where('absensi.deleted', 0)
                ->groupBy('absensi.id_user')
                ->orderBy('user.nama', 'ASC');

            if ($tampil == 'Workshop') {
                $query->where('masterid.mastername', 'Workshop');
            }

            $result['listabsensi'] = $query->get();
        } elseif ($jenis == 'Tanggal') {
            $result['listtanggal'] = DB::table('absensi')
                ->select('tanggal')
                ->whereBetween('absensi.tanggal', ["$from 00:00:00", "$to 23:59:59"])
                ->where('absensi.deleted', 0)
                ->groupBy('tanggal')
                ->orderBy('tanggal', 'ASC')
                ->get();
        }

        // Data absensi utama
        $query = DB::table('absensi')
            ->join('user', 'user.id_user', '=', 'absensi.id_user')
            ->join('absensi', 'absensi.id_absensi', '=', 'user.id_absensi')
            ->leftJoin('masterid', 'masterid.masterid', '=', 'absensi.lokasi')
            ->select('user.nama', 'absensi.*',
                DB::raw('MIN(checkin_time) as checkin_time'),
                DB::raw('MAX(checkout_time) as checkout_time'))
            ->whereBetween('absensi.tanggal', ["$from 00:00:00", "$to 23:59:59"])
            ->where('absensi.deleted', 0)
            ->groupBy('tanggal')
            ->groupBy('absensi.id_user')
            ->orderBy('checkin_time', 'ASC');

        if ($tampil == 'Workshop') {
            $query->where('masterid.mastername', 'Workshop');
        }

        $result['absensi'] = $query->get();

        return response()->json(['status' => true, 'data' => $result]);
    }


}
