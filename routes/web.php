<?php

use App\Http\Controllers\KasirController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MidtransController;
use Illuminate\Support\Facades\Route;

// 1. Frontend Utama (127.0.0.1:8000)
Route::get('/', function () {
    return view('frontend.home');
})->name('home');

// 2. Panel Admin & CRUD (Diproteksi Auth Breeze)
Route::middleware(['auth', 'verified'])->prefix('admin')->group(function () {
    // Admin Dashboard (Menampilkan Tabel CRUD)
    Route::get('/dashboard', [ProductController::class, 'index'])->name('admin.dashboard');

    // Halaman Kasir (transaksi pembayaran)
    Route::get('/kasir', [KasirController::class, 'index'])->name('admin.kasir');
    Route::post('/kasir/transaksi', [KasirController::class, 'store'])->name('admin.kasir.store');
    Route::get('/kasir/struk/{transaction}', [KasirController::class, 'struk'])->name('admin.kasir.struk');
    Route::get('/kasir/invoice/{transaction}', [KasirController::class, 'invoice'])->name('admin.kasir.invoice');

    // Pembayaran non-tunai via Midtrans Snap 
    Route::post('/kasir/midtrans', [KasirController::class, 'midtransCharge'])->name('admin.kasir.midtrans');
    Route::get('/kasir/midtrans/{transaction}/status', [KasirController::class, 'midtransStatus'])->name('admin.kasir.midtrans.status');

    // Resource Route CRUD
    Route::resource('products', ProductController::class)->except(['show']);

    // Order Broadcast Trigger (Acara 31 & 32)
    Route::post('/order/trigger', [OrderController::class, 'store'])->name('admin.order.trigger');
    Route::get('/order/trigger', [OrderController::class, 'store'])->name('admin.order.trigger.get');

    // Profile Controller bawaan Breeze
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// 3. Midtrans
// Halaman demo checkout produk digital (sesuai modul). Halaman utama '/' tidak ditimpa lagi.
Route::get('/demo-checkout', function () {
    return view('checkout');
})->name('demo.checkout');

Route::post('/checkout', [MidtransController::class, 'checkout']);

// Webhook dari Midtrans: harus publik, POST, dan dikecualikan dari CSRF (lihat bootstrap/app.php)
Route::post('/midtrans/notification', [MidtransController::class, 'notification']);

// Load Rute Autentikasi Bawaan Breeze
require __DIR__.'/auth.php';
