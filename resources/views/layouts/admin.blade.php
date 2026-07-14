<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Super-Admin') — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-semibold" href="{{ route('admin.dashboard') }}">Super-Admin</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="adminNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('admin.dashboard')) active @endif" href="{{ route('admin.dashboard') }}">
                        Nadzorna ploča
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('admin.billing.*')) active @endif" href="{{ route('admin.billing.index') }}">
                        Naplata
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('admin.audit.*')) active @endif" href="{{ route('admin.audit.index') }}">
                        Audit log
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('admin.subscription-plans.*')) active @endif" href="{{ route('admin.subscription-plans.index') }}">
                        Paketi
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('admin.applications.*')) active @endif" href="{{ route('admin.applications.index') }}">
                        Aplikacije
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('admin.super-admins.*')) active @endif" href="{{ route('admin.super-admins.index') }}">
                        Super-admini
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('admin.two-factor.*')) active @endif" href="{{ route('admin.two-factor.index') }}">
                        Sigurnost
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link @if(request()->routeIs('admin.settings.*')) active @endif" href="{{ route('admin.settings.index') }}">
                        Postavke
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-3">
                @if(isset($applications) && $applications->isNotEmpty())
                    <div class="dropdown">
                        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            {{ $activeApplication?->name ?? 'Odaberi aplikaciju' }}
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach($applications as $application)
                                <li>
                                    <form method="POST" action="{{ route('admin.switch-app', $application) }}">
                                        @csrf
                                        <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center @if(($activeApplication?->id) === $application->id) active @endif">
                                            <span>{{ $application->name }}</span>
                                            @if(($activeApplication?->id) === $application->id)
                                                <span class="ms-2">&#10003;</span>
                                            @endif
                                        </button>
                                    </form>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <span class="text-white-50 small d-none d-md-inline">{{ auth()->user()->email }}</span>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Odjava</button>
                </form>
            </div>
        </div>
    </div>
</nav>

<main class="container-fluid px-4 py-4">
    @if(session('status'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="{{ asset('js/admin-dashboard.js') }}"></script>
@stack('scripts')
</body>
</html>
