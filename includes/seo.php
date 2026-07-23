<?php
declare(strict_types=1);

/**
 * SEO helpers: meta tags, Open Graph, Twitter Cards, JSON-LD.
 */

function site_url(): string
{
    $configured = trim((string) (setting('site_url', '') ?? ''));
    if ($configured !== '') {
        return rtrim($configured, '/');
    }
    $fallback = trim((string) ($GLOBALS['config']['site']['url'] ?? ''));
    if ($fallback !== '') {
        return rtrim($fallback, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $base = base_path();
    return rtrim(($https ? 'https' : 'http') . '://' . $host . $base, '/');
}

function absolute_url(string $route = ''): string
{
    if ($route !== '' && preg_match('#^https?://#i', $route)) {
        return $route;
    }
    if ($route !== '' && str_contains($route, '?')) {
        [$path, $query] = explode('?', $route, 2);
        return absolute_url($path) . '?' . $query;
    }
    $rel = $route === '' ? url() : url($route);
    if (preg_match('#^https?://#i', $rel)) {
        return $rel;
    }
    return rtrim(site_url(), '/') . $rel;
}

function seo_keywords(): string
{
    return trim((string) (setting('site_keywords', '') ?? ''));
}

function seo_author(): string
{
    $author = trim((string) (setting('seo_author', '') ?? ''));
    return $author !== '' ? $author : site_title();
}

function seo_organization(): string
{
    $org = trim((string) (setting('seo_organization', '') ?? ''));
    return $org !== '' ? $org : site_title();
}

function seo_twitter_site(): string
{
    $handle = trim((string) (setting('seo_twitter', '') ?? ''));
    return ltrim($handle, '@');
}

function seo_default_og_image(): string
{
    $img = trim((string) (setting('seo_og_image', '') ?? ''));
    if ($img === '' && is_file(ROOT_PATH . '/uploads/images/cover.jpg')) {
        $img = '/uploads/images/cover.jpg';
    }
    if ($img === '') {
        return '';
    }
    return seo_normalize_image_url($img);
}

/**
 * Absolute HTTPS-friendly image URL for Open Graph (never thumbs).
 */
function seo_normalize_image_url(string $src): string
{
    $src = trim(html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($src === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $src)) {
        return $src;
    }
    if ($src[0] !== '/') {
        $src = '/' . ltrim($src, '/');
    }
    $src = preg_replace('#/uploads/images/thumbs/#', '/uploads/images/', $src) ?? $src;
    return absolute_url($src);
}

/**
 * @return array{width:int,height:int,type:string}|null
 */
function seo_image_dimensions(string $imageUrl): ?array
{
    $fs = uploads_path_from_url($imageUrl);
    if ($fs === null || !is_file($fs)) {
        return null;
    }
    $info = @getimagesize($fs);
    if (!$info || empty($info[0]) || empty($info[1])) {
        return null;
    }
    return [
        'width' => (int) $info[0],
        'height' => (int) $info[1],
        'type' => (string) ($info['mime'] ?? 'image/jpeg'),
    ];
}

function seo_strlen(string $text): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($text, 'UTF-8');
    }
    preg_match_all('/./us', $text, $m);
    return count($m[0] ?? []);
}

function seo_substr(string $text, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null
            ? mb_substr($text, $start, null, 'UTF-8')
            : mb_substr($text, $start, $length, 'UTF-8');
    }
    preg_match_all('/./us', $text, $m);
    $chars = $m[0] ?? [];
    if ($start > 0) {
        $chars = array_slice($chars, $start);
    }
    if ($length !== null) {
        $chars = array_slice($chars, 0, max(0, $length));
    }
    return implode('', $chars);
}

function seo_strrpos(string $haystack, string $needle): int|false
{
    if (function_exists('mb_strrpos')) {
        $pos = mb_strrpos($haystack, $needle, 0, 'UTF-8');
        return $pos === false ? false : (int) $pos;
    }
    $pos = strrpos($haystack, $needle);
    return $pos === false ? false : $pos;
}

