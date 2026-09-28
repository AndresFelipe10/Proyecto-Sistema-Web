<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Superadmin') | {{ config('app.name') }}</title>
    @include('partials.brand-head')

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }
        .navbar-superadmin {
            background-color: #1e293b;
            border-bottom: 1px solid #334155;
            height: 64px;
        }
        .superadmin-badge {
            background: #3b82f6;
            color: #ffffff;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.25rem 0.65rem;
            border-radius: 9999px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .app-layout {
            display: flex;
            min-height: calc(100vh - 64px);
        }
        .sidebar {
            width: 250px;
            background-color: #1e293b;
            border-right: 1px solid #334155;
            padding: 1.25rem 0.75rem;
            flex-shrink: 0;
        }
        .main-content {
            flex-grow: 1;
            padding: 1.75rem 2rem;
            background-color: #0f172a;
            overflow-y: auto;
        }
        .nav-link-super {
            display: flex;
            align-items: center;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #94a3b8;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
            font-weight: 500;
        }
        .nav-link-super:hover {
            background-color: #334155;
            color: #ffffff;
        }
        .nav-link-super.active {
            background-color: #2563eb;
            color: #ffffff;
            font-weight: 600;
        }
        .card-dark {
            background-color: #1e293b;
            border: 1px solid #334155;
            border-radius: 0.75rem;
            color: #f1f5f9;
        }
        .table-dark-custom {
            color: #f1f5f9;
            background-color: #1e293b;
        }
        .table-dark-custom th {
            background-color: #0f172a;
            color: #94a3b8;
            border-color: #334155;
        }
        .table-dark-custom td {
            border-color: #334155;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-superadmin sticky-top">
        <div class="container-fluid px-3 px-lg-4">
            <div class="d-flex align-items-center gap-3">
                <a class="navbar-brand m-0 text-decoration-none" href="{{ route('superadmin.dashboard') }}">
                    <x-brand size="sm" />
                </a>
                <span class="superadmin-badge"><i class="bi bi-shield-lock-fill me-1"></i> Plataforma</span>
            </div>

            <div class="d-flex align-items-center gap-3">
                <span class="text-slate-300 small">
                    <i class="bi bi-person-circle me-1 text-primary"></i> {{ auth()->user()->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                        <i class="bi bi-box-arrow-right me-1"></i> Salir
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="app-layout">
        <aside class="sidebar">
            <ul class="nav flex-column gap-1">
                <li class="nav-item">
                    <a class="nav-link-super {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}" href="{{ route('superadmin.dashboard') }}">
                        <i class="bi bi-speedometer2 me-2"></i> Panel
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link-super {{ request()->routeIs('superadmin.businesses.*') ? 'active' : '' }}" href="{{ route('superadmin.businesses.index') }}">
                        <i class="bi bi-buildings me-2"></i> Negocios
                    </a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            @if (session('status'))
                <div class="alert alert-success alert-dismissible fade show border-0 bg-success bg-opacity-25 text-success-emphasis mb-4" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('status') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('generated_password') || session('temp_password'))
                <div class="alert alert-warning border-0 bg-warning bg-opacity-25 text-warning-emphasis mb-4" role="alert">
                    <h5 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i> Contraseña generada (se muestra UNA sola vez):</h5>
                    <div class="p-2 bg-dark rounded font-monospace fs-5 text-warning select-all mt-2 user-select-all">
                        {{ session('generated_password') ?? session('temp_password') }}
                    </div>
                    <p class="small text-muted mb-0 mt-2">Copia y entrega esta clave segura al usuario. Se le pedirá cambiarla al iniciar sesión.</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
