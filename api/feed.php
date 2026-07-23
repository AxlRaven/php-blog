<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

$page = max(1, (int) ($_GET['page'] ?? 1));
$tag = isset($_GET['tag']) ? trim((string) $_GET['tag']) : null;
$query = isset($_GET['q']) ? trim((string) $_GET['q']) : null;

$result = fetch_posts($page, $tag ?: null, $query ?: null);

$html = '';
foreach ($result['items'] as $post) {
    $html .= render_post_card($post, false);
}

echo json_encode([
    'ok' => true,
    'page' => $result['page'],
    'pages' => $result['pages'],
    'total' => $result['total'],
    'html' => $html,
    'has_more' => $result['page'] < $result['pages'],
], JSON_UNESCAPED_UNICODE);