function seo_truncate(?string $text, int $max = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)) ?? '');
    if ($text === '') {
        return '';
    }

    $ellipsis = '…';
    if (seo_strlen($text) <= $max) {
        return $text;
    }

    $limit = max(1, $max - seo_strlen($ellipsis));
    $chunk = seo_substr($text, 0, $limit);
    $lastSpace = seo_strrpos($chunk, ' ');
    if ($lastSpace !== false && $lastSpace >= (int) ($limit * 0.4)) {
        $chunk = seo_substr($chunk, 0, $lastSpace);
    }

    return rtrim($chunk, ".,;:!?-–—") . $ellipsis;
}

/**
 * @param array<string, mixed> $options
 */
function seo_configure(array $options): void
{
    $current = $GLOBALS['seo'] ?? [];
    if (!is_array($current)) {
        $current = [];
    }
    $GLOBALS['seo'] = array_merge($current, $options);
}

/**
 * @return array<string, mixed>
 */
function seo_state(): array
{
    return is_array($GLOBALS['seo'] ?? null) ? $GLOBALS['seo'] : [];
}

function seo_post_og_image(array $post): string
{
    $content = (string) ($post['content'] ?? '');
    if (preg_match('/!\[[^\]]*\]\(([^)]+)\)/u', $content, $m)) {
        $src = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($src !== '') {
            return seo_normalize_image_url($src);
        }
    }
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/iu', markdown_to_html($content), $m)) {
        $src = trim(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($src !== '') {
            return seo_normalize_image_url($src);
        }
    }
    return seo_default_og_image();
}

function seo_keywords_from_tags(array $tags): string
{
    $names = array_values(array_filter(array_map(
        static fn(array $tag): string => trim((string) ($tag['name'] ?? '')),
        $tags
    )));
    return implode(', ', $names);
}

function seo_merge_keywords(?string $extra = null): string
{
    $parts = array_filter(array_map('trim', [
        seo_keywords(),
        (string) $extra,
    ]));
    $unique = [];
    foreach ($parts as $part) {
        foreach (preg_split('/\s*,\s*/u', $part) ?: [] as $word) {
            $word = trim($word);
            if ($word === '') {
                continue;
            }
            $key = mb_strtolower($word, 'UTF-8');
            $unique[$key] = $word;
        }
    }
    return implode(', ', array_values($unique));
}

/**
 * @return list<array{loc:string,lastmod?:string,changefreq?:string,priority?:string}>
 */
function seo_sitemap_urls(): array
{
    $urls = [
        ['loc' => absolute_url(), 'changefreq' => 'daily', 'priority' => '1.0'],
        ['loc' => absolute_url('tags'), 'changefreq' => 'weekly', 'priority' => '0.5'],
    ];

    $public = sql_public_posts('p');
    $posts = db()->query(
        "SELECT slug, published_at, updated_at
         FROM posts p
         WHERE {$public}
         ORDER BY published_at DESC"
    )->fetchAll();

    foreach ($posts as $post) {
        $lastmod = $post['updated_at'] ?? $post['published_at'] ?? null;
        $urls[] = [
            'loc' => absolute_url('post/' . $post['slug']),
            'lastmod' => $lastmod ? date('c', strtotime((string) $lastmod)) : null,
            'changefreq' => 'monthly',
            'priority' => '0.8',
        ];
    }

    foreach (get_all_tags() as $tag) {
        $urls[] = [
            'loc' => absolute_url('tag/' . $tag['slug']),
            'changefreq' => 'weekly',
            'priority' => '0.6',
        ];
    }

    return $urls;
}

/**
 * @return list<array<string, mixed>>
 */
function seo_rss_items(int $limit = 20): array
{
    $public = sql_public_posts('p');
    $stmt = db()->prepare(
        "SELECT p.* FROM posts p
         WHERE {$public}
         ORDER BY p.published_at DESC
         LIMIT " . max(1, min(50, $limit))
    );
    $stmt->execute();
    $items = $stmt->fetchAll();
    foreach ($items as &$item) {
        $item['tags'] = get_post_tags((int) $item['id']);
    }
    unset($item);
    return $items;
}

