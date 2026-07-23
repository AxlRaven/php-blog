<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

$id = (int) ($_GET['id'] ?? 0);
$post = null;
$tagNames = '';

if ($id > 0) {
    $stmt = db()->prepare('SELECT * FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $post = $stmt->fetch();
    if (!$post) {
        flash('error', 'Запись не найдена.');
        redirect(url('admin/posts'));
    }
    $tags = get_post_tags($id);
    $tagNames = implode(', ', array_column($tags, 'name'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $title = trim((string) ($_POST['title'] ?? ''));
    $content = (string) ($_POST['content'] ?? '');
    $status = ($_POST['status'] ?? 'draft') === 'published' ? 'published' : 'draft';
    $action = (string) ($_POST['action'] ?? 'save');
    if ($action === 'publish') {
        $status = 'published';
    } elseif ($action === 'draft') {
        $status = 'draft';
    }
    $tagsInput = (string) ($_POST['tags'] ?? '');
    $slugInput = trim((string) ($_POST['slug'] ?? ''));
    $publishedAtInput = parse_datetime_local($_POST['published_at'] ?? null);

    if ($title === '') {
        flash('error', 'Укажите заголовок.');
        redirect($id ? url('admin/post/edit/' . $id) : url('admin/post/new'));
    }

    $slug = $slugInput !== '' ? slugify($slugInput) : unique_slug($title, $id ?: null);
    if ($slugInput !== '') {
        $slug = unique_slug($slug, $id ?: null);
    }

    if ($status === 'published') {
        // Empty date → publish now; past → backdate; future → schedule
        $publishedAt = $publishedAtInput ?? date('Y-m-d H:i:s');
    } else {
        // Draft can keep a planned date
        $publishedAt = $publishedAtInput;
    }

    if ($id > 0) {
        $stmt = db()->prepare(
            'UPDATE posts SET title = ?, slug = ?, content = ?, status = ?, published_at = ? WHERE id = ?'
        );
        $stmt->execute([$title, $slug, $content, $status, $publishedAt, $id]);
        sync_post_tags($id, parse_tags_input($tagsInput));
        flash('success', $status === 'published' ? 'Запись опубликована.' : 'Черновик сохранён.');
        redirect(url('admin/post/edit/' . $id));
    }

    $stmt = db()->prepare(
        'INSERT INTO posts (title, slug, content, status, published_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$title, $slug, $content, $status, $publishedAt]);
    $newId = (int) db()->lastInsertId();
    sync_post_tags($newId, parse_tags_input($tagsInput));
    flash('success', $status === 'published' ? 'Запись опубликована.' : 'Черновик сохранён.');
    redirect(url('admin/post/edit/' . $newId));
}

$adminTitle = $post ? 'Редактирование' : 'Новая запись';
$publishedLocal = datetime_local_value($post['published_at'] ?? null);
$vis = $post ? post_visibility($post) : ['key' => 'draft', 'label' => 'черновик'];
$adminWithEditor = true;
require __DIR__ . '/_header.php';
?>

<div class="admin-page-head">
    <h1><?= $post ? 'Редактирование' : 'Новая запись' ?></h1>
    <div class="admin-page-head__actions">
        <?php if ($post): ?>
            <span class="badge badge--<?= e($vis['key']) ?>"><?= e($vis['label']) ?></span>
        <?php endif; ?>
        <a class="btn btn--ghost" href="<?= e(url('admin/posts')) ?>">К списку</a>
    </div>
</div>

<form method="post" class="post-form" id="post-form">
    <?= csrf_field() ?>

    <div class="form-row">
        <label for="title">Заголовок</label>
        <input id="title" name="title" required value="<?= e($post['title'] ?? '') ?>" placeholder="Заголовок новости">
    </div>

    <div class="form-row form-row--split">
        <div>
            <label for="slug">ЧПУ (slug)</label>
            <input id="slug" name="slug" value="<?= e($post['slug'] ?? '') ?>" placeholder="auto-from-title">
        </div>
        <div>
            <label for="status">Статус</label>
            <select id="status" name="status">
                <option value="draft" <?= (($post['status'] ?? 'draft') === 'draft') ? 'selected' : '' ?>>Черновик</option>
                <option value="published" <?= (($post['status'] ?? '') === 'published') ? 'selected' : '' ?>>Опубликовано</option>
            </select>
            <p class="field-hint">Или используйте кнопки внизу: «Опубликовать» / «Сохранить черновик».</p>
        </div>
    </div>

    <div class="form-row form-row--split">
        <div>
            <label for="published_at">Дата публикации</label>
            <input id="published_at" name="published_at" type="datetime-local" value="<?= e($publishedLocal) ?>">
            <p class="field-hint">Пусто + «Опубликовано» = сейчас. Прошлая дата — задним числом. Будущая — появится на сайте в указанное время.</p>
        </div>
        <div>
            <label for="tags">Теги</label>
            <input id="tags" name="tags" value="<?= e($tagNames) ?>" placeholder="новости, php, жизнь — через запятую">
        </div>
    </div>

    <div class="form-row">
        <div class="editor-help">
            <span>Markdown + предпросмотр с подсветкой синтаксиса. Разрыв «Читать далее»: кнопка <strong>More</strong> или маркер <code>[more]</code> / <code>&lt;!--more--&gt;</code></span>
        </div>
        <label for="content">Текст</label>
        <textarea id="content" name="content" rows="18"><?= e($post['content'] ?? '') ?></textarea>
    </div>

    <div class="form-actions form-actions--publish">
        <button class="btn btn--publish" type="submit" name="action" value="publish">Опубликовать</button>
        <button class="btn btn--ghost" type="submit" name="action" value="draft">Сохранить черновик</button>
        <button class="btn btn--ghost" type="submit" name="action" value="save">Сохранить</button>
        <?php if ($post && !empty($post['slug'])): ?>
            <a class="btn btn--ghost" href="<?= e(url('post/' . $post['slug'])) ?>" target="_blank" rel="noopener">
                <?= is_post_public($post) ? 'Открыть на сайте' : 'Превью' ?>
            </a>
        <?php endif; ?>
    </div>
</form>

<?php require __DIR__ . '/_footer.php'; ?>
