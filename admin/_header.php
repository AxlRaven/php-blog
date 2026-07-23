<?php
declare(strict_types=1);
/** @var string $adminTitle */
$adminTitle = $adminTitle ?? 'Админка';
$adminWithEditor = $adminWithEditor ?? false;
$user = auth_user();
$flashes = get_flashes();
$current = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($adminTitle) ?> — <?= e(site_title()) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/font-awesome.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/easymde.min.css')) ?>">
    <?php if ($adminWithEditor): ?>
    <link rel="stylesheet" href="<?= e(asset('css/prism.min.css')) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-body">
<header class="admin-top">
    <div class="admin-top__inner">
        <a class="admin-brand" href="<?= e(url('admin')) ?>">Админка</a>
        <nav class="admin-nav">
            <a class="<?= $current === 'index.php' ? 'is-active' : '' ?>" href="<?= e(url('admin')) ?>">Обзор</a>
            <a class="<?= in_array($current, ['posts.php', 'post-edit.php'], true) ? 'is-active' : '' ?>" href="<?= e(url('admin/posts')) ?>">Записи</a>
            <a class="<?= $current === 'settings.php' ? 'is-active' : '' ?>" href="<?= e(url('admin/settings')) ?>">Настройки</a>
            <a href="<?= e(url()) ?>" target="_blank" rel="noopener">Сайт ↗</a>
        </nav>
        <div class="admin-user">
            <span><?= e($user['username'] ?? '') ?></span>
            <a href="<?= e(url('admin/logout')) ?>">Выйти</a>
        </div>
    </div>
</header>

<?php if ($flashes): ?>
    <div class="admin-flashes">
        <?php foreach ($flashes as $flash): ?>
            <div class="admin-alert admin-alert--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$leftoverInstall = admin_leftover_install_files();
if ($leftoverInstall):
?>
    <div class="admin-flashes admin-flashes--sticky">
        <div class="admin-alert admin-alert--warning">
            <strong>Внимание:</strong> на сервере остались установочные файлы — удалите их:
            <code><?= e(implode(', ', $leftoverInstall)) ?></code>
        </div>
    </div>
<?php endif; ?>

<main class="admin-main">
