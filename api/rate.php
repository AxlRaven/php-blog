<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    $data = $_POST;
}

$postId = (int) ($data['post_id'] ?? 0);
$rating = (int) ($data['rating'] ?? 0);

$result = submit_post_rating($postId, $rating);
if (!$result['ok']) {
    http_response_code($result['error'] === 'Вы уже оценили эту запись' ? 409 : 400);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
