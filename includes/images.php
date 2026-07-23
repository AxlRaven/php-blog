<?php
declare(strict_types=1);

/** Max thumb box (fit, keep aspect ratio — no crop). */
const THUMB_MAX_W = 1200;
const THUMB_MAX_H = 1600;

function images_gd_available(): bool
{
    return extension_loaded('gd') && function_exists('imagecreatetruecolor');
}

/**
 * Map a public uploads URL to an absolute filesystem path, or null if outside uploads.
 */
function uploads_path_from_url(string $url): ?string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return null;
    }
    $path = str_replace('\\', '/', $path);
    $marker = '/uploads/';
    $pos = strpos($path, $marker);
    if ($pos === false) {
        return null;
    }
    $rel = substr($path, $pos + strlen($marker));
    $rel = ltrim(str_replace('..', '', $rel), '/');
    if ($rel === '' || str_contains($rel, '..')) {
        return null;
    }
    $full = ROOT_PATH . '/uploads/' . $rel;
    return is_file($full) ? $full : null;
}

function thumb_path_for_image(string $imageFsPath): string
{
    $dir = dirname($imageFsPath) . '/thumbs';
    return $dir . '/' . basename($imageFsPath);
}

function thumb_url_for_image_url(string $imageUrl): ?string
{
    $path = parse_url($imageUrl, PHP_URL_PATH);
    if (!is_string($path)) {
        return null;
    }
    $path = str_replace('\\', '/', $path);
    if (!str_contains($path, '/uploads/images/') || str_contains($path, '/uploads/images/thumbs/')) {
        return null;
    }
    return preg_replace('#/uploads/images/#', '/uploads/images/thumbs/', $path, 1);
}

/**
 * Create a proportional thumb (fit inside box, no cropping). Returns true on success.
 */
function create_image_thumb(string $sourcePath, ?string $destPath = null, int $maxW = THUMB_MAX_W, int $maxH = THUMB_MAX_H): bool
{
    if (!images_gd_available() || !is_file($sourcePath)) {
        return false;
    }

    $destPath = $destPath ?? thumb_path_for_image($sourcePath);
    $destDir = dirname($destPath);
    if (!is_dir($destDir) && !mkdir($destDir, 0755, true) && !is_dir($destDir)) {
        return false;
    }

    $info = @getimagesize($sourcePath);
    if (!$info) {
        return false;
    }
    [$srcW, $srcH] = $info;
    $mime = $info['mime'] ?? '';
    if ($srcW < 1 || $srcH < 1) {
        return false;
    }

    // Scale down to fit; never upscale
    $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0);
    $dstW = max(1, (int) round($srcW * $ratio));
    $dstH = max(1, (int) round($srcH * $ratio));

    // Already small enough — copy as-is when same path format helps quality
    if ($ratio >= 1.0 && $dstW === $srcW && $dstH === $srcH) {
        return @copy($sourcePath, $destPath);
    }

    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($sourcePath),
        'image/png' => @imagecreatefrompng($sourcePath),
        'image/gif' => @imagecreatefromgif($sourcePath),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
        default => false,
    };
    if ($src === false) {
        return false;
    }

    $dst = imagecreatetruecolor($dstW, $dstH);
    if ($dst === false) {
        imagedestroy($src);
        return false;
    }

    if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
        imagealphablending($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    imagedestroy($src);

    $ext = strtolower(pathinfo($destPath, PATHINFO_EXTENSION));
    $ok = match ($ext) {
        'jpg', 'jpeg' => imagejpeg($dst, $destPath, 86),
        'png' => imagepng($dst, $destPath, 6),
        'gif' => imagegif($dst, $destPath),
        'webp' => function_exists('imagewebp') ? imagewebp($dst, $destPath, 86) : imagejpeg($dst, preg_replace('/\.webp$/i', '.jpg', $destPath) ?: $destPath, 86),
        default => imagejpeg($dst, $destPath, 86),
    };
    imagedestroy($dst);
    return (bool) $ok;
}

/**
 * Ensure thumb exists for a local uploads image URL; return display (thumb) URL or original.
 */
function ensure_thumb_url(string $imageUrl): string
{
    $fs = uploads_path_from_url($imageUrl);
    if ($fs === null) {
        return $imageUrl;
    }
    // Already a thumb path
    if (str_contains(str_replace('\\', '/', $fs), '/uploads/images/thumbs/')) {
        return $imageUrl;
    }
    if (!str_contains(str_replace('\\', '/', $fs), '/uploads/images/')) {
        return $imageUrl;
    }

    $thumbFs = thumb_path_for_image($fs);
    if (!is_file($thumbFs)) {
        create_image_thumb($fs, $thumbFs);
    }
    if (!is_file($thumbFs)) {
        return $imageUrl;
    }

    $thumbUrl = thumb_url_for_image_url($imageUrl);
    return $thumbUrl ?? $imageUrl;
}

/**
 * Wrap content <img> with lightbox anchors and swap src to thumbs.
 */
