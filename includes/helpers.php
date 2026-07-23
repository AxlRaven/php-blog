<?php
declare(strict_types=1);

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Store active search query for per-request highlights.
 */
function set_search_highlight_query(?string $query): void
{
    $GLOBALS['search_highlight_query'] = trim((string) $query);
}

function get_search_highlight_query(): string
{
    return trim((string) ($GLOBALS['search_highlight_query'] ?? ''));
}

/**
 * Build regex for search terms highlighting.
 */
function search_highlight_regex(): ?string
{
    static $cacheKey = null;
    static $cacheRegex = null;

    $query = get_search_highlight_query();
    if ($query === '') {
        return null;
    }
    if ($cacheKey === $query) {
        return $cacheRegex;
    }

    $parts = preg_split('/[\s\p{Z}\p{P}\p{S}]+/u', $query) ?: [];
    $terms = [];
    foreach ($parts as $part) {
        $term = trim($part);
        if ($term === '') {
            continue;
        }
        $terms[mb_strtolower($term, 'UTF-8')] = $term;
    }
    if (!$terms) {
        $cacheKey = $query;
        $cacheRegex = null;
        return null;
    }

    $escaped = array_map(static fn(string $t): string => preg_quote($t, '/'), array_values($terms));
    usort($escaped, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
    $cacheKey = $query;
    $cacheRegex = '/(' . implode('|', $escaped) . ')/iu';
    return $cacheRegex;
}

function highlight_plain_text(string $text): string
{
    $safe = e($text);
    $regex = search_highlight_regex();
    if ($regex === null || $safe === '') {
        return $safe;
    }
    return (string) preg_replace($regex, '<mark class="search-hit">$1</mark>', $safe);
}

function highlight_html_text_nodes(string $html): string
{
    $regex = search_highlight_regex();
    if ($regex === null || $html === '') {
        return $html;
    }
    $chunks = preg_split('/(<[^>]+>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!is_array($chunks)) {
        return $html;
    }
    foreach ($chunks as $i => $chunk) {
        if ($chunk === '' || $chunk[0] === '<') {
            continue;
        }
        $chunks[$i] = (string) preg_replace($regex, '<mark class="search-hit">$1</mark>', $chunk);
    }
    return implode('', $chunks);
}

function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    // /admin/posts.php -> /admin, index.php -> ''
    $dir = str_replace('\\', '/', dirname($script));
    if (str_ends_with($dir, '/admin') || str_ends_with($dir, '/api')) {
        $dir = dirname($dir);
    }
    if ($dir === '/' || $dir === '\\' || $dir === '.') {
        $base = '';
    } else {
        $base = rtrim($dir, '/');
    }
    return $base;
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    $base = base_path();
    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }
    return ($base === '' ? '' : $base) . '/' . $path;
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $to): never
{
    header('Location: ' . $to);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf($token)) {
        http_response_code(403);
        exit('Invalid CSRF token');
    }
}

function slugify(string $text): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh','з'=>'z',
        'и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r',
        'с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch',
        'ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'А'=>'a','Б'=>'b','В'=>'v','Г'=>'g','Д'=>'d','Е'=>'e','Ё'=>'yo','Ж'=>'zh','З'=>'z',
        'И'=>'i','Й'=>'y','К'=>'k','Л'=>'l','М'=>'m','Н'=>'n','О'=>'o','П'=>'p','Р'=>'r',
        'С'=>'s','Т'=>'t','У'=>'u','Ф'=>'f','Х'=>'h','Ц'=>'ts','Ч'=>'ch','Ш'=>'sh','Щ'=>'sch',
        'Ъ'=>'','Ы'=>'y','Ь'=>'','Э'=>'e','Ю'=>'yu','Я'=>'ya',
    ];
    $text = strtr($text, $map);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'post-' . time();
}

