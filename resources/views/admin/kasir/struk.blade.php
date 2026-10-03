@php
    $rp = fn ($n) => 'Rp '.number_format((int) $n, 0, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk {{ $transaction->transaction_number }}</title>
    <style>
        /* Lebar kertas: {{ $width }} mm (ubah lewat KASIR_PAPER_WIDTH di .env atau ?w=58) */
        @page { size: {{ $width }}mm auto; margin: 0; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: "Courier New", Courier, monospace;
            font-size: {{ $width === 58 ? '11px' : '12px' }};
            line-height: 1.35;
            color: #000;
            background: #e9ecef;
        }

        .receipt {
            width: {{ $width }}mm;
            padding: 4mm {{ $width === 58 ? '2mm' : '3mm' }} 6mm;
            margin: 0 auto;
            background: #fff;
        }

        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .store { font-size: 1.25em; font-weight: 700; }
        .muted { font-size: .9em; }
        .line { border-top: 1px dashed #000; margin: 5px 0; }
        .row { display: flex; justify-content: space-between; gap: 6px; }
        .row > span:last-child { text-align: right; white-space: nowrap; }
        .item-name { word-break: break-word; }
        .total { font-size: 1.15em; font-weight: 700; }

        /* Tombol hanya tampil di layar, tidak ikut tercetak */
        .toolbar { text-align: center; padding: 12px; font-family: Arial, sans-serif; }
        .toolbar button, .toolbar a {
            display: inline-block; padding: 8px 16px; margin: 0 4px;
            border: 0; border-radius: 6px; background: #0d6efd; color: #fff;
            font-size: 13px; text-decoration: none; cursor: pointer;
        }
        .toolbar a.secondary { background: #6c757d; }

        @media print {
            body { background: #fff; }
            .receipt { margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="toolbar no-print">
    <button type="button" onclick="window.print()">Cetak Struk</button>
    <a class="secondary" href="{{ route('admin.kasir') }}">Kembali ke Kasir</a>
</div>

<div class="receipt">
    <div class="center">
        <div class="store">{{ config('kasir.store_name') }}</div>
        <div class="muted">{{ config('kasir.store_address') }}</div>
        <div class="muted">Telp. {{ config('kasir.store_phone') }}</div>
    </div>

    <div class="line"></div>

    <div class="row"><span>No</span><span>{{ $transaction->transaction_number }}</span></div>
    <div class="row"><span>Tanggal</span><span>{{ $transaction->created_at->format('d/m/Y H:i') }}</span></div>
    <div class="row"><span>Kasir</span><span>{{ $transaction->user?->name ?? '-' }}</span></div>
    <div class="row">
        <span>Pelanggan</span>
        <span>{{ $transaction->customer_type }}{{ $transaction->customer_phone ? ' / '.$transaction->customer_phone : '' }}</span>
    </div>

    <div class="line"></div>

    @foreach ($transaction->details as $detail)
        <div class="item-name">{{ $detail->product_name }}</div>
        <div class="row">
            <span>{{ $detail->qty }} x {{ number_format($detail->price, 0, ',', '.') }}</span>
            <span>{{ number_format($detail->subtotal, 0, ',', '.') }}</span>
        </div>
    @endforeach

    <div class="line"></div>

    <div class="row"><span>Subtotal</span><span>{{ $rp($transaction->subtotal) }}</span></div>
    @if ($transaction->discount > 0)
        <div class="row">
            <span>Diskon{{ $transaction->discount_percent > 0 ? ' ('.rtrim(rtrim(number_format($transaction->discount_percent, 2, ',', '.'), '0'), ',').'%)' : '' }}</span>
            <span>-{{ $rp($transaction->discount) }}</span>
        </div>
    @endif
    @if ($transaction->tax > 0)
        <div class="row"><span>Pajak / PPN</span><span>{{ $rp($transaction->tax) }}</span></div>
    @endif
    @if ($transaction->other_fee > 0)
        <div class="row"><span>Biaya Lain</span><span>{{ $rp($transaction->other_fee) }}</span></div>
    @endif

    <div class="line"></div>

    <div class="row total"><span>TOTAL</span><span>{{ $rp($transaction->grand_total) }}</span></div>
    <div class="row"><span>Bayar ({{ $transaction->payment_method }})</span><span>{{ $rp($transaction->paid_amount) }}</span></div>
    <div class="row bold"><span>Kembali</span><span>{{ $rp($transaction->change_amount) }}</span></div>

    <div class="line"></div>

    <div class="center muted">
        Terima kasih atas kunjungan Anda<br>
        Barang yang sudah dibeli tidak dapat ditukar
    </div>
</div>

@if ($autoPrint)
    <script>
        window.addEventListener('load', function () {
            window.focus();
            window.print();
        });
    </script>
@endif

</body>
</html>
