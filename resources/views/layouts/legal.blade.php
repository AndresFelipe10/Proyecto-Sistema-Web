<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ config('app.name', 'PuntoStock') }}</title>
    @include('partials.brand-head')

    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --ps-tinta: #1B2A49;
            --ps-azafran: #E8A317;
            --ps-hueso: #F6F4EF;
        }
        html {
            scroll-behavior: smooth;
        }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--ps-hueso);
            color: #334155;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .navbar-legal {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
        }
        .legal-card {
            background-color: #ffffff;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
        }
        .toc-card {
            position: sticky;
            top: 2rem;
            background-color: #ffffff;
            border-radius: 1rem;
            border: 1px solid #e2e8f0;
        }
        .toc-link {
            display: block;
            padding: 0.5rem 0.75rem;
            color: #475569;
            text-decoration: none;
            font-size: 0.875rem;
            border-radius: 0.5rem;
            transition: all 0.2s ease;
        }
        .toc-link:hover {
            background-color: #f1f5f9;
            color: var(--ps-tinta);
            font-weight: 600;
            padding-left: 1rem;
        }
        .legal-header {
            background: linear-gradient(135deg, var(--ps-tinta) 0%, #243b68 100%);
            color: #ffffff;
            border-radius: 1rem;
            padding: 2.5rem 2rem;
            margin-bottom: 2rem;
        }
        .legal-badge {
            background-color: rgba(232, 163, 23, 0.2);
            color: #fbd38d;
            border: 1px solid rgba(232, 163, 23, 0.4);
            font-size: 0.8rem;
            font-weight: 600;
            padding: 0.35rem 0.8rem;
            border-radius: 2rem;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .clause-box {
            background-color: #f8fafc;
            border-left: 4px solid var(--ps-azafran);
            border-radius: 0 0.5rem 0.5rem 0;
            padding: 1.25rem 1.5rem;
            margin: 1.5rem 0;
        }
        .section-title {
            color: var(--ps-tinta);
            font-weight: 700;
            scroll-margin-top: 2rem;
        }
        .legal-footer {
            margin-top: auto;
            background-color: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 1.5rem 0;
            color: #64748b;
            font-size: 0.875rem;
        }
        .nav-legal-pill {
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            padding: 0.4rem 1rem;
            border-radius: 2rem;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-legal-pill:hover, .nav-legal-pill.active {
            background-color: var(--ps-tinta);
            color: #ffffff;
        }
    </style>
</head>
<body>
    <!-- Navbar Legal -->
    <nav class="navbar navbar-expand-lg navbar-legal py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('login') }}">
                <x-brand size="sm" />
            </a>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('legal.terms') }}" class="nav-legal-pill {{ request()->routeIs('legal.terms') ? 'active' : '' }}">
                    <i class="bi bi-file-earmark-text me-1"></i> Términos y Condiciones
                </a>
                <a href="{{ route('legal.privacy') }}" class="nav-legal-pill {{ request()->routeIs('legal.privacy') ? 'active' : '' }}">
                    <i class="bi bi-shield-lock me-1"></i> Privacidad y Cookies
                </a>
                <a href="{{ route('login') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3 ms-2">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container py-4 py-md-5">
        @yield('content')
    </main>

    <!-- Footer Legal -->
    <footer class="legal-footer">
        <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 text-center text-md-start">
            <div>
                <strong>PuntoStock SaaS</strong> &copy; {{ date('Y') }}. Todos los derechos reservados. Cali, Colombia.
            </div>
            <div class="d-flex gap-3 small">
                <a href="{{ route('legal.terms') }}" class="text-secondary text-decoration-none">Términos del Servicio</a>
                <span class="text-muted">•</span>
                <a href="{{ route('legal.privacy') }}" class="text-secondary text-decoration-none">Política de Privacidad</a>
                <span class="text-muted">•</span>
                <a href="https://wa.me/{{ config('app.support_whatsapp', '573163765939') }}" target="_blank" rel="noopener noreferrer" class="text-secondary text-decoration-none">
                    <i class="bi bi-whatsapp me-1 text-success"></i>Soporte Legal
                </a>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
