@extends('layouts.frontend')

@section('title', 'Selamat Datang')

@section('content')
<section class="py-5 bg-white border-bottom">
    <div class="container text-center py-5">
        <h1 class="display-5 fw-bold text-dark mb-3">Selamat Datang di Portal Utama</h1>
        <p class="lead text-secondary mx-auto mb-4" style="max-width: 600px;">
            Sistem informasi terpadu. Klik tombol login di pojok kanan atas untuk mengelola data di dashboard admin.
        </p>
        <a href="{{ route('login') }}" class="btn btn-primary btn-lg px-4 rounded-pill">Masuk ke Panel Admin</a>
    </div>
</section>
@endsection
