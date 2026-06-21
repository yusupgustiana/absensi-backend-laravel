<?php
namespace App\Http\Controllers\Api;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Absensi;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class Absensiv2Controller extends Controller
{


public function kirimAbsen(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'id_user'   => 'required|integer',
            'latitude'  => 'required|numeric',
            'longitude' => 'required|numeric',
            'status'    => 'nullable|string|in:checkin,checkout,otomatis',
            'image'     => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'     => false,
                'message'    => $validator->errors()->first(),
                'statusCode' => (string) Response::HTTP_UNPROCESSABLE_ENTITY,
                'data'       => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $idUser    = $request->integer('id_user');
        $latitude  = $request->latitude;
        $longitude = $request->longitude;
        $status    = strtolower(trim($request->input('status', 'otomatis')));
        $image64   = $request->image;
        $now       = now()->format('Y-m-d H:i:s');

        // Simpan foto
        $fileName = $this->simpanFoto($image64);

        if (!$fileName) {
            return response()->json([
                'status'     => false,
                'message'    => 'Gagal menyimpan file foto',
                'statusCode' => (string) Response::HTTP_INTERNAL_SERVER_ERROR,
                'data'       => null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Cari SPK terdekat
        $nearestSpkId = $this->cariSpkTerdekat($latitude, $longitude);

        // Ambil absensi terakhir
        $lastAbsen = DB::table('absensi')
            ->where('id_user', $idUser)
            ->where('deleted', 0)
            ->latest('id')
            ->first();

        // Mode otomatis
        if ($status === 'otomatis') {

            if (!$lastAbsen || ($lastAbsen->checkin_time && $lastAbsen->checkout_time)) {
                return $this->insertCheckin(
                    $idUser,
                    $nearestSpkId,
                    $latitude,
                    $longitude,
                    $fileName,
                    $now,
                    'Check-in otomatis berhasil.'
                );
            }

            if ($lastAbsen->checkin_time && !$lastAbsen->checkout_time) {
                return $this->updateCheckout(
                    $lastAbsen->id,
                    $idUser,
                    $latitude,
                    $longitude,
                    $fileName,
                    $now,
                    'Check-out otomatis berhasil.'
                );
            }
        }

        // Check-in manual
        if ($status === 'checkin') {
            return $this->insertCheckin(
                $idUser,
                $nearestSpkId,
                $latitude,
                $longitude,
                $fileName,
                $now,
                'Check-in manual berhasil.'
            );
        }

        // Check-out manual
        if ($status === 'checkout') {
            return $this->insertCheckoutManual(
                $idUser,
                $nearestSpkId,
                $latitude,
                $longitude,
                $fileName,
                $now,
                'Check-out manual berhasil.'
            );
        }

        return response()->json([
            'status'     => false,
            'message'    => 'Status tidak dikenali',
            'statusCode' => (string) Response::HTTP_UNPROCESSABLE_ENTITY,
            'data'       => null,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);

    } catch (\Throwable $e) {

        \Log::error('Kirim Absen Error', [
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ]);

        return response()->json([
            'status'     => false,
            'message'    => 'Terjadi kesalahan pada server',
            'statusCode' => (string) Response::HTTP_INTERNAL_SERVER_ERROR,
            'data'       => null,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
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

private function insertCheckin(
    int $idUser,
    ?int $spkId,
    float $lat,
    float $lng,
    string $foto,
    string $now,
    string $message
) {
    $idAbsensi = DB::table('absensi')->insertGetId([
        'id_user'           => $idUser,
        'id_headerspk'      => $spkId,
        'tanggal'           => now()->toDateString(),
        'checkin_time'      => $now,
        'checkin_latitude'  => $lat,
        'checkin_longitude' => $lng,
        'checkin_foto'      => $foto,
        'create_by'         => $idUser,
        'create_date'       => $now,
        'deleted'           => 0,
    ]);

    return response()->json([
        'status'     => true,
        'message'    => $message,
        'statusCode' => (string) Response::HTTP_CREATED,
        'data'       => [
            'id_absensi' => $idAbsensi,
        ],
    ], Response::HTTP_CREATED);
}


private function updateCheckout(
    int $absenId,
    int $idUser,
    float $lat,
    float $lng,
    string $foto,
    string $now,
    string $message
) {
    $updated = DB::table('absensi')
        ->where('id', $absenId)
        ->update([
            'checkout_time'      => $now,
            'checkout_latitude'  => $lat,
            'checkout_longitude' => $lng,
            'checkout_foto'      => $foto,
            'update_by'          => $idUser,
            'update_date'        => $now,
        ]);

    if (!$updated) {
        return response()->json([
            'status'     => false,
            'message'    => 'Data absensi tidak ditemukan atau tidak ada perubahan.',
            'statusCode' => (string) Response::HTTP_NOT_FOUND,
            'data'       => null,
        ], Response::HTTP_NOT_FOUND);
    }

    return response()->json([
        'status'     => true,
        'message'    => $message,
        'statusCode' => (string) Response::HTTP_OK,
        'data'       => [
            'id_absensi' => $absenId,
        ],
    ], Response::HTTP_OK);
}

private function insertCheckoutManual(
    int $idUser,
    ?int $spkId,
    float $lat,
    float $lng,
    string $foto,
    string $now,
    string $message
) {
    $idAbsensi = DB::table('absensi')->insertGetId([
        'id_user'            => $idUser,
        'id_headerspk'       => $spkId,
        'tanggal'            => now()->toDateString(),
        'checkout_time'      => $now,
        'checkout_latitude'  => $lat,
        'checkout_longitude' => $lng,
        'checkout_foto'      => $foto,
        'create_by'          => $idUser,
        'create_date'        => $now,
        'deleted'            => 0,
    ]);

    return response()->json([
        'status'     => true,
        'message'    => $message,
        'statusCode' => (string) Response::HTTP_CREATED,
        'data'       => [
            'id_absensi' => $idAbsensi,
        ],
    ], Response::HTTP_CREATED);
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
    $validator = Validator::make($request->all(), [
        'iduser' => 'required|integer',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'status' => false,
            'message' => $validator->errors()->first(),
            'statusCode' => '422',
            'data' => null,
        ], 422);
    }

    $iduser = $request->iduser;

    $last = DB::table('absensi')
        ->where('id_user', $iduser)
        ->where('deleted', 0)
        ->orderByDesc('id')
        ->first();

    if ($last) {
        return response()->json([
            'status' => true,
            'message' => 'Data absensi ditemukan',
            'statusCode' => '200',
            'data' => [
                'jenisabsensi'  => is_null($last->checkout_time) ? 'Checkin' : 'Checkout',
                'tanggal'       => $last->tanggal,
                'checkin_time'  => $last->checkin_time,
                'checkout_time' => $last->checkout_time,
            ]
        ], 200);
    }

    return response()->json([
        'status' => true,
        'message' => 'Belum ada data absensi',
        'statusCode' => '200',
        'data' => null,
    ], 200);
}


    
 
public function historyAbsensi(Request $request)
{
    try {

        $validator = Validator::make($request->all(), [
            'id_user'       => 'required|integer',
            'tanggaldari'   => 'nullable|date',
            'tanggalsampai' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'     => false,
                'message'    => $validator->errors()->first(),
                'statusCode' => (string) Response::HTTP_UNPROCESSABLE_ENTITY,
                'data'       => null,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $idUser        = $request->integer('id_user');
        $tanggalDari   = $request->input('tanggaldari');
        $tanggalSampai = $request->input('tanggalsampai');

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

        // Admin dapat melihat semua data
        if ($idUser !== 1) {
            $query->where('a.id_user', $idUser);
        }

        // Filter tanggal
        if ($tanggalDari && $tanggalSampai) {

            $query->whereBetween('a.tanggal', [
                Carbon::parse($tanggalDari)->format('Y-m-d'),
                Carbon::parse($tanggalSampai)->format('Y-m-d'),
            ]);

        } else {

            $query->where(
                'a.tanggal',
                '>=',
                Carbon::now()->subDays(14)->format('Y-m-d')
            );
        }

        $result = $query
            ->groupBy([
                'a.id_user',
                'u.nama',
                'a.tanggal',
            ])
            ->orderByDesc('a.tanggal')
            ->get();

        return response()->json([
            'status'     => true,
            'message'    => $idUser === 1
                ? 'Data absensi semua user berhasil diambil.'
                : 'Data absensi berhasil diambil.',
            'statusCode' => (string) Response::HTTP_OK,
            'data'       => $result,
        ], Response::HTTP_OK);

    } catch (\Throwable $e) {

        \Log::error('History Absensi Error', [
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
            'file'    => $e->getFile(),
        ]);

        return response()->json([
            'status'     => false,
            'message'    => 'Terjadi kesalahan pada server.',
            'statusCode' => (string) Response::HTTP_INTERNAL_SERVER_ERROR,
            'data'       => null,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
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