function decorate_content_images(string $html): string
{
    $wrapImg = static function (string $imgTag): string {
        if (preg_match('/\bcontent-thumb\b/', $imgTag)) {
            return $imgTag;
        }
        if (!preg_match('/\bsrc=["\']([^"\']+)["\']/i', $imgTag, $sm)) {
            return $imgTag;
        }
        $src = html_entity_decode($sm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $thumbUrl = ensure_thumb_url($src);
        $href = htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $thumbEsc = htmlspecialchars($thumbUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $tag = preg_replace('/\bsrc=["\'][^"\']+["\']/i', 'src="' . $thumbEsc . '"', $imgTag, 1) ?? $imgTag;
        if (!preg_match('/\balt=/i', $tag)) {
            $tag = preg_replace('/\/?>\s*$/', ' alt="">', $tag) ?? ($tag . ' alt="">');
        }
        if (!preg_match('/\bloading=/i', $tag)) {
            $tag = preg_replace('/<img\b/i', '<img loading="lazy"', $tag, 1) ?? $tag;
        }
        if (!preg_match('/\bclass=/i', $tag)) {
            $tag = preg_replace('/<img\b/i', '<img class="content-thumb"', $tag, 1) ?? $tag;
        } else {
            $tag = preg_replace('/\bclass=["\']([^"\']*)["\']/i', 'class="$1 content-thumb"', $tag, 1) ?? $tag;
        }

        return '<a class="js-lightbox" href="' . $href . '" data-full="' . $href . '" aria-label="Открыть изображение">'
            . $tag
            . '</a>';
    };

    // Markdown linked images
    $html = (string) preg_replace_callback(
        '/<a\s[^>]*>\s*(<img\b[^>]*>)\s*<\/a>/iu',
        static function (array $m) use ($wrapImg): string {
            if (stripos($m[0], 'js-lightbox') !== false) {
                return $m[0];
            }
            return $wrapImg($m[1]);
        },
        $html
    );

    // Bare images (not already wrapped)
    $html = (string) preg_replace_callback(
        '/<img\b[^>]*>/iu',
        static function (array $m) use ($wrapImg): string {
            if (preg_match('/\bcontent-thumb\b/', $m[0])) {
                return $m[0];
            }
            return $wrapImg($m[0]);
        },
        $html
    );

    return $html;
}

/**
 * Generate thumbs for all images in uploads/images (not already in thumbs/).
 * @return array{ok: int, fail: int}
 */
function regenerate_all_thumbs(): array
{
    $dir = ROOT_PATH . '/uploads/images';
    $ok = 0;
    $fail = 0;
    if (!is_dir($dir)) {
        return ['ok' => 0, 'fail' => 0];
    }
    $files = scandir($dir) ?: [];
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'thumbs') {
            continue;
        }
        $path = $dir . '/' . $file;
        if (!is_file($path)) {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            continue;
        }
        if (create_image_thumb($path)) {
            $ok++;
        } else {
            $fail++;
        }
    }
    return ['ok' => $ok, 'fail' => $fail];
}

function branding_images_dir(): string
{
    return ROOT_PATH . '/uploads/images';
}

function branding_logo_path(): string
{
    return branding_images_dir() . '/logo.png';
}

function branding_cover_path(): string
{
    return branding_images_dir() . '/cover.jpg';
}

/**
 * Save an uploaded image as a fixed branding file (logo.png or cover.jpg).
 *
 * @param array{tmp_name?:string,error?:int,size?:int,name?:string} $file
 * @return string|null Error message, or null on success
 */
function save_branding_upload(array $file, string $targetName): ?string
{
    $allowed = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/gif' => true,
        'image/webp' => true,
    ];
    $maxSize = 5 * 1024 * 1024;
    $targetName = strtolower(basename($targetName));
    if (!in_array($targetName, ['logo.png', 'cover.jpg'], true)) {
        return 'Недопустимое имя файла.';
    }

    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'Ошибка загрузки файла.';
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return 'Файл не получен.';
    }
    if (($file['size'] ?? 0) > $maxSize) {
        return 'Файл слишком большой (макс. 5 МБ).';
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp) ?: '';
    if (!isset($allowed[$mime])) {
        return 'Разрешены только JPEG, PNG, GIF, WebP.';
    }

    $dir = branding_images_dir();
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return 'Не удалось создать каталог uploads/images. Проверьте права на запись.';
    }
    if (!is_writable($dir)) {
        return 'Каталог uploads/images недоступен для записи.';
    }

    $dest = $dir . '/' . $targetName;

    if (images_gd_available()) {
        $info = @getimagesize($tmp);
        if (!$info) {
            return 'Не удалось прочитать изображение.';
        }
        $srcMime = $info['mime'] ?? $mime;
        $src = match ($srcMime) {
            'image/jpeg' => @imagecreatefromjpeg($tmp),
            'image/png' => @imagecreatefrompng($tmp),
            'image/gif' => @imagecreatefromgif($tmp),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
            default => false,
        };
        if ($src === false) {
            return 'Не удалось обработать изображение (нужен GD).';
        }

        $ok = $targetName === 'logo.png'
            ? imagepng($src, $dest, 6)
            : imagejpeg($src, $dest, 90);
        imagedestroy($src);
        if (!$ok) {
            return 'Не удалось сохранить файл.';
        }
    } else {
        $needMime = $targetName === 'logo.png' ? 'image/png' : 'image/jpeg';
        if ($mime !== $needMime) {
            return 'Без расширения GD загружайте файл уже в нужном формате ('
                . ($targetName === 'logo.png' ? 'PNG' : 'JPEG') . ').';
        }
        if (!@move_uploaded_file($tmp, $dest)) {
            return 'Не удалось сохранить файл.';
        }
    }

    @chmod($dest, 0644);
    return null;
}

function delete_branding_file(string $targetName): bool
{
    $targetName = strtolower(basename($targetName));
    if (!in_array($targetName, ['logo.png', 'cover.jpg'], true)) {
        return false;
    }
    $path = branding_images_dir() . '/' . $targetName;
    if (!is_file($path)) {
        return true;
    }
    return @unlink($path);
}
