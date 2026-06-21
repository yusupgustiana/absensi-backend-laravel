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
        'is_active',
        'jenisakun',
        'akses_kasbon',
        'approved_absen',
    ];

    protected $hidden = [
        'password',
    ];
       protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if ($model->jenisakun == 1) {
                $model->akses_kasbon   = true;
                $model->approved_absen = true;
            } else {
                $model->akses_kasbon   = false;
                $model->approved_absen = false;
            }
        });
    }
}