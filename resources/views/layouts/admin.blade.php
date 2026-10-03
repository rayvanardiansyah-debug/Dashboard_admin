<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin Dashboard')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
        <nav class="navbar navbar-expand navbar-light bg-white border-bottom px-4 py-3">
            <h5 class="mb-0 fw-bold">@yield('header_title', 'Dashboard')</h5>
        </nav>
        <main class="p-4">
            @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
