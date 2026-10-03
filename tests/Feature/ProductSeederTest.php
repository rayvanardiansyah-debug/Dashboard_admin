<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ProductSeeder;

test('product seeder creates the six sample products from the practicum', function () {
    $this->seed(ProductSeeder::class);

    expect(Product::count())->toBe(6);

    $this->assertDatabaseHas('products', ['code' => 'BRG001', 'barcode' => '899123456001', 'name' => 'Indomie Goreng', 'price' => 3500]);
    $this->assertDatabaseHas('products', ['code' => 'BRG004', 'barcode' => '899123456004', 'name' => 'Beras 5 Kg', 'price' => 75000]);
    $this->assertDatabaseHas('products', ['code' => 'BRG006', 'barcode' => '899123456006', 'name' => 'Gula Pasir 1 Kg', 'price' => 17000]);
});

test('product seeder can be run repeatedly without duplicates', function () {
    $this->seed(ProductSeeder::class);
    Product::where('code', 'BRG002')->update(['price' => 1]); // harga diubah manual
    $this->seed(ProductSeeder::class);

    expect(Product::count())->toBe(6)
        ->and(Product::where('code', 'BRG002')->value('price'))->toBe(4000);
});

test('the default database seeder also creates the sample products', function () {
    $this->seed(DatabaseSeeder::class);

    expect(User::where('email', 'admin@gmail.com')->exists())->toBeTrue()
        ->and(Product::count())->toBe(6);
});

test('seeded products are available to the kasir with their barcodes', function () {
    $this->seed(ProductSeeder::class);

    $this->actingAs(User::factory()->create())
        ->get('/admin/kasir')
        ->assertOk()
        ->assertViewHas('products', fn ($products) => $products->count() === 6
            && $products->firstWhere('barcode', '899123456003')['name'] === 'Teh Botol Sosro'
            && $products->firstWhere('code', 'BRG005')['price'] === 18000);
});
