<?php
// Simple login page - form submits directly to Roundcube
// JavaScript fetches CSRF token from Roundcube in user's browser
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - sutulaya.lol</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --primary-light: #818cf8;
            --accent: #06b6d4;
            --success: #10b981;
            --error: #ef4444;
            --bg-dark: #0f172a;
            --bg-card: rgba(255, 255, 255, 0.05);
            --text: #f8fafc;
            --text-muted: #94a3b8;
            --border: rgba(255, 255, 255, 0.1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--bg-dark);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow: hidden;
            position: relative;
        }

        .bg-gradient {
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 20%, rgba(99, 102, 241, 0.3) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 80%, rgba(6, 182, 212, 0.3) 0%, transparent 50%),
                radial-gradient(ellipse at 40% 60%, rgba(139, 92, 246, 0.2) 0%, transparent 50%);
            animation: gradientMove 15s ease-in-out infinite;
        }

        @keyframes gradientMove {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(3deg); }
        }

        .shapes {
            position: fixed;
            inset: 0;
            overflow: hidden;
            pointer-events: none;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-light), var(--accent));
            opacity: 0.1;
            animation: float 20s ease-in-out infinite;
        }

        .shape:nth-child(1) { width: 400px; height: 400px; top: -10%; left: -5%; }
        .shape:nth-child(2) { width: 300px; height: 300px; top: 60%; right: -10%; animation-delay: -5s; }
        .shape:nth-child(3) { width: 200px; height: 200px; bottom: 10%; left: 20%; animation-delay: -10s; }
        .shape:nth-child(4) { width: 150px; height: 150px; top: 30%; right: 20%; animation-delay: -15s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(30px, -30px) rotate(90deg); }
            50% { transform: translate(-20px, 20px) rotate(180deg); }
            75% { transform: translate(20px, 30px) rotate(270deg); }
        }

        .container {
            position: relative;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.1);
            animation: cardAppear 0.6s ease-out;
        }

        @keyframes cardAppear {
            from { opacity: 0; transform: translateY(30px) scale(0.95); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        .logo { text-align: center; margin-bottom: 32px; }

        .logo-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.2);
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .logo-icon svg { width: 44px; height: 44px; fill: white; }

        h1 {
            text-align: center;
            color: var(--text);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 15px;
            margin-bottom: 32px;
        }

        .subtitle strong { color: var(--accent); font-weight: 600; }

        .form-group { margin-bottom: 24px; }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 500;
            font-size: 14px;
        }

        .input-wrapper { position: relative; }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            pointer-events: none;
        }

        .input-icon svg { width: 20px; height: 20px; }

        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 16px 16px 16px 48px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid var(--border);
            border-radius: 14px;
            font-size: 16px;
            font-family: inherit;
            color: var(--text);
            transition: all 0.3s ease;
        }

        input::placeholder { color: var(--text-muted); }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }

        .password-toggle:hover { color: var(--text); }
        .password-toggle svg { width: 20px; height: 20px; }

        .submit-btn {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 16px;
            font-weight: 600;
            font-family: inherit;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 8px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.4);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .submit-btn span {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn svg { width: 20px; height: 20px; }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: none;
            align-items: center;
            gap: 12px;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert.show { display: flex; }

        .alert svg { width: 20px; height: 20px; flex-shrink: 0; }

        .links {
            text-align: center;
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid var(--border);
        }

        .links a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s;
        }

        .links a:hover { color: var(--primary-light); }
        .links a svg { width: 16px; height: 16px; }

        @media (max-width: 480px) {
            .container { padding: 32px 24px; }
            h1 { font-size: 24px; }
            .logo-icon { width: 64px; height: 64px; }
            .logo-icon svg { width: 36px; height: 36px; }
        }
    </style>
