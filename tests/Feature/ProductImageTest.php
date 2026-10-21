<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
    $this->user = User::factory()->create();
});

function storeProduct($test, array $overrides = [])
{
    return $test->actingAs($test->user)->post('/admin/products', array_merge([
        'name' => 'Indomie Goreng',
        'price' => 3500,
    ], $overrides));
}

/** Ukuran & mime gambar yang benar-benar tersimpan di disk. */
function storedImageInfo(string $path): array
{
    $info = getimagesizefromstring(Storage::disk('public')->get($path));

    return ['width' => $info[0], 'height' => $info[1], 'mime' => $info['mime']];
}

// ---------------------------------------------------------------- form

test('add product form can upload files', function () {
    $this->actingAs($this->user)->get('/admin/products/create')
        ->assertOk()
        ->assertSee('enctype="multipart/form-data"', false)
        ->assertSee('type="file"', false)
        ->assertSee('name="image"', false)
        ->assertSee('id="imagePreview"', false);
});

test('guests cannot upload', function () {
    auth()->logout();

    $this->post('/admin/products', ['name' => 'X', 'price' => 1])->assertRedirect('/login');
    expect(Product::count())->toBe(0);
});

// ---------------------------------------------------------------- upload & manipulasi

test('a product image is uploaded, cropped to 800x800 and saved', function () {
    storeProduct($this, ['image' => UploadedFile::fake()->image('indomie.jpg', 1600, 900)])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHasNoErrors();

    $product = Product::firstOrFail();

    expect($product->image)->toStartWith('products/')->toEndWith('.jpg');
    Storage::disk('public')->assertExists($product->image);

    expect(storedImageInfo($product->image))->toBe(['width' => 800, 'height' => 800, 'mime' => 'image/jpeg']);
    expect($product->image_url)->toBe(asset('storage/'.$product->image));
});

test('portrait and small images also become 800x800', function (int $w, int $h) {
    storeProduct($this, ['image' => UploadedFile::fake()->image('foto.jpg', $w, $h)])->assertSessionHasNoErrors();

    $info = storedImageInfo(Product::firstOrFail()->image);
    expect([$info['width'], $info['height']])->toBe([800, 800]);
})->with([
    'portrait' => [600, 1200],
    'small' => [200, 100],
    'already square' => [800, 800],
]);

test('png stays png', function () {
    storeProduct($this, ['image' => UploadedFile::fake()->image('logo.png', 1000, 500)]);

    $product = Product::firstOrFail();
    expect($product->image)->toEndWith('.png')
        ->and(storedImageInfo($product->image)['mime'])->toBe('image/png');
});

test('webp is converted to jpg and the file name matches its content', function () {
    storeProduct($this, ['image' => UploadedFile::fake()->image('foto.webp', 900, 900)])->assertSessionHasNoErrors();

    $product = Product::firstOrFail();
    expect($product->image)->toEndWith('.jpg')
        ->and(storedImageInfo($product->image)['mime'])->toBe('image/jpeg');
});

