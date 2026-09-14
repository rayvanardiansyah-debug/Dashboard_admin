<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Dashboard Admin')</title>
    <!-- Bootstrap 5 CSS & Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            min-height: 100vh;
            width: 240px;
            background: #212529;
        }
        .sidebar .nav-link {
            color: #adb5bd;
            padding: 10px 16px;
            border-radius: 6px;
            margin-bottom: 4px;
        }
        .sidebar .nav-link.active, .sidebar .nav-link:hover {
            color: #ffffff;
            background-color: #343a40;
        }
        .main-wrapper {
            flex-grow: 1;
        }
    </style>
</head>
<body class="d-flex">

    <!-- Sidebar Admin -->
    <aside class="sidebar p-3 d-flex flex-column flex-shrink-0 text-white">
        <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center mb-4 text-white text-decoration-none px-2">
            <i class="bi bi-speedometer2 fs-4 text-primary me-2"></i>
            <span class="fs-5 fw-bold">AdminPanel</span>
        </a>
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="{{ route('admin.dashboard') }}" class="nav-link active">
                    <i class="bi bi-grid-1x2 me-2"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="#" class="nav-link">
                    <i class="bi bi-people me-2"></i> Kelola User
                </a>
            </li>
            <li>
                <a href="#" class="nav-link">
                    <i class="bi bi-gear me-2"></i> Pengaturan
                </a>
            </li>
        </ul>
        <hr class="text-secondary">
        <div>
            <a href="{{ route('home') }}" class="btn btn-outline-danger w-100 btn-sm">
                <i class="bi bi-box-arrow-left me-1"></i> Logout / Ke Web
            </a>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="main-wrapper d-flex flex-column min-vh-100">
        <!-- Top Navbar Admin -->
        <nav class="navbar navbar-expand navbar-light bg-white border-bottom px-4 py-3">
            <div class="container-fluid p-0">
                <h5 class="mb-0 fw-bold">@yield('header_title', 'Dashboard')</h5>
                <div class="ms-auto d-flex align-items-center">
                    <span class="badge bg-success-subtle text-success me-3 px-3 py-2 border border-success-subtle">
                        <i class="bi bi-check-circle me-1"></i> Admin Online
                    </span>
                    <i class="bi bi-person-circle fs-3 text-secondary"></i>
                </div>
            </div>
        </nav>

        <!-- Main Body -->
        <main class="p-4">
            @yield('content')
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
