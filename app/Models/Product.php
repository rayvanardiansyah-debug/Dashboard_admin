<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'barcode', 'name', 'price', 'description', 'image'];

    /**
     * URL gambar produk (atau null jika belum ada gambar).
     * Memakai asset() agar mengikuti alamat yang sedang dibuka di browser
     * (127.0.0.1 / localhost / domain), dan membutuhkan `php artisan storage:link`.
     *
     *   {{ $product->image_url }}
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? asset('storage/'.$this->image) : null);
    }
}
