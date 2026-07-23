<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

function admin_posts_sort_key(): string
{
    $sort = (string) ($_GET['sort'] ?? $_POST['sort'] ?? 'date');
    $allowed = ['title', 'status', 'views', 'rating', 'date'];
    return in_array($sort, $allowed, true) ? $sort : 'date';
}

function admin_posts_sort_dir(): string
{
    return strtolower((string) ($_GET['dir'] ?? $_POST['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
}

function admin_posts_url(?string $sort = null, ?string $dir = null): string
{
    $sort = $sort ?? admin_posts_sort_key();
    $dir = $dir ?? strtolower(admin_posts_sort_dir());
    $params = ['sort' => $sort, 'dir' => $dir];

    return url('admin/posts?' . http_build_query($params));
}

function admin_posts_sort_href(string $column): string
{
    $current = admin_posts_sort_key();
    $dir = admin_posts_sort_dir();
    if ($current === $column) {
        $newDir = $dir === 'ASC' ? 'desc' : 'asc';
    } else {
        $newDir = $column === 'title' ? 'asc' : 'desc';
    }

    return admin_posts_url($column, $newDir);
}

function admin_posts_sort_arrow(string $column): string
{
    if (admin_posts_sort_key() !== $column) {
        return '';
    }

    return admin_posts_sort_dir() === 'ASC' ? ' ↑' : ' ↓';
}

function admin_posts_order_sql(): string
{
    $sort = admin_posts_sort_key();
    $dir = admin_posts_sort_dir();

    return match ($sort) {
        'title' => "title {$dir}",
        'status' => "status {$dir}, COALESCE(published_at, updated_at) DESC",
        'views' => "views {$dir}",
        'rating' => "CASE WHEN rating_count > 0 THEN rating_sum / rating_count ELSE 0 END {$dir}, rating_count {$dir}",
        default => "COALESCE(published_at, updated_at) {$dir}, id {$dir}",
    };
}

function admin_posts_date_short(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '—';
    }
    if ((int) date('Y', $ts) === (int) date('Y')) {
        return date('d.m H:i', $ts);
    }

    return date('d.m.y H:i', $ts);
}

$postsSort = admin_posts_sort_key();
$postsDir = strtolower(admin_posts_sort_dir());
$listUrl = admin_posts_url();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        db()->prepare('DELETE FROM posts WHERE id = ?')->execute([$id]);
        flash('success', 'Запись удалена.');
    }
    redirect($listUrl);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'publish') {
    require_csrf();
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = db()->prepare('SELECT published_at FROM posts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        $publishedAt = $row['published_at'] ?? null;
        if (!$publishedAt || strtotime((string) $publishedAt) > time()) {
            $publishedAt = date('Y-m-d H:i:s');
        }
        db()->prepare(
            "UPDATE posts SET status = 'published', published_at = ? WHERE id = ?"
        )->execute([$publishedAt, $id]);
        flash('success', 'Запись опубликована.');
    }
    redirect($listUrl);
}

$posts = db()->query(
    'SELECT id, title, slug, status, published_at, updated_at, views, rating_sum, rating_count
     FROM posts ORDER BY ' . admin_posts_order_sql()
)->fetchAll();

$adminTitle = 'Записи';
require __DIR__ . '/_header.php';
?>

<div class="admin-page-head">
    <h1>Записи</h1>
    <a class="btn" href="<?= e(url('admin/post/new')) ?>">Новая запись</a>
</div>

