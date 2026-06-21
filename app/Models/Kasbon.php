<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kasbon extends Model
{

    protected $table = 'kasbon';


    protected $primaryKey = 'id_kasbon';


    protected $fillable = [

        'id_user',
        'nama_user',
        'jumlah',
        'keterangan',

        'status_pengajuan',
        'status_pembayaran',

        'bukti_transfer',

        'tanggal_pengajuan',

        'tanggal_disetujui',
        'tanggal_ditolak',
        'tanggal_lunas',

    ];


    protected $casts = [

        'jumlah' => 'decimal:2',

        'tanggal_pengajuan' => 'datetime',

        'tanggal_disetujui' => 'datetime',

        'tanggal_ditolak' => 'datetime',

        'tanggal_lunas' => 'datetime',

    ];

}