<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="{{ ($loginAppearance['theme'] ?? 'platform') === 'platform' ? '#212529' : '#0f6b64' }}">
    <title>{{ $loginAppearance['document_title'] ?? 'Prijava — Platforma' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primarna-zelena: #0f6b64;
            --primarna-tamna: #08403c;
            --svijetlo-zelena: #eef8f7;
            --tekst-tamni: #1a3d3a;
        }
        .login-platform { background: #f8f9fa; }
        .login-hr, .login-udruga {
            background: var(--svijetlo-zelena);
            font-family: 'Segoe UI', -apple-system, sans-serif;
            font-size: 13px;
            color: var(--tekst-tamni);
        }
        .guest-shell { min-height: 100vh; display: flex; align-items: center; }
        .kartica-kontejner {
            background: #fff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,.02);
            border: 1px solid rgba(0,0,0,.04);
        }
        .app-guest-lockup { display: block; max-width: 210px; width: 100%; height: auto; margin: 0 auto 1.15rem; }
        .text-tema { color: var(--primarna-zelena); }
        .login-hr .btn-primary, .login-udruga .btn-primary {
            background: var(--primarna-zelena);
            border-color: var(--primarna-zelena);
            border-radius: 9px;
        }
        .login-hr .btn-primary:hover, .login-udruga .btn-primary:hover {
            background: var(--primarna-tamna);
            border-color: var(--primarna-tamna);
        }
        .login-hr a, .login-udruga a { color: var(--primarna-zelena); }
        .small, small { font-size: 12px !important; }
    </style>
</head>
<body class="login-{{ $loginAppearance['theme'] ?? 'platform' }}">
@if(($loginAppearance['theme'] ?? 'platform') === 'platform')
    <div class="d-flex align-items-center min-vh-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5 col-lg-4">
                    <div class="text-center mb-4">
                        <h1 class="h4 fw-semibold">{{ $loginAppearance['brand'] ?? 'Platforma' }}</h1>
                        @if(!empty($loginAppearance['tagline']))
                            <p class="text-muted small mb-0">{{ $loginAppearance['tagline'] }}</p>
                        @endif
                    </div>
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            @yield('content')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="guest-shell">
        <div class="container py-5">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
@endif
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
