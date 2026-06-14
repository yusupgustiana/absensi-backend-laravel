<?php
namespace App\Http\Controllers\Api;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Absensi;

class Absensiv2Controller extends Controller
{

public function kirimAbsen(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        // 1. Validasi input
        $validated = $request->validate([
            'id_user'   => 'required|integer',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'status'    => 'nullable|string|in:checkin,checkout,otomatis',
            'image'     => 'required|string',
        ]);

        $id_user   = $validated['id_user'];
        $latitude  = $validated['latitude'];
        $longitude = $validated['longitude'];
        $status    = strtolower(trim($validated['status'] ?? ''));
        $image64   = $validated['image'];
        $now       = now()->format('Y-m-d H:i:s');

        // 2. Simpan foto
        $fileName = $this->simpanFoto($image64);
        if (!$fileName) {
            return response()->json(['status' => false, 'message' => 'Gagal menyimpan file foto'], 500);
        }

        // 3. Cari SPK terdekat
        $nearest_spk_id = $this->cariSpkTerdekat($latitude, $longitude);

        // 4. Ambil absensi terakhir
        $lastAbsen = DB::table('absensi')
            ->where('id_user', $id_user)
            ->where('deleted', 0)
            ->orderByDesc('id')
            ->first();

        // 5. Logika otomatis
        if ($status === '' || $status === 'otomatis') {
            if (!$lastAbsen || ($lastAbsen->checkin_time && $lastAbsen->checkout_time)) {
                return $this->insertCheckin($id_user, $nearest_spk_id, $latitude, $longitude, $fileName, $now, 'Check-in otomatis berhasil.');
            }

            if ($lastAbsen->checkin_time && !$lastAbsen->checkout_time) {
                return $this->updateCheckout($lastAbsen->id, $id_user, $latitude, $longitude, $fileName, $now, 'Check-out otomatis berhasil.');
            }
        }

        // 6. Logika manual
        if ($status === 'checkin') {
            return $this->insertCheckin($id_user, $nearest_spk_id, $latitude, $longitude, $fileName, $now, 'Check-in manual berhasil.');
        }

        if ($status === 'checkout') {
            return $this->insertCheckoutManual($id_user, $nearest_spk_id, $latitude, $longitude, $fileName, $now, 'Check-out manual berhasil.');
        }

        return response()->json(['status' => false, 'message' => 'Status tidak dikenali'], 422);
    }

    
    // ─── Helper ────────────────────────────────────────────────
private function simpanFoto(string $image64): ?string
    {
        if (strpos($image64, 'data:image') === 0) {
            $image64 = preg_replace('#^data:image/\w+;base64,#', '', $image64);
        }
        $data = base64_decode(str_replace(' ', '+', $image64));
        if ($data === false) return null;

        $folder = public_path('assets/file/absensi/');
        if (!is_dir($folder)) mkdir($folder, 0777, true);

        $fileName = uniqid('absen_', true) . '.png';
        return file_put_contents($folder . $fileName, $data) ? $fileName : null;
    }

