<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (auth_user()) {
    redirect(url('admin'));
}

if (totp_login_pending()) {
    redirect(url('admin/login-2fa'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $error = 'Введите логин и пароль.';
    } else {
        $result = attempt_login($username, $password);
        if ($result === true) {
            redirect(url('admin'));
        }
        if ($result === 'totp_required') {
            redirect(url('admin/login-2fa'));
        }
    }
    if ($error === '') {
        $error = 'Неверный логин или пароль.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Вход — <?= e(site_title()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-login">
<div class="login-card">
    <h1>Админка</h1>
    <p class="login-lead"><?= e(site_title()) ?></p>
    <?php if ($error): ?>
        <div class="admin-alert admin-alert--error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="username">Логин</label>
        <input id="username" name="username" autocomplete="username" required autofocus>
        <label for="password">Пароль</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <button class="btn" type="submit">Войти</button>
    </form>
    <p class="login-back"><a href="<?= e(url()) ?>">← На сайт</a></p>
</div>
</body>
</html>
