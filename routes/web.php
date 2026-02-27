<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ExcelController;
use App\Http\Middleware\AccessPassword;

Route::get('/login', [ExcelController::class, 'loginForm'])->name('login');
Route::post('/login', [ExcelController::class, 'login'])->name('login.submit');

Route::middleware(AccessPassword::class)->group(function () {
    Route::get('/', [ExcelController::class, 'index']);
    Route::post('/process', [ExcelController::class, 'process'])->name('excel.process');
    Route::post('/logout', [ExcelController::class, 'logout'])->name('logout');
    Route::post('/change-password', [ExcelController::class, 'changePassword'])->name('change.password');
});
