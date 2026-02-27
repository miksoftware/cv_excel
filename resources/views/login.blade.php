<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - Limpiador de Duplicados</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f0f2f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }
        h1 { color: #333; margin-bottom: 8px; font-size: 1.4rem; }
        p.desc { color: #666; margin-bottom: 24px; font-size: 0.9rem; }
        .lock-icon { font-size: 3rem; margin-bottom: 16px; }
        input[type="password"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            margin-bottom: 16px;
            text-align: center;
            letter-spacing: 4px;
            transition: border-color 0.3s;
        }
        input[type="password"]:focus {
            outline: none;
            border-color: #4a90d9;
        }
        button {
            background: #4a90d9;
            color: white;
            border: none;
            padding: 12px 32px;
            border-radius: 8px;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.3s;
            width: 100%;
        }
        button:hover { background: #3a7bc8; }
        .error {
            background: #fee;
            color: #c33;
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 16px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="lock-icon">🔒</div>
        <h1>Acceso Protegido</h1>
        <p class="desc">Ingresa la clave para acceder al sistema.</p>

        @if ($errors->any())
            <div class="error">
                @foreach ($errors->all() as $error)
                    {{ $error }}
                @endforeach
            </div>
        @endif

        <form action="{{ route('login.submit') }}" method="POST">
            @csrf
            <input type="password" name="password" placeholder="••••••••" autofocus>
            <button type="submit">Ingresar</button>
        </form>
    </div>
</body>
</html>