function unique_slug(string $title, ?int $excludeId = null): string
{
    $base = slugify($title);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM posts WHERE slug = ?';
        $params = [$slug];
        if ($excludeId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }
        $stmt = db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function markdown_to_html(string $markdown): string
{
    static $parser = null;
    if ($parser === null) {
        $parser = new Parsedown();
        $parser->setSafeMode(false);
        $parser->setBreaksEnabled(true);
    }
    $html = $parser->text($markdown);
    // Add language class hints for Prism on fenced code
    $html = preg_replace_callback(
        '/<pre><code class="language-([^"]+)">/i',
        static function ($m) {
            return '<pre class="language-' . e($m[1]) . '"><code class="language-' . e($m[1]) . '">';
        },
        $html
    ) ?? $html;
    return decorate_content_images($html);
}

/**
 * Split content by <!--more--> marker
 * @return array{excerpt: string, full: string, has_more: bool}
 */
function split_more(string $content): array
{
    $marker = '<!--more-->';
    $pos = stripos($content, $marker);
    if ($pos === false) {
        // Also support [more] markdown-friendly marker
        if (preg_match('/\n\s*\[more\]\s*\n/i', $content, $m, PREG_OFFSET_CAPTURE)) {
            $pos = $m[0][1];
            $len = strlen($m[0][0]);
            $excerpt = substr($content, 0, $pos);
            $full = substr($content, 0, $pos) . substr($content, $pos + $len);
            return [
                'excerpt' => trim($excerpt),
                'full' => trim($full),
                'has_more' => true,
            ];
        }
        return ['excerpt' => $content, 'full' => $content, 'has_more' => false];
    }
    $excerpt = substr($content, 0, $pos);
    $full = substr($content, 0, $pos) . substr($content, $pos + strlen($marker));
    return [
        'excerpt' => trim($excerpt),
        'full' => trim($full),
        'has_more' => true,
    ];
}

function format_date(?string $datetime): string
{
    if (!$datetime) {
        return '';
    }
    $ts = strtotime($datetime);
    if ($ts === false) {
        return '';
    }
    $months = [
        1 => 'января', 2 => 'февраля', 3 => 'марта', 4 => 'апреля',
        5 => 'мая', 6 => 'июня', 7 => 'июля', 8 => 'августа',
        9 => 'сентября', 10 => 'октября', 11 => 'ноября', 12 => 'декабря',
    ];
    $d = (int) date('j', $ts);
    $m = $months[(int) date('n', $ts)];
    $y = date('Y', $ts);
    $hm = date('H:i', $ts);
    return "{$d} {$m} {$y}, {$hm}";
}

/**
 * Parse HTML datetime-local / common datetime strings into MySQL DATETIME.
 */
function parse_datetime_local(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $value = str_replace('T', ' ', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d H:i:s', $ts);
}

function datetime_local_value(?string $mysqlDatetime): string
{
    if (!$mysqlDatetime) {
        return '';
    }
    $ts = strtotime($mysqlDatetime);
    if ($ts === false) {
        return '';
    }
    return date('Y-m-d\TH:i', $ts);
}

function is_post_public(array $post): bool
{
    if (($post['status'] ?? '') !== 'published') {
        return false;
    }
    if (empty($post['published_at'])) {
        return false;
    }
    $ts = strtotime($post['published_at']);
    return $ts !== false && $ts <= time();
}

/**
 * @return array{key: string, label: string}
 */
function post_visibility(array $post): array
{
    if (($post['status'] ?? '') !== 'published') {
        return ['key' => 'draft', 'label' => 'черновик'];
    }
    if (empty($post['published_at'])) {
        return ['key' => 'scheduled', 'label' => 'отложено'];
    }
    $ts = strtotime($post['published_at']);
    if ($ts === false || $ts > time()) {
        return ['key' => 'scheduled', 'label' => 'отложено'];
    }
    return ['key' => 'published', 'label' => 'опубликовано'];
}

/** SQL fragment: post is visible on the public site right now */
function sql_public_posts(string $alias = 'p'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';
    return "{$prefix}status = 'published'
            AND {$prefix}published_at IS NOT NULL
            AND {$prefix}published_at <= NOW()";
}

function site_title(): string
{
    return setting('site_title', $GLOBALS['config']['site']['title'] ?? 'Блог') ?? 'Блог';
}

function site_description(): string
{
    return setting('site_description', $GLOBALS['config']['site']['description'] ?? '') ?? '';
}

/** Public URL of site logo, or null if missing. Prefers uploads/images/logo.png. */
function site_logo_url(): ?string
{
    $candidates = [
        [branding_logo_path(), 'uploads/images/logo.png'],
        [ROOT_PATH . '/assets/images/logo.png', 'assets/images/logo.png'],
    ];
    foreach ($candidates as [$fs, $rel]) {
        if (!is_file($fs)) {
            continue;
        }
        $base = str_starts_with($rel, 'assets/')
            ? asset(substr($rel, strlen('assets/')))
            : url($rel);
        return $base . '?v=' . (string) filemtime($fs);
    }
    return null;
}

function site_has_logo(): bool
{
    return site_logo_url() !== null;
}

/** Public URL of OG cover (uploads/images/cover.jpg), or null. */
function site_cover_url(): ?string
{
    $path = branding_cover_path();
    if (!is_file($path)) {
        return null;
    }
    return url('uploads/images/cover.jpg') . '?v=' . (string) filemtime($path);
}

/**
 * Leftover installer / update artifacts that should be deleted from the server.
 *
 * @return list<string>
 */
function admin_leftover_install_files(): array
{
    $found = [];
    if (is_file(ROOT_PATH . '/install.php')) {
        $found[] = 'install.php';
    }
    foreach (glob(ROOT_PATH . '/update_*.php') ?: [] as $file) {
        $found[] = basename($file);
    }
    foreach (glob(ROOT_PATH . '/update_*', GLOB_ONLYDIR) ?: [] as $dir) {
        $found[] = basename($dir) . '/';
    }
    sort($found);
    return array_values(array_unique($found));
}

function posts_per_page(): int
{
    $n = (int) (setting('posts_per_page', '10') ?? '10');
    return max(1, min(100, $n));
}

function load_mode(): string
{
    $mode = setting('load_mode', 'pagination') ?? 'pagination';
    return in_array($mode, ['pagination', 'infinite'], true) ? $mode : 'pagination';
}

function parse_tags_input(string $input): array
{
    $parts = preg_split('/[,;]+/u', $input) ?: [];
    $tags = [];
    foreach ($parts as $p) {
        $name = trim($p);
        if ($name === '') {
            continue;
        }
        $slug = slugify($name);
        $tags[$slug] = $name;
    }
    return array_values(array_map(
        static fn($slug, $name) => ['slug' => $slug, 'name' => $name],
        array_keys($tags),
        $tags
    ));
}

function sync_post_tags(int $postId, array $tags): void
{
    db()->prepare('DELETE FROM post_tags WHERE post_id = ?')->execute([$postId]);
    foreach ($tags as $tag) {
        $stmt = db()->prepare('SELECT id FROM tags WHERE slug = ? LIMIT 1');
        $stmt->execute([$tag['slug']]);
        $existing = $stmt->fetch();
        if ($existing) {
            $tagId = (int) $existing['id'];
            db()->prepare('UPDATE tags SET name = ? WHERE id = ?')->execute([$tag['name'], $tagId]);
        } else {
            $ins = db()->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
            $ins->execute([$tag['name'], $tag['slug']]);
            $tagId = (int) db()->lastInsertId();
        }
        db()->prepare('INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (?, ?)')
            ->execute([$postId, $tagId]);
    }
}

function get_post_tags(int $postId): array
{
    $stmt = db()->prepare(
        'SELECT t.id, t.name, t.slug FROM tags t
         INNER JOIN post_tags pt ON pt.tag_id = t.id
         WHERE pt.post_id = ?
         ORDER BY t.name'
    );
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
}

function get_all_tags(): array
{
    $public = sql_public_posts('p');
    return db()->query(
        "SELECT t.id, t.name, t.slug, COUNT(p.id) AS post_count
         FROM tags t
         INNER JOIN post_tags pt ON pt.tag_id = t.id
         INNER JOIN posts p ON p.id = pt.post_id AND {$public}
         GROUP BY t.id, t.name, t.slug
         HAVING post_count > 0
         ORDER BY t.name"
    )->fetchAll();
}

/**
 * Tag is visible publicly if at least one live post uses it.
 */
function get_public_tag_by_slug(string $slug): ?array
{
    $public = sql_public_posts('p');
    $stmt = db()->prepare(
        "SELECT t.id, t.name, t.slug, COUNT(p.id) AS post_count
         FROM tags t
         INNER JOIN post_tags pt ON pt.tag_id = t.id
         INNER JOIN posts p ON p.id = pt.post_id AND {$public}
         WHERE t.slug = ?
         GROUP BY t.id, t.name, t.slug
         HAVING post_count > 0
         LIMIT 1"
    );
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return array{items: array, total: int, page: int, pages: int, per_page: int}
 */
function fetch_posts(int $page = 1, ?string $tagSlug = null, ?string $query = null): array
{
    $perPage = posts_per_page();
    $page = max(1, $page);
    $offset = ($page - 1) * $perPage;

    $where = [sql_public_posts('p')];
    $params = [];
    $join = '';

    if ($tagSlug) {
        $join .= ' INNER JOIN post_tags pt ON pt.post_id = p.id
                   INNER JOIN tags t ON t.id = pt.tag_id AND t.slug = ?';
        $params[] = $tagSlug;
    }

    if ($query !== null && $query !== '') {
        $where[] = '(p.title LIKE ? OR p.content LIKE ?)';
        $like = '%' . $query . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $whereSql = implode(' AND ', $where);

    $countSql = "SELECT COUNT(DISTINCT p.id) FROM posts p {$join} WHERE {$whereSql}";
    $stmt = db()->prepare($countSql);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $sql = "SELECT DISTINCT p.* FROM posts p {$join}
            WHERE {$whereSql}
            ORDER BY p.published_at DESC, p.id DESC
            LIMIT {$perPage} OFFSET {$offset}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item['tags'] = get_post_tags((int) $item['id']);
    }
    unset($item);

    return [
        'items' => $items,
        'total' => $total,
        'page' => $page,
        'pages' => max(1, (int) ceil($total / $perPage)),
        'per_page' => $perPage,
    ];
}

function get_post_by_slug(string $slug, bool $includeHidden = false): ?array
{
    if ($includeHidden) {
        $stmt = db()->prepare('SELECT * FROM posts WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
    } else {
        $stmt = db()->prepare(
            'SELECT * FROM posts WHERE slug = ? AND ' . sql_public_posts('') . ' LIMIT 1'
        );
        $stmt->execute([$slug]);
    }
    $post = $stmt->fetch();
    if (!$post) {
        return null;
    }
    $post['tags'] = get_post_tags((int) $post['id']);
    return $post;
}

function render_views_badge(int $views): string
{
    $n = max(0, $views);
    return '<span class="post-views" title="Просмотры">'
        . '<svg class="post-views__icon" width="11" height="11" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path fill="currentColor" d="M12 5C7 5 2.73 8.11 1 12.5 2.73 16.89 7 20 12 20s9.27-3.11 11-7.5C21.27 8.11 17 5 12 5zm0 11.5a4 4 0 1 1 0-8 4 4 0 0 1 0 8zm0-6.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5z"/>'
        . '</svg>'
        . '<span class="post-views__count">' . $n . '</span>'
        . '</span>';
}

function rating_voter_key(): string
{
    if (empty($_SESSION['rating_voter'])) {
        $_SESSION['rating_voter'] = bin2hex(random_bytes(16));
    }
    return hash('sha256', (string) $_SESSION['rating_voter']);
}

/**
 * @return array{average: float, count: int, sum: int}
 */
function post_rating_stats(array $post): array
{
    $count = max(0, (int) ($post['rating_count'] ?? 0));
    $sum = max(0, (int) ($post['rating_sum'] ?? 0));
    $average = $count > 0 ? round($sum / $count, 1) : 0.0;
    return ['average' => $average, 'count' => $count, 'sum' => $sum];
}

function get_user_post_rating(int $postId): ?int
{
    if ($postId < 1) {
        return null;
    }
    $stmt = db()->prepare('SELECT rating FROM post_ratings WHERE post_id = ? AND voter_key = ? LIMIT 1');
    $stmt->execute([$postId, rating_voter_key()]);
    $value = $stmt->fetchColumn();
    return $value !== false ? (int) $value : null;
}

/**
 * @return array{ok: bool, average?: float, count?: int, user_rating?: int, error?: string}
 */
function submit_post_rating(int $postId, int $rating): array
{
    if ($postId < 1 || $rating < 1 || $rating > 5) {
        return ['ok' => false, 'error' => 'Некорректная оценка'];
    }

    $stmt = db()->prepare(
        'SELECT id FROM posts WHERE id = ? AND ' . sql_public_posts('') . ' LIMIT 1'
    );
    $stmt->execute([$postId]);
    if (!$stmt->fetch()) {
        return ['ok' => false, 'error' => 'Запись не найдена'];
    }

    if (get_user_post_rating($postId) !== null) {
        return ['ok' => false, 'error' => 'Вы уже оценили эту запись'];
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO post_ratings (post_id, voter_key, rating) VALUES (?, ?, ?)')
            ->execute([$postId, rating_voter_key(), $rating]);
        $pdo->prepare('UPDATE posts SET rating_sum = rating_sum + ?, rating_count = rating_count + 1 WHERE id = ?')
            ->execute([$rating, $postId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['ok' => false, 'error' => 'Не удалось сохранить оценку'];
    }

    $stmt = $pdo->prepare('SELECT rating_sum, rating_count FROM posts WHERE id = ? LIMIT 1');
    $stmt->execute([$postId]);
    $row = $stmt->fetch() ?: ['rating_sum' => 0, 'rating_count' => 0];
    $stats = post_rating_stats($row);

    return [
        'ok' => true,
        'average' => $stats['average'],
        'count' => $stats['count'],
        'user_rating' => $rating,
    ];
}

function render_rating_badge(array $post): string
{
    $stats = post_rating_stats($post);
    if ($stats['count'] < 1) {
        return '';
    }
    return '<span class="post-rating-badge" title="Средний рейтинг">'
        . '<span class="post-rating-badge__star" aria-hidden="true">★</span>'
        . '<span class="post-rating-badge__value">' . e(number_format($stats['average'], 1, '.', '')) . '</span>'
        . '<span class="post-rating-badge__count">(' . (int) $stats['count'] . ')</span>'
        . '</span>';
}

function render_post_rating(array $post, bool $interactive = false): string
{
    $postId = (int) ($post['id'] ?? 0);
    $stats = post_rating_stats($post);
    $userRating = get_user_post_rating($postId);
    $canRate = $interactive && $userRating === null;

    ob_start();
    ?>
    <div class="post-rating<?= $interactive ? ' post-rating--interactive' : '' ?>"
         data-post-id="<?= $postId ?>"
         data-user-rating="<?= $userRating !== null ? (int) $userRating : '' ?>"
         itemscope itemtype="https://schema.org/AggregateRating"
         itemprop="aggregateRating">
        <meta itemprop="bestRating" content="5">
        <meta itemprop="worstRating" content="1">
        <meta itemprop="ratingValue" content="<?= e(number_format($stats['average'], 1, '.', '')) ?>">
        <meta itemprop="ratingCount" content="<?= (int) $stats['count'] ?>">
        <span class="post-rating__label"><?= $canRate ? 'Оцените запись:' : 'Рейтинг:' ?></span>
        <div class="post-rating__stars" role="<?= $canRate ? 'group' : 'img' ?>"
             aria-label="<?= e($stats['count'] > 0
                 ? 'Рейтинг ' . number_format($stats['average'], 1, '.', '') . ' из 5, ' . $stats['count'] . ' оценок'
                 : 'Пока нет оценок') ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <?php
                $filled = $stats['count'] > 0 && $i <= (int) round($stats['average']);
                ?>
                <?php if ($canRate): ?>
                    <button type="button" class="post-rating__star<?= $filled ? ' is-filled' : '' ?>"
                            data-rating="<?= $i ?>" aria-label="<?= $i ?> из 5">★</button>
                <?php else: ?>
                    <span class="post-rating__star<?= $filled ? ' is-filled' : '' ?>" aria-hidden="true">★</span>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        <span class="post-rating__summary">
            <?php if ($stats['count'] > 0): ?>
                <strong class="post-rating__average"><?= e(number_format($stats['average'], 1, '.', '')) ?></strong>
                <span class="post-rating__count">(<?= (int) $stats['count'] ?> <?= rating_count_label((int) $stats['count']) ?>)</span>
            <?php else: ?>
                <span class="post-rating__empty">Пока нет оценок</span>
            <?php endif; ?>
        </span>
        <?php if ($userRating !== null): ?>
            <span class="post-rating__thanks">Спасибо! Ваша оценка: <?= (int) $userRating ?></span>
        <?php endif; ?>
        <span class="post-rating__message" aria-live="polite"></span>
    </div>
    <?php
    return (string) ob_get_clean();
}

function rating_count_label(int $count): string
{
    $n = abs($count) % 100;
    $n1 = $n % 10;
    if ($n > 10 && $n < 20) {
        return 'оценок';
    }
    if ($n1 === 1) {
        return 'оценка';
    }
    if ($n1 >= 2 && $n1 <= 4) {
        return 'оценки';
    }
    return 'оценок';
}

/**
 * Count one view per session for a public post page.
 */
function record_post_view(int $postId): void
{
    if ($postId < 1) {
        return;
    }
    if (!isset($_SESSION['viewed_posts']) || !is_array($_SESSION['viewed_posts'])) {
        $_SESSION['viewed_posts'] = [];
    }
    if (!empty($_SESSION['viewed_posts'][$postId])) {
        return;
    }
    $_SESSION['viewed_posts'][$postId] = time();
    db()->prepare('UPDATE posts SET views = views + 1 WHERE id = ?')->execute([$postId]);
}

function render_post_card(array $post, bool $full = false, bool $interactiveRating = false): string
{
    $split = split_more($post['content']);
    $bodyMd = $full ? $split['full'] : $split['excerpt'];
    $html = markdown_to_html($bodyMd);
    if (!$full) {
        $html = highlight_html_text_nodes($html);
    }
    $permalink = url('post/' . $post['slug']);
    $views = (int) ($post['views'] ?? 0);
    $title = highlight_plain_text((string) ($post['title'] ?? ''));

    ob_start();
    ?>
    <article class="post-card" id="post-<?= (int) $post['id'] ?>">
        <header class="post-card__header">
            <h2 class="post-card__title">
                <?php if ($full): ?>
                    <?= $title ?>
                <?php else: ?>
                    <a href="<?= e($permalink) ?>"><?= $title ?></a>
                <?php endif; ?>
            </h2>
            <div class="post-card__meta">
                <time class="post-card__date" datetime="<?= e($post['published_at'] ?? $post['created_at']) ?>">
                    <?= e(format_date($post['published_at'] ?? $post['created_at'])) ?>
                </time>
                <div class="post-card__meta-end">
                    <?= render_rating_badge($post) ?>
                    <?= render_views_badge($views) ?>
                </div>
            </div>
            <?php if (!empty($post['tags'])): ?>
                <ul class="tag-list">
                    <?php foreach ($post['tags'] as $tag): ?>
                        <li><a class="tag" href="<?= e(url('tag/' . $tag['slug'])) ?>"><?= e($tag['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </header>
        <div class="post-card__body content">
            <?= $html ?>
        </div>
        <?php if ($full): ?>
            <footer class="post-card__footer post-card__footer--rating">
                <?= render_post_rating($post, $interactiveRating) ?>
            </footer>
        <?php elseif ($split['has_more']): ?>
            <footer class="post-card__footer">
                <a class="read-more" href="<?= e($permalink) ?>">Читать далее →</a>
            </footer>
        <?php endif; ?>
    </article>
    <?php
    return (string) ob_get_clean();
}
