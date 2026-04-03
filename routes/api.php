<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\Http\Controllers\VoucherDataController;
use LBHurtado\Voucher\Http\Controllers\VoucherGenerationController;

Route::post('/vouchers/generate', VoucherGenerationController::class)->name('vouchers.generate');
Route::get('/vouchers/data/{code}', VoucherDataController::class)->name('vouchers.data');
