@extends('layouts.admin')

@section('title', 'Tambah Produk')
@section('header_title', 'Tambah Produk Baru')

@section('content')
<div class="card border-0 shadow-sm col-md-8">
    <div class="card-body">
        <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Kode Barang</label>
                    <input type="text" name="code" value="{{ old('code') }}" class="form-control" placeholder="Contoh: BRG001">
                @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-semibold">Barcode</label>
                    <input type="text" name="barcode" value="{{ old('barcode') }}" class="form-control" placeholder="Scan atau ketik barcode" autocomplete="off">
                @error('barcode')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Nama Produk</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required>
                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Harga (Rp)</label>
                <input type="number" name="price" value="{{ old('price') }}" class="form-control @error('price') is-invalid @enderror" required>
                @error('price')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Deskripsi</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Gambar Produk</label>
                <input type="file" name="image" id="imageInput"
                       class="form-control @error('image') is-invalid @enderror"
                       accept="image/jpeg,image/png,image/webp">
                <div class="form-text">
                    JPG, PNG, atau WEBP, maksimal 2 MB. Gambar otomatis dipotong dan diubah menjadi 800 &times; 800 px.
                </div>
                @error('image')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                <div class="text-danger small mt-1" id="imageClientError" hidden></div>

                <!-- Pratinjau gambar yang dipilih -->
                <img id="imagePreview" alt="Pratinjau gambar produk" hidden
                     class="mt-2 rounded border" style="width: 160px; height: 160px; object-fit: cover;">
            </div>
            <button type="submit" class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
<script>
    // Pratinjau gambar + pengecekan ukuran (maks 2 MB) sebelum dikirim
    (function () {
        const input = document.getElementById('imageInput');
        const preview = document.getElementById('imagePreview');
        const error = document.getElementById('imageClientError');
        const MAX_BYTES = 2 * 1024 * 1024;

        input.addEventListener('change', function () {
            const file = input.files[0];
            error.hidden = true;
            preview.hidden = true;

            if (!file) return;

            if (file.size > MAX_BYTES) {
                error.textContent = 'Ukuran gambar maksimal 2 MB (file ini ' + (file.size / 1024 / 1024).toFixed(1) + ' MB).';
                error.hidden = false;
                input.value = '';
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.hidden = false;
        });
    })();
</script>
@endsection
