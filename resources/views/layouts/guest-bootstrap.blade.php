<!DOCTYPE html>
<html lang="hr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Prijava') — SuperSkyControl</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html { scrollbar-gutter: stable; }
        .login-page { padding-top: 4.5rem; }
        .login-logo { display: block; width: auto; height: 155px; margin: 0 auto 1rem; }
        .login-tagline { height: 3em; line-height: 1.5; overflow: hidden; }
        .login-links { margin-top: 1rem; }
        .login-links p { height: 1.5em; line-height: 1.5; margin: 0; }
        .login-card a { color: #0d6efd; }
        .login-card a:hover { color: #0a58ca; }
        .form-control:focus, .form-select:focus, .form-check-input:focus {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
</head>
<body class="bg-light">
<div class="container login-page">
    <div class="text-center mb-4">
        <img src="{{ asset('brand/superskycontrol-zelena.png') }}" alt="SuperSkyControl" class="login-logo">
        <p class="text-muted small mb-0 login-tagline">Platforma za upravljanje SaaS aplikacijama.</p>
    </div>
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card border-0 shadow-sm login-card">
                <div class="card-body p-4">
                    @yield('content')
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
