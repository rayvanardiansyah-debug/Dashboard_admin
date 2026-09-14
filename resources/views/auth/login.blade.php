<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Panel Admin</title>
    <!-- Bootstrap 5 CSS & Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background-color: #f4f6f9;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card-login {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
            width: 100%;
            max-width: 400px;
        }
    </style>
</head>
<body>

<div class="container p-3">
    <div class="card card-login mx-auto p-4 bg-white">
        <div class="text-center mb-4">
            <div class="bg-primary text-white d-inline-flex align-items-center justify-content-center rounded-circle mb-2" style="width: 50px; height: 50px;">
                <i class="bi bi-person-lock fs-3"></i>
            </div>
            <h4 class="fw-bold text-dark">Login Admin</h4>
            <p class="text-muted small">Silakan login untuk mengakses halaman dashboard admin</p>
        </div>

        <!-- Form Login yang mengarah ke Dashboard Admin -->
        <form action="{{ route('login.proses') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Email atau Username</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                    <input type="text" class="form-control" name="email" value="admin@example.com" placeholder="admin@example.com" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold text-secondary">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                    <input type="password" class="form-control" name="password" value="password" placeholder="••••••••" required>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="remember">
                    <label class="form-check-label small text-muted" for="remember">Ingat Saya</label>
                </div>
            </div>

            <!-- Tombol Login -->
            <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold rounded-3 mb-3 shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Masuk ke Dashboard
            </button>

            <div class="text-center">
                <a href="{{ route('home') }}" class="text-decoration-none small text-secondary">
                    <i class="bi bi-arrow-left"></i> Kembali ke Halaman Utama
                </a>
            </div>
        </form>
    </div>
</div>

</body>
</html>
