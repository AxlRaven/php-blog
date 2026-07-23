<?php
declare(strict_types=1);
/** @var string $pageTitle */
/** @var string|null $pageDescription */
$pageTitle = $pageTitle ?? site_title();
$pageDescription = $pageDescription ?? site_description();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0060a0">
    <?php seo_render_head(); ?>
    <?php if ($logoUrl = site_logo_url()): ?>
    <link rel="icon" type="image/png" href="<?= e($logoUrl) ?>">
    <link rel="apple-touch-icon" href="<?= e($logoUrl) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?= e(asset('css/fonts.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/prism.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body>
<div class="page">
    <header class="site-header">
        <div class="site-header__inner">
            <a class="site-brand" href="<?= e(url()) ?>">
                <?php if ($logoUrl = site_logo_url()): ?>
                    <img class="site-brand__logo" src="<?= e($logoUrl) ?>" width="36" height="36" alt="">
                <?php endif; ?>
                <span class="site-brand__name"><?= e(site_title()) ?></span>
            </a>
            <nav class="site-nav" aria-label="Основная навигация">
                <a href="<?= e(url()) ?>">Лента</a>
                <a href="<?= e(url('tags')) ?>">Теги</a>
                <a href="<?= e(url('search')) ?>">Поиск</a>
            </nav>
            <form class="site-search" action="<?= e(url('search')) ?>" method="get" role="search">
                <label class="visually-hidden" for="q">Поиск</label>
                <input type="search" id="q" name="q" placeholder="Поиск…" value="<?= e($_GET['q'] ?? '') ?>" autocomplete="off">
                <button type="submit" aria-label="Искать">⌕</button>
            </form>
        </div>
    </header>

    <?php if ($flashes): ?>
        <div class="flashes" role="status">
            <?php foreach ($flashes as $flash): ?>
                <div class="flash flash--<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main class="site-main">
