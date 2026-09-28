<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@hasSection('title')@yield('title') | @endif{{ config('app.name') }}</title>
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
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
        }
        .navbar-custom {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
            height: 62px;
            z-index: 1030;
        }
        .navbar-brand {
            font-weight: 700;
            color: #4f46e5 !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.15rem;
        }
        .card-custom {
            background: #ffffff;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }
        .user-pill {
            background: #f1f5f9;
            padding: 0.35rem 0.85rem;
            border-radius: 2rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
        }
        .tenant-selector {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            border-radius: 2rem;
            padding: 0.35rem 0.95rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        /* Layout con Sidebar */
        .app-layout {
            display: flex;
            min-height: calc(100vh - 62px);
        }

        @media (min-width: 992px) {
            .sidebar-desktop {
                position: fixed;
                top: 62px;
                bottom: 0;
                left: 0;
                width: 250px;
                background-color: #ffffff;
                border-right: 1px solid #e2e8f0;
                overflow-y: auto;
                padding: 1.25rem 0.75rem;
                z-index: 1020;
            }
            .main-content {
                margin-left: 250px;
                width: calc(100% - 250px);
                min-height: calc(100vh - 62px);
            }
        }

        @media (max-width: 991.98px) {
            .sidebar-desktop {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
                min-height: calc(100vh - 62px);
            }
        }

        /* Estilos del Sidebar */
        .sidebar-nav-container .nav-link {
            display: flex;
            align-items: center;
            padding: 0.65rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.9rem;
            color: #64748b;
            text-decoration: none;
            transition: all 0.15s ease-in-out;
        }

        .sidebar-nav-container .nav-link:hover {
            background-color: #f1f5f9;
            color: #4f46e5 !important;
        }

        .sidebar-nav-container .nav-link.active {
            background-color: #eef2ff;
            color: #4f46e5 !important;
            font-weight: 600;
        }

        .sidebar-nav-container .nav-link.active i {
            color: #4f46e5 !important;
        }

        .sidebar-nav-container .nav-link i {
            font-size: 1.15rem;
            width: 1.5rem;
            text-align: center;
            flex-shrink: 0;
        }
    </style>
</head>
<body>
    {{-- Navbar Superior --}}
    <nav class="navbar navbar-custom sticky-top">
        <div class="container-fluid px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2">
                {{-- Botón hamburguesa para móvil/tablet que abre el Offcanvas --}}
                <button class="btn btn-outline-secondary d-lg-none p-1 border-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas" aria-controls="sidebarOffcanvas" aria-label="Abrir menú de navegación">
                    <i class="bi bi-list fs-3"></i>
                </button>

                <a class="navbar-brand m-0 text-decoration-none" href="{{ route('dashboard') }}">
                    <x-brand size="sm" />
                </a>
            </div>

            {{-- Elementos de la derecha: Badge del emprendimiento activo, usuario y botón Salir --}}
            <div class="d-flex align-items-center gap-2 gap-md-3">
                @if (isset($currentBusiness))
                    <span class="tenant-selector">
                        <i class="bi bi-shop text-primary"></i> <span class="d-none d-sm-inline">{{ $currentBusiness->name }}</span>
                    </span>
                @endif

                <span class="user-pill">
                    <i class="bi bi-person-circle me-1 text-primary"></i> <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
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

    {{-- Offcanvas para pantallas móviles y tablets (< 992px) --}}
    <div class="offcanvas offcanvas-start" tabindex="-1" id="sidebarOffcanvas" aria-labelledby="sidebarOffcanvasLabel">
        <div class="offcanvas-header border-bottom">
            <h5 class="offcanvas-title fw-bold text-primary d-flex align-items-center gap-2" id="sidebarOffcanvasLabel">
                <i class="bi bi-box-seam-fill"></i> Módulos
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
        </div>
        <div class="offcanvas-body p-3" style="overflow-y: auto;">
            @include('layouts.sidebar')
        </div>
    </div>

    {{-- Contenedor principal con Sidebar de escritorio y contenido --}}
    <div class="app-layout">
        {{-- Sidebar fijo para pantallas de escritorio (>= 992px) --}}
        <aside class="sidebar-desktop d-none d-lg-block">
            @include('layouts.sidebar')
        </aside>

        {{-- Área de contenido principal --}}
        <div class="main-content flex-grow-1">
            <main class="container py-4">
                @if (session('status'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> {{ session('status') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('info'))
                    <div class="alert alert-info alert-dismissible fade show rounded-3" role="alert">
                        <i class="bi bi-info-circle-fill me-2"></i> {{ session('info') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle (incluye Popper para dropdowns y offcanvas) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
