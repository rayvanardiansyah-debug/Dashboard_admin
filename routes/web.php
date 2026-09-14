<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

// 1. Halaman Frontend (127.0.0.1:8000)
Route::get('/', function () {
    return view('frontend.home');
})->name('home');

// 2. Halaman Login
Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// 3. Proses Form Login -> Masuk ke Halaman Admin
Route::post('/login-proses', function (Request $request) {
    // Sesuai instruksi alur: ketika tombol login diklik langsung menuju admin
    return redirect()->route('admin.dashboard');
})->name('login.proses');

// 4. Halaman Admin Dashboard
Route::get('/admin/dashboard', function () {
    return view('admin.dashboard');
})->name('admin.dashboard');