test('a product can still be created without an image', function () {
    storeProduct($this)->assertRedirect(route('admin.dashboard'))->assertSessionHasNoErrors();

    $product = Product::firstOrFail();
    expect($product->image)->toBeNull()->and($product->image_url)->toBeNull();
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('stored file names are safe and unique', function () {
    $first = storeProduct($this, ['name' => 'A', 'image' => UploadedFile::fake()->image('Foto Produk (1).jpg')]);
    storeProduct($this, ['name' => 'B', 'image' => UploadedFile::fake()->image('Foto Produk (1).jpg')]);

    [$a, $b] = Product::orderBy('id')->pluck('image')->all();

    expect($a)->toMatch('/^products\/\d+_[A-Za-z0-9]{6}_[A-Za-z0-9_\-]+\.jpg$/')
        ->and($a)->not->toBe($b);
});

test('dangerous client file names cannot escape the products folder', function () {
    storeProduct($this, ['image' => UploadedFile::fake()->image('../../evil<>.jpg')]);

    $path = Product::firstOrFail()->image;
    expect($path)->toStartWith('products/')->not->toContain('..')->not->toContain('<');
});

// ---------------------------------------------------------------- validasi

test('invalid uploads are rejected and nothing is stored', function (callable $file, string $message) {
    storeProduct($this, ['image' => $file()])
        ->assertSessionHasErrors(['image' => $message]);

    expect(Product::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
})->with([
    'pdf file' => [fn () => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'), 'File harus berupa gambar.'],
    // File palsu di tes dikenali dari ekstensinya, jadi lolos aturan "image" dan ditangkap
    // oleh penjaga kedua (try/catch di controller). Di unggahan sungguhan isi file diperiksa
    // lebih awal (lihat pengujian HTTP nyata). Hasil akhirnya sama: ditolak, tidak ada yang tersimpan.
    'text renamed to jpg' => [fn () => UploadedFile::fake()->createWithContent('palsu.jpg', 'bukan gambar'), 'Gambar tidak dapat diproses. Coba gunakan file gambar lain.'],
    'over 2 MB' => [fn () => UploadedFile::fake()->image('besar.jpg')->size(3000), 'Ukuran gambar maksimal 2 MB.'],
    'gif not allowed' => [fn () => UploadedFile::fake()->image('anim.gif'), 'Format gambar harus JPG, JPEG, PNG, atau WEBP.'],
    'svg not allowed' => [fn () => UploadedFile::fake()->createWithContent('x.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'), 'File harus berupa gambar.'],
]);

test('exactly 2 MB is accepted', function () {
    storeProduct($this, ['image' => UploadedFile::fake()->image('pas.jpg', 400, 400)->size(2048)])
        ->assertSessionHasNoErrors();

    expect(Product::count())->toBe(1);
});

test('a corrupt image gives a friendly error instead of a server error', function () {
    $valid = UploadedFile::fake()->image('ok.jpg', 400, 400);
    $truncated = substr(file_get_contents($valid->getRealPath()), 0, 120);

    storeProduct($this, ['image' => UploadedFile::fake()->createWithContent('rusak.jpg', $truncated)])
        ->assertSessionHasErrors('image');

    expect(Product::count())->toBe(0)->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('typed values are kept when the upload is rejected', function () {
    $response = storeProduct($this, [
        'name' => 'Teh Botol',
        'price' => 5000,
        'image' => UploadedFile::fake()->create('dokumen.pdf', 10, 'application/pdf'),
    ]);

    $response->assertSessionHasErrors('image')->assertSessionHasInput('name', 'Teh Botol');
});

// ---------------------------------------------------------------- tampil & hapus

test('product list shows the thumbnail or a placeholder', function () {
    storeProduct($this, ['name' => 'Ada Gambar', 'image' => UploadedFile::fake()->image('a.jpg')]);
    Product::create(['name' => 'Tanpa Gambar', 'price' => 1000]);

    $with = Product::where('name', 'Ada Gambar')->firstOrFail();

    $this->actingAs($this->user)->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('<th>Gambar</th>', false)
        ->assertSee('src="'.asset('storage/'.$with->image).'"', false)
        ->assertSee('bi-image', false);
});

test('deleting a product also deletes its image file', function () {
    storeProduct($this, ['image' => UploadedFile::fake()->image('hapus.jpg')]);
    $product = Product::firstOrFail();
    Storage::disk('public')->assertExists($product->image);

    $this->actingAs($this->user)->delete("/admin/products/{$product->id}")->assertRedirect(route('admin.dashboard'));

    Storage::disk('public')->assertMissing($product->image);
    expect(Product::count())->toBe(0);
});

test('deleting a product without an image still works', function () {
    $product = Product::create(['name' => 'Polos', 'price' => 1000]);

    $this->actingAs($this->user)->delete("/admin/products/{$product->id}")->assertRedirect(route('admin.dashboard'));

    expect(Product::count())->toBe(0);
});

test('barcode and code uploads still work together with an image', function () {
    storeProduct($this, [
        'code' => 'BRG001',
        'barcode' => '899123456001',
        'image' => UploadedFile::fake()->image('i.jpg'),
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('products', ['code' => 'BRG001', 'barcode' => '899123456001']);
    expect(Product::firstOrFail()->image)->not->toBeNull();
});
