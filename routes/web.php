<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

// 1. Frontend Utama (127.0.0.1:8000)
Route::get('/', function () {
    return view('frontend.home');
})->name('home');

// 2. Panel Admin & CRUD (Diproteksi Auth Breeze)
Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    // Admin Dashboard (Menampilkan Tabel CRUD)
    Route::get('/dashboard', [ProductController::class, 'index'])->name('admin.dashboard');

    // Resource Route CRUD
    Route::resource('products', ProductController::class)->except(['show']);

    // Profile Controller bawaan Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Load Rute Autentikasi Bawaan Breeze
require __DIR__.'/auth.php';
