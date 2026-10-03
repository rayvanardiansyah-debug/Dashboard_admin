<?php

use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;

function payload(array $overrides = []): array
{
    return array_merge([
        'items' => [],
        'discount_percent' => 0,
        'discount_amount' => 0,
        'tax' => 0,
        'other_fee' => 0,
        'paid_amount' => 0,
        'payment_method' => 'Tunai',
        'customer_type' => 'Umum',
        'customer_phone' => null,
    ], $overrides);
}

// ---------------------------------------------------------------- halaman

test('guest is redirected to login for every kasir route', function () {
    $this->get('/admin/kasir')->assertRedirect('/login');
    $this->post('/admin/kasir/transaksi', [])->assertRedirect('/login');
    $this->get('/admin/kasir/struk/1')->assertRedirect('/login');
});

test('authenticated user can open the kasir page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/kasir')
        ->assertOk()
        ->assertSee('TOKO RETAIL MAKMUR')
        ->assertSee('BAYAR &amp; CETAK', false)
        ->assertSee('Kasir: '.$user->name)
        ->assertSee('TRX-'.now()->format('Ymd').'-001');
});

test('kasir page receives products with code and barcode', function () {
    $user = User::factory()->create();
    $a = Product::create(['code' => 'SKU-9', 'barcode' => '899000111', 'name' => 'Teh Botol', 'price' => 5000]);
    $b = Product::create(['name' => 'Aqua 600ml', 'price' => 4000]); // tanpa kode/barcode

    $this->actingAs($user)->get('/admin/kasir')
        ->assertOk()
        ->assertViewHas('products', function ($products) use ($a, $b) {
            $teh = $products->firstWhere('id', $a->id);
            $aqua = $products->firstWhere('id', $b->id);

            return $teh['code'] === 'SKU-9'
                && $teh['barcode'] === '899000111'
                && $aqua['code'] === 'BRG'.str_pad((string) $b->id, 3, '0', STR_PAD_LEFT)
                && $aqua['barcode'] === '';
        });
});

test('product names cannot break out of the script tag', function () {
    $user = User::factory()->create();
    Product::create(['name' => '</script><script>alert(1)</script>', 'price' => 1000]);

    $this->actingAs($user)
        ->get('/admin/kasir')
        ->assertOk()
        ->assertDontSee('</script><script>alert(1)', false);
});

// ---------------------------------------------------------------- simpan transaksi

test('a transaction is saved and totals are recalculated on the server', function () {
    $user = User::factory()->create();
    $indomie = Product::create(['code' => 'BRG001', 'name' => 'Indomie Goreng', 'price' => 3500]);
    $beras = Product::create(['code' => 'BRG004', 'name' => 'Beras 5 Kg', 'price' => 75000]);

    $response = $this->actingAs($user)->postJson('/admin/kasir/transaksi', payload([
        // 'price' dari browser harus diabaikan
        'items' => [
            ['id' => $indomie->id, 'qty' => 2, 'price' => 1],
            ['id' => $beras->id, 'qty' => 1, 'price' => 1],
        ],
        'discount_percent' => 10,
        'discount_amount' => 500,
        'tax' => 100,
        'other_fee' => 50,
        'paid_amount' => 100000,
        'payment_method' => 'QRIS',
        'customer_type' => 'Member VIP',
        'customer_phone' => '0812345',
    ]));

    // subtotal 7.000 + 75.000 = 82.000; diskon 8.200 + 500 = 8.700
    // total = 82.000 - 8.700 + 100 + 50 = 73.450; kembali = 26.550
    $response->assertCreated()
        ->assertJsonPath('transaction.number', 'TRX-'.now()->format('Ymd').'-001')
        ->assertJsonPath('transaction.grand_total', 73450)
        ->assertJsonPath('transaction.change_amount', 26550)
        ->assertJsonPath('next_number', 'TRX-'.now()->format('Ymd').'-002');

    $trx = Transaction::with('details')->firstOrFail();
    expect($trx->user_id)->toBe($user->id)
        ->and($trx->subtotal)->toBe(82000)
        ->and($trx->discount)->toBe(8700)
        ->and($trx->tax)->toBe(100)
        ->and($trx->other_fee)->toBe(50)
        ->and($trx->paid_amount)->toBe(100000)
        ->and($trx->payment_method)->toBe('QRIS')
        ->and($trx->customer_type)->toBe('Member VIP')
        ->and($trx->customer_phone)->toBe('0812345')
        ->and($trx->status)->toBe('paid')
        ->and($trx->details)->toHaveCount(2);

    $line = $trx->details->firstWhere('product_name', 'Indomie Goreng');
    expect($line->price)->toBe(3500)->and($line->qty)->toBe(2)->and($line->subtotal)->toBe(7000);
});

test('lines of the same product are merged', function () {
    $user = User::factory()->create();
    $p = Product::create(['name' => 'Aqua', 'price' => 4000]);

    $this->actingAs($user)->postJson('/admin/kasir/transaksi', payload([
        'items' => [['id' => $p->id, 'qty' => 2], ['id' => $p->id, 'qty' => 3]],
        'paid_amount' => 20000,
    ]))->assertCreated()->assertJsonPath('transaction.grand_total', 20000);

    expect(Transaction::first()->details)->toHaveCount(1)
        ->and(Transaction::first()->details->first()->qty)->toBe(5);
});

