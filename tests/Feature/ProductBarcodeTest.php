<?php

use App\Models\Product;
use App\Models\User;

test('a product can be created with code and barcode', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/admin/products', [
        'code' => 'BRG001',
        'barcode' => '899123456001',
        'name' => 'Indomie Goreng',
        'price' => 3500,
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('products', ['code' => 'BRG001', 'barcode' => '899123456001']);
});

test('barcode and code must be unique', function () {
    $user = User::factory()->create();
    Product::create(['code' => 'BRG001', 'barcode' => '899123456001', 'name' => 'A', 'price' => 1000]);

    $this->actingAs($user)->post('/admin/products', [
        'code' => 'BRG002',
        'barcode' => '899123456001',
        'name' => 'B',
        'price' => 1000,
    ])->assertSessionHasErrors('barcode');

    $this->actingAs($user)->post('/admin/products', [
        'code' => 'BRG001',
        'barcode' => '899123456002',
        'name' => 'C',
        'price' => 1000,
    ])->assertSessionHasErrors('code');
});

test('products without code and barcode are still allowed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post('/admin/products', ['name' => 'A', 'price' => 1000])->assertSessionHasNoErrors();
    $this->actingAs($user)->post('/admin/products', ['name' => 'B', 'price' => 2000, 'code' => '', 'barcode' => ''])
        ->assertSessionHasNoErrors();

    expect(Product::count())->toBe(2);
});

test('a product can keep its own barcode when updated', function () {
    $user = User::factory()->create();
    $p = Product::create(['code' => 'BRG001', 'barcode' => '899123456001', 'name' => 'A', 'price' => 1000]);

    $this->actingAs($user)->put("/admin/products/{$p->id}", [
        'code' => 'BRG001',
        'barcode' => '899123456001',
        'name' => 'A baru',
        'price' => 1500,
    ])->assertSessionHasNoErrors();

    expect($p->fresh()->name)->toBe('A baru');
});

test('product forms show the barcode fields', function () {
    $user = User::factory()->create();
    $p = Product::create(['code' => 'BRG001', 'barcode' => '899123456001', 'name' => 'A', 'price' => 1000]);

    $this->actingAs($user)->get('/admin/products/create')->assertOk()->assertSee('name="barcode"', false);
    $this->actingAs($user)->get("/admin/products/{$p->id}/edit")->assertOk()->assertSee('899123456001');
    $this->actingAs($user)->get('/admin/dashboard')->assertOk()->assertSee('899123456001');
});
