<ul class="nav nav-pills gap-2 mb-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('superadmin.tenants') || request()->routeIs('superadmin.tenants.create') || request()->routeIs('superadmin.tenants.show') || request()->routeIs('superadmin.tenants.edit') ? 'active' : '' }}" href="{{ route('superadmin.tenants') }}">
            <i class="fas fa-building me-1"></i>Couriers
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('superadmin.tenants.customers') ? 'active' : '' }}" href="{{ route('superadmin.tenants.customers') }}">
            <i class="fas fa-users me-1"></i>Customers
        </a>
    </li>
</ul>
