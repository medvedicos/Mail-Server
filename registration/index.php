<?php
session_start();

$domain = 'sutulaya.lol';
$error = '';
$success = '';

// Rate limiting: max 3 registrations per hour per IP
$rateLimitFile = '/tmp/reg_limits_' . md5($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '.json';
$rateLimit = ['count' => 0, 'reset' => time() + 3600];
if (file_exists($rateLimitFile)) {
    $rateLimit = json_decode(file_get_contents($rateLimitFile), true) ?: $rateLimit;
    if ($rateLimit['reset'] < time()) {
        $rateLimit = ['count' => 0, 'reset' => time() + 3600];
    }
}

// CSRF protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to execute command in mailserver container using curl
function execInMailserver($cmdArgs) {
    $cmd = json_encode($cmdArgs);
    $createExec = sprintf(
        'curl -s --unix-socket /var/run/docker.sock -X POST -H "Content-Type: application/json" -d \'{"AttachStdin":false,"AttachStdout":true,"AttachStderr":true,"Tty":false,"Cmd":%s}\' http://localhost/containers/mailserver/exec 2>/dev/null',
        $cmd
    );
    $result = shell_exec($createExec);
    $data = json_decode($result, true);
    
    if (!isset($data['Id'])) {
        return ['success' => false, 'output' => ''];
    }
    
    $execId = $data['Id'];
    $startExec = sprintf(
        'curl -s --unix-socket /var/run/docker.sock -X POST -H "Content-Type: application/json" -d \'{"Detach":false,"Tty":false}\' http://localhost/exec/%s/start 2>/dev/null',
        $execId
    );
    $output = shell_exec($startExec);
    
    return ['success' => true, 'output' => $output ?? ''];
}

function addUser($email, $password) {
    return execInMailserver(["setup", "email", "add", $email, $password]);
}

function listUsers() {
    return execInMailserver(["setup", "email", "list"]);
}

function userExists($email) {
    $result = listUsers();
    return strpos($result['output'] ?? '', $email) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($rateLimit['count'] >= 3) {
        $error = 'Слишком много попыток. Попробуйте через час.';
    } elseif (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        if (empty($username)) {
            $error = 'Введите имя пользователя';
        } elseif (!preg_match('/^[a-z0-9][a-z0-9._-]*$/i', $username) || strlen($username) < 3 || strlen($username) > 30) {
            $error = 'Имя: 3-30 символов (буквы, цифры, точки, дефисы)';
        } elseif (empty($password)) {
            $error = 'Введите пароль';
        } elseif (strlen($password) < 8) {
            $error = 'Пароль: минимум 8 символов';
        } elseif ($password !== $password_confirm) {
            $error = 'Пароли не совпадают';
        } else {
            $email = strtolower($username) . '@' . $domain;
            
            if (userExists($email)) {
                $error = 'Этот адрес уже занят';
            } else {
                $result = addUser($email, $password);
                if ($result['success']) {
                    $success = "Аккаунт <strong>{$email}</strong> создан!";
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    // Update rate limit
                    $rateLimit['count']++;
                    file_put_contents($rateLimitFile, json_encode($rateLimit));
                } else {
                    $error = 'Ошибка. Попробуйте позже.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация - <?= $domain ?></title>
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

        /* Animated gradient background */
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

        /* Floating shapes */
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

        .shape:nth-child(1) { width: 400px; height: 400px; top: -10%; left: -5%; animation-delay: 0s; }
        .shape:nth-child(2) { width: 300px; height: 300px; top: 60%; right: -10%; animation-delay: -5s; }
        .shape:nth-child(3) { width: 200px; height: 200px; bottom: 10%; left: 20%; animation-delay: -10s; }
        .shape:nth-child(4) { width: 150px; height: 150px; top: 30%; right: 20%; animation-delay: -15s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(30px, -30px) rotate(90deg); }
            50% { transform: translate(-20px, 20px) rotate(180deg); }
            75% { transform: translate(20px, 30px) rotate(270deg); }
        }

        /* Glass card */
        .container {
            position: relative;
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 48px 40px;
            width: 100%;
            max-width: 440px;
            box-shadow:
                0 25px 50px -12px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            animation: cardAppear 0.6s ease-out;
        }

        @keyframes cardAppear {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Logo */
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow:
                0 10px 40px rgba(99, 102, 241, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-5px); }
        }

        .logo-icon svg {
            width: 44px;
            height: 44px;
            fill: white;
        }

        h1 {
            text-align: center;
            color: var(--text);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 15px;
            margin-bottom: 32px;
        }

        .subtitle strong {
            color: var(--accent);
            font-weight: 600;
        }

        /* Form styles */
        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 500;
            font-size: 14px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            transition: color 0.3s;
            pointer-events: none;
        }

        .input-icon svg {
            width: 20px;
            height: 20px;
        }

        input[type="text"],
        input[type="password"] {
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

        input::placeholder {
            color: var(--text-muted);
        }

        input:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(99, 102, 241, 0.1);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.15);
        }

        input:focus + .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: var(--primary-light);
        }

        .input-with-suffix {
            padding-right: 130px;
        }

        .domain-suffix {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 14px;
            font-weight: 500;
            background: rgba(255, 255, 255, 0.1);
            padding: 6px 12px;
            border-radius: 8px;
        }

        /* Password toggle */
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
            transition: color 0.3s;
        }

        .password-toggle:hover {
            color: var(--text);
        }

        .password-toggle svg {
            width: 20px;
            height: 20px;
        }

        /* Password strength */
        .password-strength {
            display: flex;
            gap: 6px;
            margin-top: 10px;
        }

        .strength-bar {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: rgba(255, 255, 255, 0.1);
            transition: all 0.3s;
        }

        .strength-bar.active { background: var(--error); }
        .strength-bar.medium { background: #f59e0b; }
        .strength-bar.strong { background: var(--success); }

        .strength-text {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 6px;
        }

        /* Submit button */
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

        .submit-btn::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, var(--accent), var(--primary-light));
            opacity: 0;
            transition: opacity 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 40px rgba(99, 102, 241, 0.4);
        }

        .submit-btn:hover::before {
            opacity: 1;
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .submit-btn span {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .submit-btn svg {
            width: 20px;
            height: 20px;
            transition: transform 0.3s;
        }

        .submit-btn:hover svg {
            transform: translateX(4px);
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: alertAppear 0.4s ease-out;
        }

        @keyframes alertAppear {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert svg {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .alert.error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert.success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #6ee7b7;
        }

        /* Links */
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
            transition: color 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .links a:hover {
            color: var(--primary-light);
        }

        .links a svg {
            width: 16px;
            height: 16px;
            transition: transform 0.3s;
        }

        .links a:hover svg {
            transform: translateX(-4px);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .container {
                padding: 32px 24px;
                border-radius: 20px;
            }

            h1 { font-size: 24px; }

            .logo-icon {
                width: 64px;
                height: 64px;
                border-radius: 16px;
            }

            .logo-icon svg {
                width: 36px;
                height: 36px;
            }
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

        <h1>Создать аккаунт</h1>
        <p class="subtitle">Ваша новая почта на <strong><?= $domain ?></strong></p>

        <?php if ($error): ?>
        <div class="alert error">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/>
            </svg>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert success">
            <svg viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
            </svg>
            <?= $success ?>
        </div>
        <?php endif; ?>

        <form method="POST" id="registerForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="form-group">
                <label for="username">Имя пользователя</label>
                <div class="input-wrapper">
                    <input
                        type="text"
                        id="username"
                        name="username"
                        class="input-with-suffix"
                        placeholder="ivan.petrov"
                        value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                        autocomplete="off"
                        required
                    >
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                        </svg>
                    </span>
                    <span class="domain-suffix">@<?= $domain ?></span>
                </div>
            </div>

            <div class="form-group">
                <label for="password">Пароль</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Минимум 8 символов"
                        required
                    >
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
                        </svg>
                    </span>
                    <button type="button" class="password-toggle" onclick="togglePassword('password', this)">
                        <svg class="eye-open" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </button>
                </div>
                <div class="password-strength" id="strengthBars">
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                    <div class="strength-bar"></div>
                </div>
                <div class="strength-text" id="strengthText"></div>
            </div>

            <div class="form-group">
                <label for="password_confirm">Подтверждение пароля</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        placeholder="Повторите пароль"
                        required
                    >
                    <span class="input-icon">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-2 16l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/>
                        </svg>
                    </span>
                    <button type="button" class="password-toggle" onclick="togglePassword('password_confirm', this)">
                        <svg class="eye-open" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                <span>
                    Создать аккаунт
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                    </svg>
                </span>
            </button>
        </form>

        <div class="links">
            <a href="/">
                <svg viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
                Войти в почту
            </a>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword(inputId, button) {
            const input = document.getElementById(inputId);
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            button.innerHTML = isPassword
                ? '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 7c2.76 0 5 2.24 5 5 0 .65-.13 1.26-.36 1.83l2.92 2.92c1.51-1.26 2.7-2.89 3.43-4.75-1.73-4.39-6-7.5-11-7.5-1.4 0-2.74.25-3.98.7l2.16 2.16C10.74 7.13 11.35 7 12 7zM2 4.27l2.28 2.28.46.46C3.08 8.3 1.78 10.02 1 12c1.73 4.39 6 7.5 11 7.5 1.55 0 3.03-.3 4.38-.84l.42.42L19.73 22 21 20.73 3.27 3 2 4.27zM7.53 9.8l1.55 1.55c-.05.21-.08.43-.08.65 0 1.66 1.34 3 3 3 .22 0 .44-.03.65-.08l1.55 1.55c-.67.33-1.41.53-2.2.53-2.76 0-5-2.24-5-5 0-.79.2-1.53.53-2.2zm4.31-.78l3.15 3.15.02-.16c0-1.66-1.34-3-3-3l-.17.01z"/></svg>'
                : '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>';
        }

        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthBars = document.querySelectorAll('.strength-bar');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            let text = '';

            if (password.length >= 8) strength++;
            if (password.length >= 12) strength++;
            if (/[A-Z]/.test(password) && /[a-z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            strength = Math.min(strength, 4);

            strengthBars.forEach((bar, index) => {
                bar.className = 'strength-bar';
                if (index < strength) {
                    if (strength <= 1) bar.classList.add('active');
                    else if (strength <= 2) bar.classList.add('medium');
                    else bar.classList.add('strong');
                }
            });

            if (password.length === 0) text = '';
            else if (strength <= 1) text = 'Слабый пароль';
            else if (strength <= 2) text = 'Средний пароль';
            else if (strength <= 3) text = 'Хороший пароль';
            else text = 'Отличный пароль!';

            strengthText.textContent = text;
        });
    </script>
</body>
</html>
