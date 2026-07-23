<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
$result = fetch_posts($page);
$mode = load_mode();

$pageTitle = $page > 1 ? site_title() . ' — страница ' . $page : site_title();
$pageDescription = site_description();
seo_configure([
    'title' => $pageTitle,
    'description' => $pageDescription,
    'type' => 'website',
    'canonical' => $page > 1 ? 'page/' . $page : '',
    'prev' => $page > 1 ? ($page === 2 ? '' : 'page/' . ($page - 1)) : null,
    'next' => $page < $result['pages'] ? 'page/' . ($page + 1) : null,
    'breadcrumbs' => [['Лента', '']],
]);

require __DIR__ . '/includes/header.php';
?>

<?php if (!$result['items']): ?>
    <div class="empty-state">
        <p>Пока нет опубликованных записей.</p>
    </div>
<?php else: ?>
    <div class="feed feed--clip-tall-images" id="feed" data-mode="<?= e($mode) ?>" data-page="<?= (int) $result['page'] ?>" data-pages="<?= (int) $result['pages'] ?>">
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
            <?php if ($result['page'] > 1): ?>
                <a href="<?= e(url('page/' . ($result['page'] - 1))) ?>" aria-label="Назад">←</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $result['pages']; $i++): ?>
                <?php if ($i === $result['page']): ?>
                    <span class="is-current" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="<?= e($i === 1 ? url() : url('page/' . $i)) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($result['page'] < $result['pages']): ?>
                <a href="<?= e(url('page/' . ($result['page'] + 1))) ?>" aria-label="Вперёд">→</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
