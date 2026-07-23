<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

if (auth_user()) {
    redirect(url('admin'));
}

if (!totp_login_pending()) {
    redirect(url('admin/login'));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $code = trim((string) ($_POST['code'] ?? ''));
    if ($code === '') {
        $error = 'Введите код из приложения-аутентификатора.';
    } elseif (complete_totp_login($code)) {
        redirect(url('admin'));
    } else {
        $error = 'Неверный код. Проверьте время на телефоне и попробуйте снова.';
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Код 2FA — <?= e(site_title()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-login">
<div class="login-card">
    <h1>Двухфакторная проверка</h1>
    <p class="login-lead">Введите 6-значный код из Google Authenticator, Authy или аналога.</p>
    <?php if ($error): ?>
        <div class="admin-alert admin-alert--error"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label for="code">Код TOTP</label>
        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus placeholder="000000">
        <button class="btn" type="submit">Подтвердить</button>
    </form>
    <p class="login-back"><a href="<?= e(url('admin/login')) ?>">← Назад к входу</a></p>
</div>
</body>
</html>
