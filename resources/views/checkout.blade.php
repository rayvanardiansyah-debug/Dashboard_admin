<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Demo Checkout Produk Digital (Midtrans)</title>
</head>
<body>
    <h1>Produk Digital - Rp 50.000</h1>
    <button id="pay-button">Bayar Sekarang</button>

    <script src="{{ config('midtrans.isProduction') ? 'https://app.midtrans.com/snap/snap.js' : 'https://app.sandbox.midtrans.com/snap/snap.js' }}"
            data-client-key="{{ config('midtrans.clientKey') }}"></script>
    <script>
        document.getElementById('pay-button').addEventListener('click', function () {
            fetch('/checkout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                window.snap.pay(data.snap_token, {
                    onSuccess: function (result) { alert('Pembayaran sukses!'); },
                    onPending: function (result) { alert('Menunggu pembayaran...'); },
                    onError:   function (result) { alert('Pembayaran gagal!'); },
                    onClose:   function () { alert('Anda menutup popup pembayaran.'); }
                });
            });
        });
    </script>
</body>
</html>