    private function cariSpkTerdekat(float $lat, float $lng): ?int
    {
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();
        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($lat, $lng, (float)$spk->latitude, (float)$spk->longitude);
            if ($distance <= 1) return $spk->id_headerspk;
        }
        return null;
    }

    private function insertCheckin($id_user, $spk_id, $lat, $lng, $foto, $now, $msg)
    {
        DB::table('absensi')->insert([
            'id_user'           => $id_user,
            'id_headerspk'      => $spk_id,
            'tanggal'           => date('Y-m-d'),
            'checkin_time'      => $now,
            'checkin_latitude'  => $lat,
            'checkin_longitude' => $lng,
            'checkin_foto'      => $foto,
            'create_by'         => $id_user,
            'create_date'       => $now,
            'deleted'           => 0,
        ]);
        return response()->json(['status' => true, 'message' => $msg]);
    }

    private function updateCheckout($absen_id, $id_user, $lat, $lng, $foto, $now, $msg)
    {
        DB::table('absensi')->where('id', $absen_id)->update([
            'checkout_time'      => $now,
            'checkout_latitude'  => $lat,
            'checkout_longitude' => $lng,
            'checkout_foto'      => $foto,
            'update_by'          => $id_user,
            'update_date'        => $now,
        ]);
        return response()->json(['status' => true, 'message' => $msg]);
    }

    private function insertCheckoutManual($id_user, $spk_id, $lat, $lng, $foto, $now, $msg)
    {
        DB::table('absensi')->insert([
            'id_user'            => $id_user,
            'id_headerspk'       => $spk_id,
            'tanggal'            => date('Y-m-d'),
            'checkout_time'      => $now,
            'checkout_latitude'  => $lat,
            'checkout_longitude' => $lng,
            'checkout_foto'      => $foto,
            'create_by'          => $id_user,
            'create_date'        => $now,
            'deleted'            => 0,
        ]);
        return response()->json(['status' => true, 'message' => $msg]);
    }

    private function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $earthRadius * $c;
    }

 public function absensiTerakhir(Request $request)
    {
        // Validasi input
        $validated = $request->validate([
            'iduser' => 'required|integer',
        ]);

        $iduser = $validated['iduser'];

        // Ambil absensi terakhir user
        $last = DB::table('absensi')
            ->where('id_user', $iduser)
            ->where('deleted', 0)
            ->orderByDesc('id')
            ->first();

        if ($last) {
            $jenisabsensi = is_null($last->checkout_time) ? 'Checkin' : 'Checkout';

            return response()->json([
                'status' => true,
                'message' => 'Data absensi ditemukan',
                'data' => [
                    'jenisabsensi'  => $jenisabsensi,
                    'tanggal'       => $last->tanggal,
                    'checkin_time'  => $last->checkin_time,
                    'checkout_time' => $last->checkout_time,
                ]
            ], 200);
        }

        return response()->json([
            'status' => true,
            'message' => 'Belum ada data absensi',
            'data' => null
        ], 200);
    }

