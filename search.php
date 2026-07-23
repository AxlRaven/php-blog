<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$result = $q !== '' ? fetch_posts($page, null, $q) : ['items' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => posts_per_page()];
set_search_highlight_query($q);

$pageTitle = ($q !== '' ? 'Поиск: ' . $q : 'Поиск') . ' — ' . site_title();
$pageDescription = $q !== '' ? 'Результаты поиска по запросу «' . $q . '»' : 'Поиск по блогу';
seo_configure([
    'title' => $pageTitle,
    'description' => $pageDescription,
    'type' => 'website',
    'canonical' => 'search' . ($q !== '' ? '?q=' . rawurlencode($q) : ''),
    'robots' => 'noindex,follow',
]);

require __DIR__ . '/includes/header.php';
?>

<section class="page-intro">
    <h1>Поиск</h1>
    <p>Найдите записи по заголовку или тексту.</p>
</section>

<form class="search-form-large" action="<?= e(url('search')) ?>" method="get" role="search">
    <label class="visually-hidden" for="search-q">Запрос</label>
    <input type="search" id="search-q" name="q" value="<?= e($q) ?>" placeholder="Что ищем?" autofocus>
    <button class="btn" type="submit">Найти</button>
</form>

<?php if ($q === ''): ?>
    <div class="empty-state"><p>Введите запрос, чтобы начать поиск.</p></div>
<?php elseif (!$result['items']): ?>
    <div class="empty-state"><p>Ничего не найдено по запросу «<?= e($q) ?>».</p></div>
<?php else: ?>
    <p style="color: var(--text-muted); margin-bottom: 1.5rem;">Найдено: <?= (int) $result['total'] ?></p>
    <div class="feed">
        <?php foreach ($result['items'] as $post): ?>
            <?= render_post_card($post, false) ?>
        <?php endforeach; ?>
    </div>
    <?php if ($result['pages'] > 1): ?>
        <nav class="pagination" aria-label="Страницы">
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                <?php
                $href = url('search') . '?q=' . rawurlencode($q) . ($i > 1 ? '&page=' . $i : '');
                ?>
                <?php if ($i === $result['page']): ?>
                    <span class="is-current"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e($href) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
