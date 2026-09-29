@extends('layouts.admin')

@section('title', 'Edit Produk')
@section('header_title', 'Edit Data Produk')

@section('content')
<div class="card border-0 shadow-sm col-md-8">
    <div class="card-body">
        <form action="{{ route('products.update', $product->id) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="mb-3">
                <label class="form-label fw-semibold">Nama Produk</label>
                <input type="text" name="name" value="{{ $product->name }}" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Harga (Rp)</label>
                <input type="number" name="price" value="{{ $product->price }}" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Deskripsi</label>
                <textarea name="description" class="form-control" rows="3">{{ $product->description }}</textarea>
            </div>
            <button type="submit" class="btn btn-warning text-white">Update</button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">Batal</a>
        </form>
    </div>
</div>
@endsection
