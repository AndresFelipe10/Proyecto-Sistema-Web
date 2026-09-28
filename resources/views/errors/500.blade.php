<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Error del servidor | {{ config('app.name') }}</title>
    @include('partials.brand-head')
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-card {
            text-align: center;
            max-width: 480px;
            padding: 3rem 2rem;
        }
        .error-code {
            font-size: 5rem;
            font-weight: 700;
            color: #dc2626;
            line-height: 1;
        }
        .error-icon {
            font-size: 3.5rem;
            color: #dc2626;
            margin-bottom: 1rem;
        }
        .error-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin-top: 0.5rem;
        }
        .error-message {
            color: #64748b;
            margin-top: 0.75rem;
            font-size: 1rem;
        }
        .btn-back {
            margin-top: 1.5rem;
            background-color: #4f46e5;
            border-color: #4f46e5;
            border-radius: 0.75rem;
            padding: 0.6rem 1.75rem;
            font-weight: 600;
        }
        .btn-back:hover {
            background-color: #4338ca;
            border-color: #4338ca;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-icon"><i class="bi bi-exclamation-triangle-fill"></i></div>
        <div class="error-code">500</div>
        <h1 class="error-title">Error del servidor</h1>
        <p class="error-message">Algo salió mal en nuestro servidor. Por favor, intenta de nuevo más tarde. Si el problema persiste, contacta al soporte.</p>
        <a href="{{ url('/') }}" class="btn btn-primary btn-back">
            <i class="bi bi-arrow-left me-1"></i> Volver al inicio
        </a>
    </div>
</body>
</html>
