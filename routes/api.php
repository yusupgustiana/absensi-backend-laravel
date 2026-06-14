<?php

// use Illuminate\Http\Request;
// use Illuminate\Support\Facades\Route;
// use App\Http\Controllers\Api\AbsensiApiController;
// use App\Http\Controllers\Api\AuthApiController;


// Route::post('/login', [AuthApiController::class, 'login']);
// Route::prefix('absensi')->group(function () {
//     Route::post('/print', [AbsensiApiController::class, 'printAbsensi']);
//     Route::post('/approve-admin', [AbsensiApiController::class, 'approveAbsensiByAdmin']);
//     Route::post('/incomplete', [AbsensiApiController::class, 'getIncompleteAbsensi']);
//     Route::post('/unapproved', [AbsensiApiController::class, 'getUnapprovedAbsensi']);
//     Route::post('/filter', [AbsensiApiController::class, 'filterAbsensi']);
//     Route::post('/approve-checkout-admin', [AbsensiApiController::class, 'approveCheckoutByAdmin']);
//     Route::post('/update', [AbsensiApiController::class, 'updateAbsensi']);
//     Route::post('/approve-checkout', [AbsensiApiController::class, 'approveAbsensiCheckout']);
//     Route::post('/approve-checkin', [AbsensiApiController::class, 'approveAbsensiCheckin']);
//     Route::post('/save-checkout', [AbsensiApiController::class, 'saveAbsensiCheckout']);
//     Route::post('/save-checkin', [AbsensiApiController::class, 'saveAbsensiCheckin']);
//     Route::post('/approve', [AbsensiApiController::class, 'approveAbsensi']);
//     Route::post('/send', [AbsensiApiController::class, 'sendAbsensi']);
//     Route::post('/last', [AbsensiApiController::class, 'getLastAbsensi']);
//     Route::post('/last-two-weeks', [AbsensiApiController::class, 'getAbsensiLastTwoWeeks']);
//     Route::post('/list', [AbsensiApiController::class, 'listAbsensi']);
//     Route::post('/duplicate-update', [AbsensiApiController::class, 'duplicateAndUpdateAbsensi']);
// });

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Absensiv2Controller;
use App\Http\Controllers\Api\KasbonControllerApi;
use App\Http\Controllers\Api\AuthApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Laravel Fortify sudah meng-handle auth (login, register, dll).
| Di sini kita buat route dengan prefix sesuai kebutuhan.
*/
Route::post('/login', [AuthApiController::class, 'login']);
// Prefix ABSENSI
Route::prefix('absensi')->group(function () {
    Route::post('kirimAbsen', [Absensiv2Controller::class, 'kirimAbsen']);
    Route::post('simpanFoto', [Absensiv2Controller::class, 'simpanFoto']);
    Route::post('insertCheckin', [Absensiv2Controller::class, 'insertCheckin']);
    Route::post('updateCheckout', [Absensiv2Controller::class, 'updateCheckout']);
    Route::post('insertCheckoutManual', [Absensiv2Controller::class, 'insertCheckoutManual']);
    Route::post('absensiTerakhir', [Absensiv2Controller::class, 'absensiTerakhir']);
    Route::post('historyAbsensi', [Absensiv2Controller::class, 'historyAbsensi']);
    Route::post('lupaCheckout', [Absensiv2Controller::class, 'lupaCheckout']);
    Route::post('lupaCheckin', [Absensiv2Controller::class, 'lupaCheckin']);
    Route::post('formAbsensi', [Absensiv2Controller::class, 'formAbsensi']);
});

// Prefix KASBON
Route::prefix('kasbon')->middleware(['auth:sanctum'])->group(function () {
    Route::post('pengajuan', [KasbonControllerApi::class, 'pengajuan']);
    Route::post('history', [KasbonControllerApi::class, 'history']);
    Route::post('uploadBuktiTransfer', [KasbonControllerApi::class, 'uploadBuktiTransfer']);
    Route::post('allHistory', [KasbonControllerApi::class, 'allHistory']);
});
