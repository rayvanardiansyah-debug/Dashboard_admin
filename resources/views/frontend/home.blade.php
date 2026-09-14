@extends('layouts.frontend')

@section('title', 'Beranda - Selamat Datang')

@section('content')
<!-- Hero Section -->
<section class="py-5 bg-white border-bottom">
    <div class="container text-center py-5">
        <h1 class="display-5 fw-bold text-dark mb-3">Selamat Datang di Portal Utama</h1>
        <p class="lead text-secondary mx-auto mb-4" style="max-width: 650px;">
            Sistem informasi terpadu. Silakan login melalui tombol di pojok kanan atas navbar untuk mengelola data di panel admin.
        </p>
        <div class="d-flex justify-content-center gap-2">
            <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4 rounded-pill">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sekarang
            </a>
            <a href="#fitur" class="btn btn-outline-secondary btn-lg px-4 rounded-pill">Pelajari Lebih Lanjut</a>
        </div>
    </div>
</section>

<!-- Fitur Section -->
<section id="fitur" class="container py-5">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 p-3">
                <div class="card-body">
                    <i class="bi bi-speedometer2 fs-1 text-primary mb-3"></i>
                    <h5 class="card-title fw-bold">Performa Cepat</h5>
                    <p class="card-text text-muted">Dibangun menggunakan framework Laravel dan styling Bootstrap 5 yang ringan dan responsif.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 p-3">
                <div class="card-body">
                    <i class="bi bi-shield-lock fs-1 text-success mb-3"></i>
                    <h5 class="card-title fw-bold">Akses Aman</h5>
                    <p class="card-text text-muted">Panel admin terlindungi dengan sistem autentikasi dan alur navigasi terstruktur.</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 p-3">
                <div class="card-body">
                    <i class="bi bi-layout-wtf fs-1 text-warning mb-3"></i>
                    <h5 class="card-title fw-bold">Desain Modern</h5>
                    <p class="card-text text-muted">Tampilan clean, rapi, dan mudah disesuaikan untuk berbagai kebutuhan aplikasi web.</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
