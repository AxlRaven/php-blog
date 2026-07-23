<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';
require_auth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verify_csrf(is_string($token) ? $token : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$type = ($_POST['type'] ?? 'image') === 'file' ? 'file' : 'image';
$maxSize = 10 * 1024 * 1024; // 10 MB

$allowedImages = [
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];
$allowedFiles = [
    'application/pdf' => 'pdf',
    'text/plain' => 'txt',
    'application/zip' => 'zip',
    'application/x-zip-compressed' => 'zip',
    'application/msword' => 'doc',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    'audio/mpeg' => 'mp3',
    'video/mp4' => 'mp4',
];

function upload_fail(string $message, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function store_upload(string $binary, string $ext, string $subdir): array
{
    $dir = ROOT_PATH . '/uploads/' . $subdir;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        upload_fail('Не удалось создать каталог загрузки', 500);
    }
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    $path = $dir . '/' . $name;
    if (file_put_contents($path, $binary) === false) {
        upload_fail('Не удалось сохранить файл', 500);
    }
    $url = url('uploads/' . $subdir . '/' . $name);
    $thumbUrl = null;
    if ($subdir === 'images') {
        if (create_image_thumb($path)) {
            $thumbUrl = thumb_url_for_image_url($url);
        }
    }
    return ['url' => $url, 'name' => $name, 'thumb' => $thumbUrl];
}

// Paste / base64
if (!empty($_POST['data_url'])) {
    $dataUrl = (string) $_POST['data_url'];
    if (!preg_match('#^data:(image/(?:jpeg|png|gif|webp));base64,(.+)$#s', $dataUrl, $m)) {
        upload_fail('Некорректные данные изображения');
    }
    $mime = $m[1];
    $binary = base64_decode($m[2], true);
    if ($binary === false || strlen($binary) === 0) {
        upload_fail('Не удалось декодировать изображение');
    }
    if (strlen($binary) > $maxSize) {
        upload_fail('Файл слишком большой (макс. 10 МБ)');
    }
    $ext = $allowedImages[$mime] ?? null;
    if (!$ext) {
        upload_fail('Недопустимый тип изображения');
    }
    $stored = store_upload($binary, $ext, 'images');
    echo json_encode([
        'ok' => true,
        'url' => $stored['url'],
        'thumb' => $stored['thumb'] ?? null,
        'name' => $stored['name'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    upload_fail('Файл не получен');
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    upload_fail('Ошибка загрузки файла');
}
if (($file['size'] ?? 0) > $maxSize) {
    upload_fail('Файл слишком большой (макс. 10 МБ)');
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';
$origName = (string) ($file['name'] ?? 'file');

if ($type === 'image') {
    $ext = $allowedImages[$mime] ?? null;
    if (!$ext) {
        upload_fail('Разрешены только JPEG, PNG, GIF, WebP');
    }
    $binary = file_get_contents($file['tmp_name']);
    if ($binary === false) {
        upload_fail('Не удалось прочитать файл', 500);
    }
    $stored = store_upload($binary, $ext, 'images');
    echo json_encode([
        'ok' => true,
        'url' => $stored['url'],
        'thumb' => $stored['thumb'] ?? null,
        'name' => $stored['name'],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$ext = $allowedFiles[$mime] ?? strtolower(pathinfo($origName, PATHINFO_EXTENSION));
$safeExt = preg_replace('/[^a-z0-9]/', '', (string) $ext) ?: 'bin';
$blocked = ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'htaccess', 'js', 'html', 'htm', 'shtml'];
if (in_array($safeExt, $blocked, true)) {
    upload_fail('Этот тип файла запрещён');
}
if (!isset($allowedFiles[$mime]) && !in_array($safeExt, ['pdf', 'txt', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'mp3', 'mp4', 'csv', 'odt', 'ods'], true)) {
    upload_fail('Недопустимый тип файла');
}

$binary = file_get_contents($file['tmp_name']);
if ($binary === false) {
    upload_fail('Не удалось прочитать файл', 500);
}
$stored = store_upload($binary, $safeExt, 'files');
echo json_encode([
    'ok' => true,
    'url' => $stored['url'],
    'name' => $origName,
], JSON_UNESCAPED_UNICODE);