test('insufficient payment is rejected and nothing is saved', function () {
    $user = User::factory()->create();
    $p = Product::create(['name' => 'Beras', 'price' => 75000]);

    $this->actingAs($user)->postJson('/admin/kasir/transaksi', payload([
        'items' => [['id' => $p->id, 'qty' => 1]],
        'paid_amount' => 50000,
    ]))->assertStatus(422)->assertJsonValidationErrors('paid_amount');

    expect(Transaction::count())->toBe(0);
});

test('invalid transactions are rejected', function (array $override, string $errorKey) {
    $user = User::factory()->create();
    $p = Product::create(['name' => 'Aqua', 'price' => 4000]);

    $body = payload(['items' => [['id' => $p->id, 'qty' => 1]], 'paid_amount' => 10000]);

    $this->actingAs($user)->postJson('/admin/kasir/transaksi', array_merge($body, $override))
        ->assertStatus(422)->assertJsonValidationErrors($errorKey);

    expect(Transaction::count())->toBe(0);
})->with([
    'empty cart' => [['items' => []], 'items'],
    'unknown product' => [['items' => [['id' => 9999, 'qty' => 1]]], 'items.0.id'],
    'zero qty' => [['items' => [['id' => 1, 'qty' => 0]]], 'items.0.qty'],
    'bad payment method' => [['payment_method' => 'Cek Kosong'], 'payment_method'],
    'discount over 100%' => [['discount_percent' => 150], 'discount_percent'],
]);

test('transaction numbers run per day', function () {
    $user = User::factory()->create();
    $p = Product::create(['name' => 'Aqua', 'price' => 4000]);
    $body = payload(['items' => [['id' => $p->id, 'qty' => 1]], 'paid_amount' => 4000]);

    $first = $this->actingAs($user)->postJson('/admin/kasir/transaksi', $body);
    $second = $this->actingAs($user)->postJson('/admin/kasir/transaksi', $body);

    $first->assertJsonPath('transaction.number', 'TRX-'.now()->format('Ymd').'-001');
    $second->assertJsonPath('transaction.number', 'TRX-'.now()->format('Ymd').'-002');

    $this->travel(1)->days();

    $this->actingAs($user)->postJson('/admin/kasir/transaksi', $body)
        ->assertJsonPath('transaction.number', 'TRX-'.now()->format('Ymd').'-001');
});

test('saved lines keep the product snapshot after the product is changed or deleted', function () {
    $user = User::factory()->create();
    $p = Product::create(['code' => 'BRG001', 'name' => 'Indomie Goreng', 'price' => 3500]);

    $this->actingAs($user)->postJson('/admin/kasir/transaksi', payload([
        'items' => [['id' => $p->id, 'qty' => 1]],
        'paid_amount' => 3500,
    ]))->assertCreated();

    $p->update(['name' => 'Nama Baru', 'price' => 9999]);
    $p->delete();

    $line = Transaction::first()->details->first();
    expect($line->product_name)->toBe('Indomie Goreng')
        ->and($line->product_code)->toBe('BRG001')
        ->and($line->price)->toBe(3500)
        ->and($line->product_id)->toBeNull();
});

// ---------------------------------------------------------------- struk

test('receipt page shows the transaction', function () {
    $user = User::factory()->create(['name' => 'Budi']);
    $p = Product::create(['name' => 'Indomie Goreng', 'price' => 3500]);

    $json = $this->actingAs($user)->postJson('/admin/kasir/transaksi', payload([
        'items' => [['id' => $p->id, 'qty' => 2]],
        'paid_amount' => 10000,
    ]))->json();

    $this->get($json['receipt_url'])
        ->assertOk()
        ->assertSee('TOKO RETAIL MAKMUR')
        ->assertSee($json['transaction']['number'])
        ->assertSee('Indomie Goreng')
        ->assertSee('2 x 3.500', false)
        ->assertSee('Rp 7.000')
        ->assertSee('Rp 10.000')
        ->assertSee('Rp 3.000')
        ->assertSee('Budi')
        ->assertSee('size: 80mm auto', false)
        ->assertDontSee("addEventListener('load'", false);
});

test('receipt can be printed on 58mm paper and can auto print', function () {
    $user = User::factory()->create();
    $p = Product::create(['name' => 'Aqua', 'price' => 4000]);

    $json = $this->actingAs($user)->postJson('/admin/kasir/transaksi', payload([
        'items' => [['id' => $p->id, 'qty' => 1]],
        'paid_amount' => 4000,
    ]))->json();

    $this->get($json['receipt_url'].'?w=58&print=1')
        ->assertOk()
        ->assertSee('size: 58mm auto', false)
        ->assertSee("addEventListener('load'", false);
});

test('receipt escapes product names', function () {
    $user = User::factory()->create();
    $p = Product::create(['name' => '<script>alert(1)</script>', 'price' => 1000]);

    $json = $this->actingAs($user)->postJson('/admin/kasir/transaksi', payload([
        'items' => [['id' => $p->id, 'qty' => 1]],
        'paid_amount' => 1000,
    ]))->json();

    $this->get($json['receipt_url'])
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', false);
});

// ---------------------------------------------------------------- tanpa popup bawaan browser

test('kasir page has in-page dialogs and toast instead of browser popups', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/kasir')
        ->assertOk()
        ->assertSee('id="confirmModal"', false)
        ->assertSee('id="heldModal"', false)
        ->assertSee('id="heldButton"', false)
        ->assertSee('id="toast"', false);
});

test('chasier.js never calls alert, confirm or prompt', function () {
    $js = file_get_contents(public_path('js/chasier.js'));

    expect(preg_match('/(?<![\w.])(alert|confirm|prompt)\s*\(/', $js))->toBe(0);
});