</head>
<body>
    <div class="bg-gradient"></div>
    <div class="shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>

    <div class="container">
        <div class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
            </div>
        </div>

        <h1>Добро пожаловать</h1>
        <p class="subtitle">Войдите в почту <strong>sutulaya.lol</strong></p>

        <div class="alert" id="errorAlert">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <span id="errorText">Ошибка</span>
        </div>

        <form id="loginForm">
            <div class="form-group">
                <label for="user">Email</label>
                <div class="input-wrapper">
                    <input type="text" id="user" name="_user" placeholder="user@sutulaya.lol" autocomplete="email" required>
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                        </svg>
                    </span>
                </div>
            </div>

            <div class="form-group">
                <label for="pass">Пароль</label>
                <div class="input-wrapper">
                    <input type="password" id="pass" name="_pass" placeholder="Введите пароль" autocomplete="current-password" required>
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                    </span>
                    <button type="button" class="password-toggle" onclick="togglePassword()">
                        <svg id="eyeIcon" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn" id="submitBtn">
                <span id="btnText">
                    Войти
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                    </svg>
                </span>
            </button>
        </form>

        <div class="links">
            <a href="/register">
                Создать аккаунт
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                </svg>
            </a>
        </div>
    </div>

    <script>
        // Redirect to clean /login URL after logout
        if (window.location.search.includes('_task=logout') || window.location.search.includes('_task=login')) {
            window.location.replace('/login');
        }

        function togglePassword() {
            const input = document.getElementById('pass');
            const icon = document.getElementById('eyeIcon');
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            icon.innerHTML = isPassword
                ? '<path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/>'
                : '<path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>';
        }

        function showError(msg) {
            document.getElementById('errorText').textContent = msg;
            document.getElementById('errorAlert').classList.add('show');
        }

        function setLoading(loading) {
            const btn = document.getElementById('submitBtn');
            const text = document.getElementById('btnText');
            btn.disabled = loading;
            text.innerHTML = loading
                ? '<span class="loading-spinner"></span> Вход...'
                : 'Войти <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/></svg>';
        }

        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const user = document.getElementById('user').value;
            const pass = document.getElementById('pass').value;

            document.getElementById('errorAlert').classList.remove('show');
            setLoading(true);

            try {
                // Step 1: Fetch Roundcube login page to get CSRF token (browser gets cookie)
                const rcResponse = await fetch('/?_task=login', {
                    credentials: 'include'
                });
                const html = await rcResponse.text();

                // Extract CSRF token
                const tokenMatch = html.match(/name="_token"\s+value="([^"]+)"/);
                if (!tokenMatch) {
                    throw new Error('Не удалось получить токен');
                }
                const token = tokenMatch[1];

                // Step 2: Submit login form to Roundcube
                const formData = new URLSearchParams();
                formData.append('_token', token);
                formData.append('_task', 'login');
                formData.append('_action', 'login');
                formData.append('_timezone', Intl.DateTimeFormat().resolvedOptions().timeZone || 'Europe/Moscow');
                formData.append('_url', '');
                formData.append('_user', user);
                formData.append('_pass', pass);

                const loginResponse = await fetch('/?_task=login', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData.toString()
                });

                // Check response - if redirected to mail, login succeeded
                if (loginResponse.redirected && loginResponse.url.includes('_task=mail')) {
                    window.location.href = loginResponse.url;
                    return;
                }

                // Check if we're now on the mail page
                if (loginResponse.url.includes('_task=mail')) {
                    window.location.href = '/?_task=mail';
                    return;
                }

                // If response is OK and we're still on login page, credentials were wrong
                const responseHtml = await loginResponse.text();
                if (responseHtml.includes('rcmloginuser') || responseHtml.includes('task-login')) {
                    showError('Неверный email или пароль');
                } else {
                    // We might actually be logged in
                    window.location.href = '/?_task=mail';
                    return;
                }

            } catch (err) {
                console.error('Login error:', err);
                showError('Ошибка подключения к серверу');
            }

            setLoading(false);
        });
    </script>
</body>
</html>
