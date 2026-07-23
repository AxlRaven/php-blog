<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

$stats = [
    'live' => (int) db()->query(
        "SELECT COUNT(*) FROM posts WHERE status = 'published' AND published_at IS NOT NULL AND published_at <= NOW()"
    )->fetchColumn(),
    'scheduled' => (int) db()->query(
        "SELECT COUNT(*) FROM posts WHERE status = 'published' AND (published_at IS NULL OR published_at > NOW())"
    )->fetchColumn(),
    'drafts' => (int) db()->query("SELECT COUNT(*) FROM posts WHERE status = 'draft'")->fetchColumn(),
    'tags' => (int) db()->query('SELECT COUNT(*) FROM tags')->fetchColumn(),
];

$recent = db()->query(
    'SELECT id, title, status, published_at, updated_at FROM posts ORDER BY updated_at DESC LIMIT 5'
)->fetchAll();

$adminTitle = 'Обзор';
require __DIR__ . '/_header.php';
?>

<div class="admin-page-head">
    <h1>Обзор</h1>
    <a class="btn" href="<?= e(url('admin/post/new')) ?>">Новая запись</a>
</div>

<div class="admin-stats admin-stats--4">
    <div class="stat"><strong><?= $stats['live'] ?></strong><span>на сайте</span></div>
    <div class="stat"><strong><?= $stats['scheduled'] ?></strong><span>отложено</span></div>
    <div class="stat"><strong><?= $stats['drafts'] ?></strong><span>черновиков</span></div>
    <div class="stat"><strong><?= $stats['tags'] ?></strong><span>тегов</span></div>
</div>

<section class="admin-panel">
    <h2>Недавние записи</h2>
    <?php if (!$recent): ?>
        <p class="muted">Записей ещё нет. <a href="<?= e(url('admin/post/new')) ?>">Создать первую</a></p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr><th>Заголовок</th><th>Статус</th><th>Обновлено</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($recent as $row): ?>
                <?php $vis = post_visibility($row); ?>
                <tr>
                    <td><?= e($row['title']) ?></td>
                    <td><span class="badge badge--<?= e($vis['key']) ?>"><?= e($vis['label']) ?></span></td>
                    <td><?= e(format_date($row['updated_at'])) ?></td>
                    <td><a href="<?= e(url('admin/post/edit/' . $row['id'])) ?>">Править</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
