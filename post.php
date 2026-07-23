<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    $pageTitle = 'Не найдено — ' . site_title();
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>Запись не найдена.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$isPreview = false;
$post = get_post_by_slug($slug);

if (!$post && auth_user()) {
    $post = get_post_by_slug($slug, true);
    if ($post && !is_post_public($post)) {
        $isPreview = true;
    }
}

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Не найдено — ' . site_title();
    require __DIR__ . '/includes/header.php';
    echo '<div class="empty-state"><p>Запись не найдена.</p></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

if (!$isPreview) {
    record_post_view((int) $post['id']);
    $stmt = db()->prepare('SELECT views FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $post['id']]);
    $post['views'] = (int) ($stmt->fetchColumn() ?: 0);
}

$split = split_more($post['content']);
$pageTitle = ($isPreview ? 'Превью: ' : '') . $post['title'] . ' — ' . site_title();
$plain = trim(preg_replace('/\s+/u', ' ', strip_tags(markdown_to_html($split['excerpt']))) ?? '');
$pageDescription = $plain;
$tagNames = array_column($post['tags'] ?? [], 'name');
$ratingStats = post_rating_stats($post);

seo_configure([
    'title' => $pageTitle,
    'og_title' => (string) $post['title'],
    'description' => $pageDescription,
    'keywords' => seo_keywords_from_tags($post['tags'] ?? []),
    'type' => 'article',
    'canonical' => 'post/' . $slug,
    'image' => seo_post_og_image($post),
    'robots' => $isPreview ? 'noindex,nofollow' : 'index,follow,max-image-preview:large',
    'article' => [
        'headline' => (string) $post['title'],
        'datePublished' => $post['published_at'] ?? $post['created_at'],
        'dateModified' => $post['updated_at'] ?? $post['published_at'] ?? $post['created_at'],
        'author' => seo_author(),
        'tags' => $tagNames,
        'ratingValue' => $ratingStats['average'],
        'ratingCount' => $ratingStats['count'],
    ],
    'breadcrumbs' => [
        ['Лента', ''],
        [(string) $post['title'], 'post/' . $slug],
    ],
]);

require __DIR__ . '/includes/header.php';

if ($isPreview):
    $vis = post_visibility($post);
    $previewNote = $vis['key'] === 'scheduled'
        ? 'Отложенная публикация: ' . format_date($post['published_at']) . '. Для посетителей страница скрыта.'
        : 'Черновик. Для посетителей страница скрыта.';
    ?>
    <div class="preview-banner" role="status">
        <strong>Превью для администратора</strong>
        <span><?= e($previewNote) ?></span>
        <a href="<?= e(url('admin/post/edit/' . $post['id'])) ?>">Править запись</a>
    </div>
<?php endif; ?>

<a class="back-link" href="<?= e($isPreview ? url('admin/posts') : url()) ?>">
    <?= $isPreview ? '← К записям' : '← К ленте' ?>
</a>

<article class="post-single<?= $isPreview ? ' post-single--preview' : '' ?>">
    <?= render_post_card($post, true, !$isPreview) ?>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
