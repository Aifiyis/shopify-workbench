<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Auth\AdminLoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;

Route::get('/', function () {
    return Auth::guard('admin')->check() ? redirect('/dashboard') : redirect('/login');
});

Route::middleware('guest:admin')->group(function () {
    Route::get('/login', [AdminLoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AdminLoginController::class, 'login'])->name('login.post');
});

Route::middleware('auth:admin')->group(function () {
    Route::post('/logout', [AdminLoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

    Route::prefix('admins')->name('admins.')->group(function () {
        Route::get('/', [App\Http\Controllers\AdminManagementController::class, 'index'])->name('index');
        Route::get('/create', [App\Http\Controllers\AdminManagementController::class, 'create'])->name('create');
        Route::post('/', [App\Http\Controllers\AdminManagementController::class, 'store'])->name('store');
        Route::get('/{admin}/edit', [App\Http\Controllers\AdminManagementController::class, 'edit'])->name('edit');
        Route::put('/{admin}', [App\Http\Controllers\AdminManagementController::class, 'update'])->name('update');
        Route::delete('/{admin}', [App\Http\Controllers\AdminManagementController::class, 'destroy'])->name('destroy');
    });

    Route::get('/export/download/{filename}', [ExportController::class, 'download'])->name('export.download');

    Route::prefix('data-processing')->name('data-processing.')->group(function () {
        Route::get('/', [App\Http\Controllers\DataProcessingController::class, 'index'])->name('index');
        Route::post('/upload', [App\Http\Controllers\DataProcessingController::class, 'upload'])->name('upload');
        Route::get('/{id}/download', [App\Http\Controllers\DataProcessingController::class, 'download'])->name('download');
        Route::delete('/{id}', [App\Http\Controllers\DataProcessingController::class, 'delete'])->name('delete');
    });
});
