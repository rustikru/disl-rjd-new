<?php $basePath = $basePath ?? ''; ?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Страница не найдена</title>
    <link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($basePath) ?>/assets/img/favicon.ico">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #f4f3f8;
            color: #1c2128;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .container { padding: 20px; }

        .error-code {
            font-size: 140px;
            font-weight: 700;
            line-height: 1;
            color: #4f328e;
            letter-spacing: -4px;
            margin-bottom: 8px;
            opacity: 0.15;
        }

        .error-title {
            font-size: 20px;
            font-weight: 600;
            color: #1c2128;
            margin-bottom: 8px;
            margin-top: -20px;
        }

        .error-message {
            font-size: 14px;
            color: #7c7e86;
            margin-bottom: 28px;
        }

        .btn {
            display: inline-block;
            padding: 10px 22px;
            font-size: 13px;
            font-weight: 600;
            color: #fff;
            background: #4f328e;
            border-radius: 7px;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn:hover { background: #3a226b; }
    </style>
</head>

<body>
    <div class="container">
        <div class="error-code">404</div>
        <h1 class="error-title">Страница не найдена</h1>
        <p class="error-message">Запрашиваемый адрес не существует или был перемещён.</p>
        <a href="<?= htmlspecialchars($basePath) ?>/" class="btn">Вернуться на главную</a>
    </div>
</body>

</html>
