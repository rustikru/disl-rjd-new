<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 — Страница не найдена</title>
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background-color: #f4f6f9;
            /* Мягкий фон вашего дашборда */
            color: #2d3748;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }

        .container {
            padding: 20px;
        }

        .error-code {
            font-size: 140px;
            font-weight: 700;
            line-height: 1;
            color: #2b4c7e;
            /* Спокойный, глубокий приглушенный синий */
            letter-spacing: -3px;
            margin-bottom: 8px;
            opacity: 0.85;
        }

        .error-title {
            font-size: 20px;
            font-weight: 500;
            color: #1a202c;
            margin-bottom: 10px;
        }

        .error-message {
            font-size: 14px;
            color: #718096;
            /* Приглушенный серый для текста */
            margin-bottom: 28px;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            font-size: 13px;
            font-weight: 500;
            color: #ffffff;
            background-color: #2b4c7e;
            /* Цвет кнопки в тон цифрам */
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.15s ease;
        }

        .btn:hover {
            background-color: #1e3a61;
            /* Чуть темнее при наведении */
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="error-code">404</div>
        <h1 class="error-title">Страница не найдена</h1>
        <p class="error-message">Запрашиваемый адрес не существует или был перемещен.</p>
        <a href="/" class="btn">Вернуться на главную</a>
    </div>
</body>

</html>