public function historyAbsensi(Request $request)
{
    $idUser = $request->input('id_user');

    if (!$idUser) {
        return response()->json([
            'status'  => false,
            'message' => 'Parameter id_user tidak boleh kosong.'
        ], 400);
    }

    $query = DB::table('absensi as a')
        ->leftJoin('karyawan as u', 'u.id_user', '=', 'a.id_user')
        ->select([
            'a.id_user',
            'u.nama',
            'a.tanggal',
            DB::raw('MIN(a.checkin_time) as checkin_time'),
            DB::raw('MAX(a.checkout_time) as checkout_time'),
            DB::raw('MAX(a.checkin_approved) as checkin_approved'),
            DB::raw('MAX(a.checkout_approved) as checkout_approved'),
            DB::raw("GROUP_CONCAT(DISTINCT a.checkin_deskripsi ORDER BY a.checkin_time SEPARATOR ' | ') as checkin_deskripsi"),
            DB::raw("GROUP_CONCAT(DISTINCT a.checkout_deskripsi ORDER BY a.checkout_time SEPARATOR ' | ') as checkout_deskripsi"),
        ])
        ->where('a.deleted', 0);

    // Admin (id_user = 1) dapat melihat semua data
    if ((int) $idUser !== 1) {
        $query->where('a.id_user', $idUser);
    }

    // Filter tanggal
    $tanggalDari   = $request->input('tanggaldari');
    $tanggalSampai = $request->input('tanggalsampai');

    $query->when(
        $tanggalDari && $tanggalSampai,
        function ($q) use ($tanggalDari, $tanggalSampai) {
            $q->whereBetween('a.tanggal', [
                Carbon::parse($tanggalDari)->format('Y-m-d'),
                Carbon::parse($tanggalSampai)->format('Y-m-d'),
            ]);
        },
        function ($q) {
            $q->where(
                'a.tanggal',
                '>=',
                Carbon::now()->subDays(14)->format('Y-m-d')
            );
        }
    );

    $result = $query
        ->groupBy([
            'a.id_user',
            'u.nama',
            'a.tanggal',
        ])
        ->orderByDesc('a.tanggal')
        ->get();

    return response()->json([
        'status'  => true,
        'message' => ((int) $idUser === 1)
            ? 'Data absensi semua user berhasil diambil.'
            : 'Data absensi berhasil diambil.',
        'data' => $result,
    ]);
}



    public function lupaCheckout(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $id         = $request->input('id');
        $id_user    = $request->input('id_user');
        $tanggal    = $request->input('tanggal');
        $waktu      = $request->input('waktu');
        $keterangan = $request->input('keterangan');
        $latitude   = $request->input('latitude');
        $longitude  = $request->input('longitude');

        // ─── 1. Validasi input dasar ───
        if (empty($id_user) || empty($tanggal) || empty($waktu) || $latitude === null || $longitude === null) {
            return response()->json([
                'status'  => false,
                'message' => 'Semua parameter wajib diisi.'
            ], 400);
        }

        // Pastikan format tanggal + waktu valid
        $datetime = date('Y-m-d H:i:s', strtotime("$tanggal $waktu"));
        if (!$datetime) {
            return response()->json([
                'status'  => false,
                'message' => 'Format tanggal/waktu tidak valid.'
            ], 422);
        }

        // ─── 2. Temukan SPK terdekat (≤ 1 km) ───
        $nearest_spk_id = null;
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();

        foreach ($dataspk as $spk) {
            $dist = $this->haversineDistance($latitude, $longitude, (float) $spk->latitude, (float) $spk->longitude);
            if ($dist <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        // ─── 3. Cari data absensi yang harus di-checkout ───
        if ($id) {
            $absen = DB::table('absensi')->where('id', $id)->first();
        } else {
            $absen = DB::table('absensi')
                ->where('id_user', $id_user)
                ->whereNull('checkout_time')
                ->orderBy('checkin_time', 'DESC')
                ->first();
        }

        // ─── 4. Lakukan update atau balas error ───
        if ($absen) {
            $updated = DB::table('absensi')
                ->where('id', $absen->id)
                ->update([
                    'id_headerspk'       => $nearest_spk_id,
                    'checkout_time'      => $datetime,
                    'checkout_deskripsi' => $keterangan,
                    'checkout_latitude'  => $latitude,
                    'checkout_longitude' => $longitude,
                    'update_by'          => $id_user,
                    'update_date'        => now(),
                    'checkout_approved'  => 0,
                ]);

            if ($updated) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Checkout berhasil disimpan. Menunggu persetujuan.'
                ], 200);
            }

            return response()->json([
                'status'  => false,
                'message' => 'Gagal menyimpan checkout (DB error).'
            ], 500);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Data absensi untuk checkout tidak ditemukan.'
        ], 404);
    }


    public function lupaCheckin(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $id         = $request->input('id');
        $id_user    = $request->input('id_user');
        $tanggal    = $request->input('tanggal');
        $waktu      = $request->input('waktu');
        $keterangan = $request->input('keterangan');
        $latitude   = $request->input('latitude');
        $longitude  = $request->input('longitude');

        // ─── 1. Validasi input ───
        if (empty($id_user) || empty($tanggal) || empty($waktu) || $latitude === null || $longitude === null) {
            return response()->json([
                'status'  => false,
                'message' => 'Semua parameter wajib diisi.'
            ], 400);
        }

        $datetime = date('Y-m-d H:i:s', strtotime("$tanggal $waktu"));
        if (!$datetime) {
            return response()->json([
                'status'  => false,
                'message' => 'Format tanggal atau waktu tidak valid.'
            ], 422);
        }

        // ─── 2. Cari SPK terdekat (maks 1km) ───
        $nearest_spk_id = null;
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();

        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($latitude, $longitude, (float) $spk->latitude, (float) $spk->longitude);
            if ($distance <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        // ─── 3. Simpan atau Update ───
        if ($id) {
            $updated = DB::table('absensi')
                ->where('id', $id)
                ->update([
                    'id_headerspk'      => $nearest_spk_id,
                    'checkin_time'      => $datetime,
                    'checkin_deskripsi' => $keterangan,
                    'checkin_latitude'  => $latitude,
                    'checkin_longitude' => $longitude,
                    'update_by'         => $id_user,
                    'update_date'       => now(),
                    'checkin_approved'  => 0,
                ]);

            if ($updated) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Check-in berhasil diperbarui. Menunggu persetujuan.'
                ], 200);
            }

            return response()->json([
                'status'  => false,
                'message' => 'Gagal memperbarui check-in.'
            ], 500);
        } else {
            $inserted = DB::table('absensi')->insert([
                'id_headerspk'      => $nearest_spk_id,
                'id_user'           => $id_user,
                'tanggal'           => $tanggal,
                'checkin_time'      => $datetime,
                'checkin_deskripsi' => $keterangan,
                'checkin_latitude'  => $latitude,
                'checkin_longitude' => $longitude,
                'create_by'         => $id_user,
                'create_date'       => now(),
                'checkin_approved'  => 0,
            ]);

            if ($inserted) {
                return response()->json([
                    'status'  => true,
                    'message' => 'Check-in berhasil disimpan. Menunggu persetujuan.'
                ], 200);
            }

            return response()->json([
                'status'  => false,
                'message' => 'Gagal menyimpan check-in.'
            ], 500);
        }
    }


    public function formAbsensi(Request $request)
    {
        date_default_timezone_set('Asia/Jakarta');

        $status     = strtolower($request->input('status'));
        $id_user    = $request->input('id_user');
        $tanggal    = $request->input('tanggal');
        $waktu      = $request->input('waktu');
        $keterangan = $request->input('keterangan');
        $latitude   = $request->input('latitude');
        $longitude  = $request->input('longitude');

        // ─── 1. Validasi parameter ───
        if (!$id_user || !$status || !$tanggal || !$waktu) {
            return response()->json([
                'status'  => false,
                'message' => 'Parameter tidak lengkap.'
            ], 400);
        }

        $datetime = date('Y-m-d H:i:s', strtotime("$tanggal $waktu"));
        if (!$datetime) {
            return response()->json([
                'status'  => false,
                'message' => 'Format tanggal/waktu tidak valid.'
            ], 422);
        }

        // ─── 2. Cari SPK terdekat ───
        $nearest_spk_id = null;
        $dataspk = DB::table('headerspk')->select('id_headerspk', 'latitude', 'longitude')->get();

        foreach ($dataspk as $spk) {
            $distance = $this->haversineDistance($latitude, $longitude, (float) $spk->latitude, (float) $spk->longitude);
            if ($distance <= 1) {
                $nearest_spk_id = $spk->id_headerspk;
                break;
            }
        }

        // ─── 3. Susun data absen ───
        $data = [
            'id_user'      => $id_user,
            'id_headerspk' => $nearest_spk_id,
            'tanggal'      => $tanggal,
            'create_by'    => $id_user,
            'create_date'  => now(),
        ];

        if ($status === 'checkin') {
            $data['checkin_time']      = $datetime;
            $data['checkin_deskripsi'] = $keterangan;
            $data['checkin_latitude']  = $latitude;
            $data['checkin_longitude'] = $longitude;
            $data['checkin_approved']  = 0;
        } elseif ($status === 'checkout') {
            $data['checkout_time']      = $datetime;
            $data['checkout_deskripsi'] = $keterangan;
            $data['checkout_latitude']  = $latitude;
            $data['checkout_longitude'] = $longitude;
            $data['checkout_approved']  = 0;
        } else {
            return response()->json([
                'status'  => false,
                'message' => 'Status tidak valid (harus checkin atau checkout).'
            ], 422);
        }

        // ─── 4. Simpan ke database ───
        $inserted = DB::table('absensi')->insert($data);

        if ($inserted) {
            return response()->json([
                'status'  => true,
                'message' => ucfirst($status) . ' berhasil disimpan. Menunggu persetujuan.'
            ], 200);
        }

        return response()->json([
            'status'  => false,
            'message' => 'Gagal menyimpan data absensi.'
        ], 500);
    }




}