<section class="admin-panel admin-panel--table">
    <?php if (!$posts): ?>
        <p class="muted">Пока пусто.</p>
    <?php else: ?>
        <div class="admin-table-wrap">
        <table class="admin-table admin-table--posts">
            <colgroup>
                <col class="col-title">
                <col class="col-status">
                <col class="col-views">
                <col class="col-rating">
                <col class="col-date">
                <col class="col-actions">
            </colgroup>
            <thead>
            <tr>
                <th class="col-title">
                    <a class="admin-table__sort<?= $postsSort === 'title' ? ' is-active' : '' ?>" href="<?= e(admin_posts_sort_href('title')) ?>">Заголовок<?= admin_posts_sort_arrow('title') ?></a>
                </th>
                <th class="col-status">
                    <a class="admin-table__sort<?= $postsSort === 'status' ? ' is-active' : '' ?>" href="<?= e(admin_posts_sort_href('status')) ?>">Статус<?= admin_posts_sort_arrow('status') ?></a>
                </th>
                <th class="col-views admin-table__num">
                    <a class="admin-table__sort<?= $postsSort === 'views' ? ' is-active' : '' ?>" href="<?= e(admin_posts_sort_href('views')) ?>">Просм.<?= admin_posts_sort_arrow('views') ?></a>
                </th>
                <th class="col-rating admin-table__num">
                    <a class="admin-table__sort<?= $postsSort === 'rating' ? ' is-active' : '' ?>" href="<?= e(admin_posts_sort_href('rating')) ?>">Рейт.<?= admin_posts_sort_arrow('rating') ?></a>
                </th>
                <th class="col-date">
                    <a class="admin-table__sort<?= $postsSort === 'date' ? ' is-active' : '' ?>" href="<?= e(admin_posts_sort_href('date')) ?>">Дата<?= admin_posts_sort_arrow('date') ?></a>
                </th>
                <th class="col-actions" aria-label="Действия"></th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($posts as $row): ?>
                <?php
                $vis = post_visibility($row);
                $rating = post_rating_stats($row);
                ?>
                <tr>
                    <td class="col-title">
                        <a class="post-title" href="<?= e(url('admin/post/edit/' . $row['id'])) ?>" title="<?= e($row['title']) ?>"><?= e($row['title']) ?></a>
                        <div class="table-sub table-sub--inline">
                            <?php if (is_post_public($row)): ?>
                                <a href="<?= e(url('post/' . $row['slug'])) ?>" target="_blank" rel="noopener">на сайте</a>
                            <?php elseif ($vis['key'] === 'scheduled'): ?>
                                <span title="Отложено: <?= e(format_date($row['published_at'])) ?>">⏱ <?= e(admin_posts_date_short($row['published_at'])) ?></span>
                                · <a href="<?= e(url('post/' . $row['slug'])) ?>" target="_blank" rel="noopener">превью</a>
                            <?php else: ?>
                                <a href="<?= e(url('post/' . $row['slug'])) ?>" target="_blank" rel="noopener">превью</a>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="col-status"><span class="badge badge--<?= e($vis['key']) ?>" title="<?= e($vis['label']) ?>"><?= e(match ($vis['key']) {
                        'published' => 'опубл.',
                        'scheduled' => 'отлож.',
                        default => 'черн.',
                    }) ?></span></td>
                    <td class="col-views admin-table__num">
                        <span class="admin-stat<?= (int) ($row['views'] ?? 0) >= 100 ? ' admin-stat--hot' : '' ?>" title="Просмотры">
                            <?= number_format((int) ($row['views'] ?? 0), 0, '.', ' ') ?>
                        </span>
                    </td>
                    <td class="col-rating admin-table__num">
                        <?php if ($rating['count'] > 0): ?>
                            <span class="admin-stat admin-stat--rating<?= $rating['average'] >= 4 ? ' admin-stat--hot' : '' ?>" title="Средний рейтинг">
                                ★<?= e(number_format($rating['average'], 1, '.', '')) ?><span class="admin-stat__sub">(<?= (int) $rating['count'] ?>)</span>
                            </span>
                        <?php else: ?>
                            <span class="admin-stat admin-stat--empty">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="col-date" title="<?= e(format_date($row['published_at'] ?? $row['updated_at'])) ?>">
                        <?= e(admin_posts_date_short($row['published_at'] ?? $row['updated_at'])) ?>
                    </td>
                    <td class="col-actions table-actions">
                        <a href="<?= e(url('admin/post/edit/' . $row['id'])) ?>">ред.</a>
                        <?php if ($vis['key'] !== 'published'): ?>
                            <form method="post" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="publish">
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="sort" value="<?= e($postsSort) ?>">
                                <input type="hidden" name="dir" value="<?= e($postsDir) ?>">
                                <button type="submit" class="link-publish">опубл.</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="inline-form" onsubmit="return confirm('Удалить запись?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                            <input type="hidden" name="sort" value="<?= e($postsSort) ?>">
                            <input type="hidden" name="dir" value="<?= e($postsDir) ?>">
                            <button type="submit" class="link-danger">удал.</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/_footer.php'; ?>
