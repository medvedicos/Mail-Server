<?php
session_start();

$domain = 'sutulaya.lol';
$error = '';
$success = '';

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
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
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
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            width: 100%;
            max-width: 420px;
        }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo svg { width: 70px; height: 70px; }
        h1 { text-align: center; color: #333; margin-bottom: 8px; font-size: 24px; }
        .subtitle { text-align: center; color: #666; margin-bottom: 28px; font-size: 14px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; margin-bottom: 6px; color: #333; font-weight: 500; font-size: 14px; }
        .input-wrapper { position: relative; display: flex; align-items: center; }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.2s;
        }
        input:focus { outline: none; border-color: #4a90d9; box-shadow: 0 0 0 3px rgba(74,144,217,0.1); }
        .domain-suffix { position: absolute; right: 14px; color: #666; font-size: 14px; }
        .input-with-suffix { padding-right: 120px; }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4a90d9 0%, #357abd 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(74,144,217,0.4); }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .error { background: #ffe6e6; color: #c0392b; border-left: 4px solid #c0392b; }
        .success { background: #d4edda; color: #155724; border-left: 4px solid #28a745; }
        .links { text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid #eee; }
        .links a { color: #4a90d9; text-decoration: none; font-size: 14px; font-weight: 500; }
        .links a:hover { text-decoration: underline; }
        .hint { font-size: 12px; color: #888; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <svg viewBox="0 0 100 100" fill="none">
                <circle cx="50" cy="50" r="45" fill="#4a90d9"/>
                <path d="M25 35 L50 55 L75 35 L75 65 L25 65 Z" fill="white"/>
                <path d="M25 35 L50 50 L75 35" stroke="white" stroke-width="3" fill="none"/>
            </svg>
        </div>
        <h1>Регистрация</h1>
        <p class="subtitle">Создайте почту на <?= $domain ?></p>
        
        <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?= $success ?></div><?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="form-group">
                <label>Имя пользователя</label>
                <div class="input-wrapper">
                    <input type="text" name="username" class="input-with-suffix" placeholder="ivan.petrov" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" autocomplete="off" required>
                    <span class="domain-suffix">@<?= $domain ?></span>
                </div>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" placeholder="••••••••" required>
                <p class="hint">Минимум 8 символов</p>
            </div>
            <div class="form-group">
                <label>Подтверждение</label>
                <input type="password" name="password_confirm" placeholder="••••••••" required>
            </div>
            <button type="submit">Создать аккаунт</button>
        </form>
        <div class="links"><a href="/">← Войти в почту</a></div>
    </div>
</body>
</html>
