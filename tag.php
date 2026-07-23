<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));

if ($slug === '') {
    $tags = get_all_tags();
    $pageTitle = 'Теги — ' . site_title();
    $pageDescription = 'Все темы и теги блога «' . site_title() . '»';
    seo_configure([
        'title' => $pageTitle,
        'description' => $pageDescription,
        'type' => 'website',
        'canonical' => 'tags',
        'breadcrumbs' => [['Теги', 'tags']],
    ]);
    require __DIR__ . '/includes/header.php';
    ?>
    <section class="page-intro">
        <h1>Теги</h1>
        <p>Все темы блога.</p>
    </section>
    <?php if (!$tags): ?>
        <div class="empty-state"><p>Тегов пока нет.</p></div>
    <?php else: ?>
        <div class="tags-cloud">
            <?php foreach ($tags as $tag): ?>
                <a class="tag tag--lg" href="<?= e(url('tag/' . $tag['slug'])) ?>">
                    <?= e($tag['name']) ?>
                    <span class="tag-count"><?= (int) $tag['post_count'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php
    require __DIR__ . '/includes/footer.php';
    exit;
}

$tag = get_public_tag_by_slug($slug);

if (!$tag) {
    http_response_code(404);
    $pageTitle = 'Тег не найден — ' . site_title();
    seo_configure(['robots' => 'noindex,follow']);
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>Такого тега нет.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$result = fetch_posts($page, $slug);
$mode = load_mode();
$pageTitle = '#' . $tag['name'] . ($page > 1 ? ' — страница ' . $page : '') . ' — ' . site_title();
$pageDescription = 'Записи с тегом «' . $tag['name'] . '» — ' . site_title();
seo_configure([
    'title' => $pageTitle,
    'description' => $pageDescription,
    'keywords' => (string) $tag['name'],
    'type' => 'website',
    'canonical' => $page > 1 ? 'tag/' . $slug . '/page/' . $page : 'tag/' . $slug,
    'prev' => $page > 1 ? ($page === 2 ? 'tag/' . $slug : 'tag/' . $slug . '/page/' . ($page - 1)) : null,
    'next' => $page < $result['pages'] ? 'tag/' . $slug . '/page/' . ($page + 1) : null,
    'breadcrumbs' => [
        ['Теги', 'tags'],
        ['#' . $tag['name'], 'tag/' . $slug],
    ],
]);

require __DIR__ . '/includes/header.php';
?>

<a class="back-link" href="<?= e(url('tags')) ?>">← Все теги</a>

<section class="page-intro">
    <h1>#<?= e($tag['name']) ?></h1>
    <p>Записей: <?= (int) $result['total'] ?></p>
</section>

<?php if (!$result['items']): ?>
    <div class="empty-state"><p>Нет опубликованных записей с этим тегом.</p></div>
<?php else: ?>
    <div class="feed" id="feed" data-mode="<?= e($mode) ?>" data-page="<?= (int) $result['page'] ?>" data-pages="<?= (int) $result['pages'] ?>" data-tag="<?= e($slug) ?>">
        <?php foreach ($result['items'] as $post): ?>
            <?= render_post_card($post, false) ?>
        <?php endforeach; ?>
    </div>

    <?php if ($mode === 'infinite' && $result['page'] < $result['pages']): ?>
        <div class="load-more-wrap">
            <button type="button" class="btn btn--ghost" id="load-more" data-next="<?= (int) ($result['page'] + 1) ?>">
                Показать ещё
            </button>
        </div>
    <?php elseif ($mode === 'pagination' && $result['pages'] > 1): ?>
        <nav class="pagination" aria-label="Страницы">
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                <?php $href = $i === 1 ? url('tag/' . $slug) : url('tag/' . $slug . '/page/' . $i); ?>
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