function seo_render_head(): void
{
    global $pageTitle, $pageDescription, $isPreview;

    $seo = seo_state();
    $title = (string) ($seo['title'] ?? $pageTitle ?? site_title());
    $ogTitle = (string) ($seo['og_title'] ?? $title);
    $description = seo_truncate((string) ($seo['description'] ?? $pageDescription ?? site_description()), 160);
    $keywords = seo_merge_keywords(isset($seo['keywords']) ? (string) $seo['keywords'] : null);
    $robots = (string) ($seo['robots'] ?? (!empty($isPreview) ? 'noindex,nofollow' : 'index,follow,max-image-preview:large'));
    $type = (string) ($seo['type'] ?? 'website');
    $canonicalRoute = (string) ($seo['canonical'] ?? '');
    $canonical = $canonicalRoute !== '' ? absolute_url($canonicalRoute) : absolute_url();
    $image = (string) ($seo['image'] ?? seo_default_og_image());
    $prev = isset($seo['prev']) && $seo['prev'] !== null ? absolute_url((string) $seo['prev']) : null;
    $next = isset($seo['next']) && $seo['next'] !== null ? absolute_url((string) $seo['next']) : null;
    $breadcrumbs = is_array($seo['breadcrumbs'] ?? null) ? $seo['breadcrumbs'] : [];
    $article = is_array($seo['article'] ?? null) ? $seo['article'] : [];

    echo '<title>' . e($title) . "</title>\n";
    if ($description !== '') {
        echo '<meta name="description" content="' . e($description) . "\">\n";
    }
    if ($keywords !== '') {
        echo '<meta name="keywords" content="' . e($keywords) . "\">\n";
    }
    echo '<meta name="author" content="' . e(seo_author()) . "\">\n";
    echo '<meta name="robots" content="' . e($robots) . "\">\n";
    echo '<meta name="googlebot" content="' . e($robots) . "\">\n";
    echo '<link rel="canonical" href="' . e($canonical) . "\">\n";
    if ($prev) {
        echo '<link rel="prev" href="' . e($prev) . "\">\n";
    }
    if ($next) {
        echo '<link rel="next" href="' . e($next) . "\">\n";
    }

    $google = trim((string) (setting('google_site_verification', '') ?? ''));
    if ($google !== '') {
        echo '<meta name="google-site-verification" content="' . e($google) . "\">\n";
    }
    $yandex = trim((string) (setting('yandex_verification', '') ?? ''));
    if ($yandex !== '') {
        echo '<meta name="yandex-verification" content="' . e($yandex) . "\">\n";
    }

    echo '<meta property="og:locale" content="ru_RU">' . "\n";
    echo '<meta property="og:site_name" content="' . e(site_title()) . "\">\n";
    echo '<meta property="og:title" content="' . e($ogTitle) . "\">\n";
    if ($description !== '') {
        echo '<meta property="og:description" content="' . e($description) . "\">\n";
    }
    echo '<meta property="og:url" content="' . e($canonical) . "\">\n";
    echo '<meta property="og:type" content="' . e($type === 'article' ? 'article' : 'website') . "\">\n";
    if ($image !== '') {
        $dims = seo_image_dimensions($image);
        echo '<meta property="og:image" content="' . e($image) . "\">\n";
        if (str_starts_with($image, 'https://')) {
            echo '<meta property="og:image:secure_url" content="' . e($image) . "\">\n";
        }
        echo '<meta property="og:image:alt" content="' . e($ogTitle) . "\">\n";
        if ($dims) {
            echo '<meta property="og:image:width" content="' . (int) $dims['width'] . "\">\n";
            echo '<meta property="og:image:height" content="' . (int) $dims['height'] . "\">\n";
            echo '<meta property="og:image:type" content="' . e($dims['type']) . "\">\n";
        }
    }
    if ($type === 'article' && !empty($article['datePublished'])) {
        echo '<meta property="article:published_time" content="' . e(date('c', strtotime((string) $article['datePublished']))) . "\">\n";
    }
    if ($type === 'article' && !empty($article['dateModified'])) {
        echo '<meta property="article:modified_time" content="' . e(date('c', strtotime((string) $article['dateModified']))) . "\">\n";
    }
    if ($type === 'article' && !empty($article['author'])) {
        echo '<meta property="article:author" content="' . e((string) $article['author']) . "\">\n";
    }
    if ($type === 'article' && !empty($article['tags']) && is_array($article['tags'])) {
        foreach ($article['tags'] as $tagName) {
            $tagName = trim((string) $tagName);
            if ($tagName !== '') {
                echo '<meta property="article:tag" content="' . e($tagName) . "\">\n";
            }
        }
    }

    echo '<meta name="twitter:card" content="' . e($image !== '' ? 'summary_large_image' : 'summary') . "\">\n";
    $twitter = seo_twitter_site();
    if ($twitter !== '') {
        echo '<meta name="twitter:site" content="@' . e($twitter) . "\">\n";
        echo '<meta name="twitter:creator" content="@' . e($twitter) . "\">\n";
    }
    echo '<meta name="twitter:title" content="' . e($ogTitle) . "\">\n";
    if ($description !== '') {
        echo '<meta name="twitter:description" content="' . e($description) . "\">\n";
    }
    if ($image !== '') {
        echo '<meta name="twitter:image" content="' . e($image) . "\">\n";
    }

    echo '<link rel="alternate" type="application/rss+xml" title="' . e(site_title()) . '" href="' . e(absolute_url('feed')) . "\">\n";

    $jsonLd = [];
    $jsonLd[] = [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => site_title(),
        'description' => site_description(),
        'url' => absolute_url(),
        'inLanguage' => 'ru-RU',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => absolute_url('search') . '?q={search_term_string}',
            'query-input' => 'required name=search_term_string',
        ],
    ];

    if ($type === 'article' && $article !== []) {
        $blogPosting = [
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => (string) ($article['headline'] ?? $title),
            'description' => $description,
            'url' => $canonical,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $canonical],
            'datePublished' => !empty($article['datePublished'])
                ? date('c', strtotime((string) $article['datePublished']))
                : null,
            'dateModified' => !empty($article['dateModified'])
                ? date('c', strtotime((string) $article['dateModified']))
                : null,
            'author' => [
                '@type' => 'Person',
                'name' => (string) ($article['author'] ?? seo_author()),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => seo_organization(),
                'url' => absolute_url(),
            ],
            'inLanguage' => 'ru-RU',
            'keywords' => $keywords,
        ];
        if ($image !== '') {
            $blogPosting['image'] = [$image];
        }
        if (!empty($article['tags']) && is_array($article['tags'])) {
            $blogPosting['articleSection'] = implode(', ', array_map('strval', $article['tags']));
        }
        $ratingCount = (int) ($article['ratingCount'] ?? 0);
        $ratingValue = (float) ($article['ratingValue'] ?? 0);
        if ($ratingCount > 0 && $ratingValue > 0) {
            $blogPosting['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => number_format($ratingValue, 1, '.', ''),
                'bestRating' => '5',
                'worstRating' => '1',
                'ratingCount' => (string) $ratingCount,
            ];
        }
        $jsonLd[] = array_filter($blogPosting, static fn($v) => $v !== null && $v !== '');
    }

    if ($breadcrumbs !== []) {
        $items = [];
        $pos = 1;
        foreach ($breadcrumbs as $crumb) {
            if (!is_array($crumb)) {
                continue;
            }
            $label = (string) ($crumb[0] ?? $crumb['label'] ?? '');
            $href = (string) ($crumb[1] ?? $crumb['url'] ?? '');
            if ($label === '') {
                continue;
            }
            $entry = [
                '@type' => 'ListItem',
                'position' => $pos++,
                'name' => $label,
            ];
            if ($href !== '') {
                $entry['item'] = preg_match('#^https?://#i', $href) ? $href : absolute_url($href);
            }
            $items[] = $entry;
        }
        if ($items !== []) {
            $jsonLd[] = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => $items,
            ];
        }
    }

    echo '<script type="application/ld+json">' . json_encode(
        count($jsonLd) === 1 ? $jsonLd[0] : $jsonLd,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    ) . "</script>\n";
}
