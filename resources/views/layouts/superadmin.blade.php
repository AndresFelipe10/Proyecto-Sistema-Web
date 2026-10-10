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
            overflow-x: hidden;
        }
        .navbar-superadmin {
            background-color: #1e293b;
            border-bottom: 1px solid #334155;
            min-height: 64px;
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
            width: 100%;
        }

        /* Estructura Desktop (>= 992px) */
        @media (min-width: 992px) {
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
                width: calc(100% - 250px);
                max-width: calc(100% - 250px);
            }
        }

        /* Estructura Móvil y Tablets (< 992px) */
        @media (max-width: 991.98px) {
            .sidebar.offcanvas-lg {
                background-color: #1e293b !important;
                width: 280px;
                max-width: 85vw;
                border-right: 1px solid #334155;
            }
            .app-layout {
                flex-direction: column;
            }
            .main-content {
                width: 100%;
                max-width: 100%;
                padding: 1.25rem 1rem;
                flex-grow: 1;
            }
        }

        .nav-link-super {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            min-height: 44px;
            border-radius: 0.5rem;
            font-size: 0.95rem;
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
            white-space: nowrap;
        }
        .table-dark-custom td {
            border-color: #334155;
        }

        /* Botones táctiles ergonómicos en filas de tabla */
        .btn-action-table {
            width: 36px;
            height: 36px;
            min-width: 36px;
            min-height: 36px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.375rem;
        }

        /* Alertas de Alto Contraste para el tema oscuro de Superadmin */
        .main-content .alert {
            border-radius: 0.75rem;
            font-size: 0.95rem;
        }
        .main-content .alert-success {
            background-color: #064e3b !important;
            border: 1px solid #10b981 !important;
            color: #ffffff !important;
        }
        .main-content .alert-danger {
            background-color: #7f1d1d !important;
            border: 1px solid #ef4444 !important;
            color: #ffffff !important;
        }
        .main-content .alert-warning {
            background-color: #78350f !important;
            border: 1px solid #f59e0b !important;
            color: #ffffff !important;
        }
        .main-content .alert-info {
            background-color: #1e3a8a !important;
            border: 1px solid #3b82f6 !important;
            color: #ffffff !important;
        }
        .main-content .alert .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.85;
        }
        .main-content .alert .btn-close:hover {
            opacity: 1;
        }

        /* Salvaguarda para iconos de paginación SVG */
        .pagination svg {
            width: 1em !important;
            height: 1em !important;
            max-width: 16px !important;
            max-height: 16px !important;
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-superadmin sticky-top">
        <div class="container-fluid px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <!-- Botón Hamburguesa Offcanvas (Visible en < lg) -->
                <button class="btn btn-outline-secondary d-lg-none d-flex align-items-center justify-content-center border-secondary border-opacity-50 text-light rounded-3 p-0" 
                        type="button" 
                        data-bs-toggle="offcanvas" 
                        data-bs-target="#superadminSidebar" 
                        aria-controls="superadminSidebar" 
                        aria-label="Abrir menú de navegación"
                        style="width: 44px; height: 44px; min-width: 44px; min-height: 44px;">
                    <i class="bi bi-list fs-3"></i>
                </button>

                <a class="navbar-brand m-0 text-decoration-none d-flex align-items-center" href="{{ route('superadmin.dashboard') }}">
                    <x-brand size="sm" light />
                </a>
                <span class="superadmin-badge d-none d-sm-inline-flex align-items-center">
                    <i class="bi bi-shield-lock-fill me-1"></i> Plataforma
                </span>
            </div>

            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <span class="text-slate-300 small d-none d-md-inline-block text-truncate" style="max-width: 160px;">
                    <i class="bi bi-person-circle me-1 text-primary"></i> {{ auth()->user()->name }}
                </span>
                <form method="POST" action="{{ route('logout') }}" class="d-inline m-0">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3 d-inline-flex align-items-center gap-1" style="min-height: 38px;">
                        <i class="bi bi-box-arrow-right"></i> <span class="d-none d-sm-inline">Salir</span>
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <div class="app-layout">
        <!-- Sidebar adaptable: Fijo en Desktop / Offcanvas en Móvil (< lg) -->
        <aside class="sidebar offcanvas-lg offcanvas-start text-white" id="superadminSidebar" tabindex="-1" aria-labelledby="superadminSidebarLabel">
            <div class="offcanvas-header d-lg-none border-bottom border-secondary border-opacity-25 px-3 py-3">
                <div class="d-flex align-items-center gap-2" id="superadminSidebarLabel">
                    <x-brand size="sm" light />
                    <span class="superadmin-badge"><i class="bi bi-shield-lock-fill me-1"></i> Plataforma</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#superadminSidebar" aria-label="Cerrar"></button>
            </div>
            <div class="offcanvas-body p-3 p-lg-0 d-flex flex-column justify-content-between h-100">
                <ul class="nav flex-column gap-1 w-100">
                    <li class="nav-item">
                        <a class="nav-link-super {{ request()->routeIs('superadmin.dashboard') ? 'active' : '' }}" href="{{ route('superadmin.dashboard') }}">
                            <i class="bi bi-speedometer2 me-2 fs-5"></i> Panel
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link-super {{ request()->routeIs('superadmin.businesses.*') ? 'active' : '' }}" href="{{ route('superadmin.businesses.index') }}">
                            <i class="bi bi-buildings me-2 fs-5"></i> Negocios
                        </a>
                    </li>
                </ul>

                <!-- Identificador de usuario en menú móvil -->
                <div class="d-lg-none mt-auto pt-3 border-top border-secondary border-opacity-25">
                    <div class="d-flex align-items-center gap-2 px-2 text-slate-300 small">
                        <i class="bi bi-person-circle fs-5 text-primary"></i>
                        <div class="text-truncate">
                            <div class="fw-semibold text-white text-truncate">{{ auth()->user()->name }}</div>
                            <div class="text-secondary small text-truncate">{{ auth()->user()->email }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <main class="main-content">
            @if (session('status') || session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4 d-flex align-items-center justify-content-between" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                        <span>{{ session('status') ?? session('success') }}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('error'))
                <div class="alert alert-danger alert-dismissible fade show mb-4 d-flex align-items-center justify-content-between" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-octagon-fill me-2 fs-5"></i>
                        <span>{{ session('error') }}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('info'))
                <div class="alert alert-info alert-dismissible fade show mb-4 d-flex align-items-center justify-content-between" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                        <span>{{ session('info') }}</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if (session('generated_password') || session('temp_password'))
                <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0 text-white"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i> Contraseña generada (se muestra UNA sola vez):</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                    <div class="p-2 bg-dark rounded font-monospace fs-5 text-warning select-all mt-2 user-select-all border border-secondary">
                        {{ session('generated_password') ?? session('temp_password') }}
                    </div>
                    <p class="small text-light text-opacity-75 mb-0 mt-2">Copia y entrega esta clave segura al usuario. Se le pedirá cambiarla al iniciar sesión.</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
