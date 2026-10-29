<?php

use App\Models\Transaction;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Membuat transaksi contoh: 2x Indomie Goreng (3.500) + 1x Beras 5 Kg (75.000).
 */
function makeTransaction(User $user, array $overrides = []): Transaction
{
    $transaction = Transaction::create(array_merge([
        'transaction_number' => 'TRX-'.now()->format('Ymd').'-'.str_pad((string) (Transaction::count() + 1), 3, '0', STR_PAD_LEFT),
        'user_id' => $user->id,
        'customer_type' => 'Umum',
        'subtotal' => 82000,
        'discount' => 0,
        'tax' => 0,
        'other_fee' => 0,
        'grand_total' => 82000,
        'paid_amount' => 100000,
        'change_amount' => 18000,
        'payment_method' => 'Tunai',
        'status' => 'paid',
    ], $overrides));

    // product_id null = barang sudah dihapus dari daftar produk; invoice memakai snapshot nama.
    $transaction->details()->createMany([
        ['product_id' => null, 'product_code' => 'BRG001', 'product_name' => 'Indomie Goreng', 'price' => 3500, 'qty' => 2, 'discount' => 0, 'subtotal' => 7000],
        ['product_id' => null, 'product_code' => 'BRG004', 'product_name' => 'Beras 5 Kg', 'price' => 75000, 'qty' => 1, 'discount' => 0, 'subtotal' => 75000],
    ]);

    return $transaction;
}

/** Mengambil teks dari semua stream PDF (dompdf memakai font bawaan, teks tersimpan apa adanya). */
function pdfText(string $pdf): string
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $streams);

    return collect($streams[1])
        ->map(fn ($stream) => @gzuncompress($stream) ?: $stream)
        ->implode("\n");
}

// ---------------------------------------------------------------- akses

test('guests cannot download an invoice', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user);

    $this->get("/admin/kasir/invoice/{$transaction->id}")->assertRedirect('/login');
});

test('unknown transaction returns 404', function () {
    $this->actingAs(User::factory()->create())->get('/admin/kasir/invoice/9999')->assertNotFound();
});

// ---------------------------------------------------------------- unduh PDF

test('invoice is downloaded as a real PDF file', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user);

    $response = $this->actingAs($user)->get(route('admin.kasir.invoice', $transaction))->assertOk();

    $response->assertHeader('content-type', 'application/pdf');
    expect($response->headers->get('content-disposition'))
        ->toContain('attachment')
        ->toContain("invoice_{$transaction->id}.pdf");
    expect($response->getContent())->toStartWith('%PDF-');
});

test('pdf contains the items, prices, quantities and grand total', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user);

    $text = pdfText($this->actingAs($user)->get(route('admin.kasir.invoice', $transaction))->getContent());

    expect($text)
        ->toContain('INVOICE')
        ->toContain('No: '.$transaction->id)
        ->toContain('Tanggal: '.now()->format('d/m/Y'))
        ->toContain('Indomie Goreng')->toContain('Rp 3.500')->toContain('Rp 7.000')
        ->toContain('Beras 5 Kg')->toContain('Rp 75.000')
        ->toContain('Grand Total')->toContain('Rp 82.000');
});

test('grand total in the pdf is the saved grand_total after discount and tax', function () {
    $user = User::factory()->create();
    // subtotal 82.000 - diskon 8.200 + pajak 500 = 74.300
    $transaction = makeTransaction($user, ['discount' => 8200, 'tax' => 500, 'grand_total' => 74300, 'paid_amount' => 80000, 'change_amount' => 5700]);

    $text = pdfText($this->actingAs($user)->get(route('admin.kasir.invoice', $transaction))->getContent());

    expect($text)->toContain('Rp 74.300')->not->toContain('Rp 82.000');
});

test('invoice for a transaction made via the kasir can be downloaded', function () {
    $user = User::factory()->create();
    $product = App\Models\Product::create(['name' => 'Teh Botol Sosro', 'price' => 5000]);

    $json = $this->actingAs($user)->postJson('/admin/kasir/transaksi', [
        'items' => [['id' => $product->id, 'qty' => 3]],
        'paid_amount' => 20000,
        'payment_method' => 'Tunai',
    ])->assertCreated()->json();

    expect($json['invoice_url'])->toBe(route('admin.kasir.invoice', $json['transaction']['id']));

    $text = pdfText($this->actingAs($user)->get($json['invoice_url'])->getContent());
    expect($text)->toContain('Teh Botol Sosro')->toContain('Rp 15.000');
});

test('invoice keeps the product name even after the product is deleted', function () {
    $user = User::factory()->create();
    $product = App\Models\Product::create(['name' => 'Gula Pasir 1 Kg', 'price' => 17000]);

    $json = $this->actingAs($user)->postJson('/admin/kasir/transaksi', [
        'items' => [['id' => $product->id, 'qty' => 1]],
        'paid_amount' => 17000,
        'payment_method' => 'Tunai',
    ])->json();

    $product->delete();

    $text = pdfText($this->actingAs($user)->get($json['invoice_url'])->getContent());
    expect($text)->toContain('Gula Pasir 1 Kg');
});

// ---------------------------------------------------------------- kontrak controller -> view

test('controller prepares the fields expected by the invoice view', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user);

    Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($transaction) {
        $invoice = $data['invoice'];

        return $view === 'invoices.invoice'
            && $invoice->id === $transaction->id
            && $invoice->total === 82000
            && $invoice->items->count() === 2
            && $invoice->items[0]->product->name === 'Indomie Goreng'
            && $invoice->items[0]->quantity === 2
            && $invoice->items[0]->total === 7000
            && $invoice->items[1]->total === 75000;
    })->andReturnSelf();

    Pdf::shouldReceive('download')->once()->with("invoice_{$transaction->id}.pdf")->andReturn(response('PDF'));

    $this->actingAs($user)->get(route('admin.kasir.invoice', $transaction))->assertOk();
});

test('the invoice view exists in resources/views/invoices', function () {
    expect(view()->exists('invoices.invoice'))->toBeTrue()
        ->and(file_exists(resource_path('views/invoices/invoice.blade.php')))->toBeTrue();
});

// ---------------------------------------------------------------- integrasi di modul kasir

test('receipt page has a download invoice button', function () {
    $user = User::factory()->create();
    $transaction = makeTransaction($user);

    $this->actingAs($user)->get(route('admin.kasir.struk', $transaction))
        ->assertOk()
        ->assertSee('Unduh Invoice (PDF)')
        ->assertSee(route('admin.kasir.invoice', $transaction), false);
});

test('kasir javascript shows an invoice link after payment', function () {
    expect(file_get_contents(public_path('js/chasier.js')))
        ->toContain('result.invoice_url')
        ->toContain('Unduh invoice (PDF)');
});
