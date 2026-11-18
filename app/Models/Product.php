<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    protected $fillable = ['category_id', 'code', 'barcode', 'name', 'price', 'stock', 'description', 'image'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     *   {{ $product->image_url }}
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(fn () => $this->image ? asset('storage/'.$this->image) : null);
    }
}
