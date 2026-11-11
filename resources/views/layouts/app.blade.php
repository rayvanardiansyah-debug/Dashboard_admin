<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>
        <!-- Toastr (Visual Notification) & Echo Listener (BKPM Acara 32) -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" />
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>

        @auth
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                if (window.Echo) {
                    const handleOrderPlaced = (e) => {
                        console.log('Order Placed:', e);
                        if (typeof toastr !== 'undefined') {
                            toastr.options = {
                                "closeButton": true,
                                "progressBar": true,
                                "positionClass": "toast-top-right",
                                "timeOut": "7000"
                            };
                            toastr.success(`Order #${e.order_id} telah ditempatkan! Total: Rp ${Number(e.total).toLocaleString('id-ID')}`, 'Notifikasi');
                        } else {
                            alert(`Order #${e.order_id} telah ditempatkan! Total: Rp ${e.total}`);
                        }
                    };

                    window.Echo.private('user.{{ auth()->id() }}')
                        .listen('OrderPlaced', handleOrderPlaced)
                        .listen('.order.placed', handleOrderPlaced);
                }
            });
        </script>
        @endauth
    </body>
</html>
