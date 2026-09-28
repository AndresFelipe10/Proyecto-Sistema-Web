<div class="sidebar-nav-container">
    <ul class="nav flex-column gap-1">
        {{-- 1. Dashboard --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('dashboard') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('dashboard') }}">
                <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>
        </li>

        {{-- 2. Ventas --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('sales.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('sales.index') }}">
                <i class="bi bi-cart-check me-2"></i> Ventas
            </a>
        </li>

        {{-- 3. Inventario --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('inventory.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('inventory.index') }}">
                <i class="bi bi-arrow-left-right me-2"></i> Inventario
            </a>
        </li>

        {{-- 4. Productos --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('products.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('products.index') }}">
                <i class="bi bi-boxes me-2"></i> Productos
            </a>
        </li>

        {{-- 5. Categorías --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('categories.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('categories.index') }}">
                <i class="bi bi-tags me-2"></i> Categorías
            </a>
        </li>

        {{-- 6. Clientes --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('customers.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('customers.index') }}">
                <i class="bi bi-people-fill me-2"></i> Clientes
            </a>
        </li>

        {{-- 7. Proveedores --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('suppliers.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('suppliers.index') }}">
                <i class="bi bi-truck me-2"></i> Proveedores
            </a>
        </li>

        {{-- 8. Gastos (Solo Administrador) --}}
        @if (auth()->check() && auth()->user()->isCurrentAdmin())
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('expenses.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('expenses.index') }}">
                    <i class="bi bi-receipt-cutoff me-2"></i> Gastos
                </a>
            </li>
        @endif

        {{-- 9. Reportes (Solo Administrador) --}}
        @if (auth()->check() && auth()->user()->isCurrentAdmin())
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('reports.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('reports.index') }}">
                    <i class="bi bi-file-earmark-bar-graph me-2"></i> Reportes
                </a>
            </li>
        @endif

        {{-- 9. Asistente IA --}}
        <li class="nav-item">
            <a class="nav-link fw-semibold {{ request()->routeIs('ai.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('ai.index') }}">
                <i class="bi bi-stars text-primary me-2"></i> Asistente IA
            </a>
        </li>

        {{-- 10. Mi Negocio (Solo Administrador) --}}
        @if (auth()->check() && auth()->user()->isCurrentAdmin())
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('businesses.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('businesses.edit') }}">
                    <i class="bi bi-buildings me-2"></i> Mi negocio
                </a>
            </li>
        @endif

        {{-- 11. Equipo (Solo Administrador) --}}
        @if (auth()->check() && auth()->user()->isCurrentAdmin())
            <li class="nav-item">
                <a class="nav-link fw-semibold {{ request()->routeIs('users.*') ? 'active text-primary' : 'text-secondary' }}" href="{{ route('users.index') }}">
                    <i class="bi bi-person-badge me-2"></i> Equipo
                </a>
            </li>
        @endif
    </ul>
</div>
