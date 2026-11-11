<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin Dashboard')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">
    @vite(['resources/js/app.js'])
    <style>
        .sidebar { min-height: 100vh; width: 240px; background: #212529; }
        .sidebar .nav-link { color: #adb5bd; padding: 10px 16px; border-radius: 6px; }
        .sidebar .nav-link.active, .sidebar .nav-link:hover { color: #fff; background-color: #343a40; }
        .main-wrapper { flex-grow: 1; }
    </style>
</head>
<body class="d-flex">

    <aside class="sidebar p-3 d-flex flex-column text-white">
        <a href="{{ route('admin.dashboard') }}" class="text-white text-decoration-none fs-5 fw-bold mb-4 px-2">
            <i class="bi bi-speedometer2 text-primary me-2"></i> AdminPanel
        </a>
        <ul class="nav nav-pills flex-column mb-auto">
            <li>
                <a href="{{ route('admin.dashboard') }}" class="nav-link active">
                    <i class="bi bi-box-seam me-2"></i> Kelola Produk
                </a>
            </li>
            <li>
                <a href="{{ route('admin.kasir') }}" class="nav-link">
                    <i class="bi bi-cash-coin me-2"></i> Kasir
                </a>
            </li>
        </ul>
        <hr class="text-secondary">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button type="submit" class="btn btn-outline-danger w-100 btn-sm">
                <i class="bi bi-box-arrow-left me-1"></i> Logout
            </button>
        </form>
    </aside>

    <div class="main-wrapper d-flex flex-column min-vh-100 bg-light">
        <nav class="navbar navbar-expand navbar-light bg-white border-bottom px-4 py-3 justify-content-between">
            <h5 class="mb-0 fw-bold">@yield('header_title', 'Dashboard')</h5>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="btnTestBroadcast" onclick="testOrderBroadcast()">
                    <i class="bi bi-broadcast me-1"></i> Uji Broadcast (Reverb)
                </button>
            </div>
        </nav>
        <main class="p-4">
            @yield('content')
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

    @auth
    <script>
        function testOrderBroadcast() {
            const btn = document.getElementById('btnTestBroadcast');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengirim...';

            fetch('{{ route('admin.order.trigger') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    total: Math.floor(Math.random() * (500000 - 50000) + 50000)
                })
            })
            .then(res => res.json())
            .then(data => {
                console.log('Order trigger response:', data);
            })
            .catch(err => {
                console.error('Order trigger error:', err);
                if (typeof toastr !== 'undefined') {
                    toastr.error('Gagal mengirim event broadcast', 'Error');
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-broadcast me-1"></i> Uji Broadcast (Reverb)';
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (window.Echo) {
                const handleOrderNotification = (e) => {
                    console.log('Order Placed:', e);
                    if (typeof toastr !== 'undefined') {
                        toastr.options = {
                            "closeButton": true,
                            "progressBar": true,
                            "positionClass": "toast-top-right",
                            "timeOut": "7000"
                        };
                        toastr.success(`Order #${e.order_id} telah ditempatkan! Total: Rp ${Number(e.total).toLocaleString('id-ID')}`, 'Notifikasi Transaksi');
                    } else {
                        alert(`Order #${e.order_id} telah ditempatkan! Total: Rp ${e.total}`);
                    }
                };

                window.Echo.private('user.{{ auth()->id() }}')
                    .listen('OrderPlaced', handleOrderNotification)
                    .listen('.order.placed', handleOrderNotification);
            }
        });
    </script>
    @endauth
</body>
</html>
