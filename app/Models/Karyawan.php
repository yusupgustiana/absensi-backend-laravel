<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;

class Karyawan extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'karyawan';
    protected $primaryKey = 'id_user';

    public $timestamps = false;

    protected $fillable = [
        'id_user',
        'id_absensi',
        'username',
        'nama',
        'role_id',
        'password',
        'image',
        'is_active'
    ];

    protected $hidden = [
        'password',
    ];
}