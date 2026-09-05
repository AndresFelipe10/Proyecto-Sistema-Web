<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Panel') — {{ config('app.name') }}</title>

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
        }
        .navbar-brand {
            font-weight: 700;
            color: #4f46e5 !important;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid px-4">
            <a class="navbar-brand" href="{{ route('dashboard') }}">
                <i class="bi bi-box-seam-fill"></i> {{ config('app.name') }}
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->routeIs('dashboard') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('dashboard') }}">
                            <i class="bi bi-speedometer2 me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->routeIs('products.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('products.index') }}">
                            <i class="bi bi-boxes me-1"></i> Productos
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->routeIs('inventory.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('inventory.index') }}">
                            <i class="bi bi-arrow-left-right me-1"></i> Inventario
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->routeIs('sales.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('sales.index') }}">
                            <i class="bi bi-cart-check me-1"></i> Ventas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->routeIs('customers.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('customers.index') }}">
                            <i class="bi bi-people-fill me-1"></i> Clientes
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->routeIs('suppliers.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('suppliers.index') }}">
                            <i class="bi bi-truck me-1"></i> Proveedores
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->routeIs('categories.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('categories.index') }}">
                            <i class="bi bi-tags me-1"></i> Categorías
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link fw-semibold {{ request()->routeIs('businesses.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('businesses.index') }}">
                            <i class="bi bi-buildings me-1"></i> Emprendimientos
                        </a>
                    </li>
                    @if (auth()->check() && auth()->user()->isCurrentAdmin())
                        <li class="nav-item">
                            <a class="nav-link fw-semibold {{ request()->routeIs('users.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('users.index') }}">
                                <i class="bi bi-shield-person me-1"></i> Equipo
                            </a>
                        </li>
                    @endif
                </ul>

                <div class="d-flex align-items-center gap-3 flex-wrap">
                    @if (isset($currentBusiness))
                        <div class="dropdown">
                            <button class="btn tenant-selector dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-shop text-primary"></i> {{ $currentBusiness->name }}
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 py-2">
                                <li class="dropdown-header small text-uppercase fw-bold text-muted">Cambiar Emprendimiento</li>
                                @if (isset($userBusinesses))
                                    @foreach ($userBusinesses as $biz)
                                        <li>
                                            <form method="POST" action="{{ route('businesses.switch', $biz) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center py-2 {{ $biz->id === $currentBusiness->id ? 'active fw-bold' : '' }}">
                                                    <span>{{ $biz->name }}</span>
                                                    @if ($biz->id === $currentBusiness->id)
                                                        <i class="bi bi-check2"></i>
                                                    @endif
                                                </button>
                                            </form>
                                        </li>
                                    @endforeach
                                @endif
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item small text-primary fw-semibold" href="{{ route('businesses.create') }}">
                                        <i class="bi bi-plus-circle me-1"></i> Registrar otro negocio
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item small text-secondary" href="{{ route('businesses.index') }}">
                                        <i class="bi bi-gear me-1"></i> Administrar todos
                                    </a>
                                </li>
                            </ul>
                        </div>
                    @endif

                    <span class="user-pill">
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
        </div>
    </nav>

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

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
