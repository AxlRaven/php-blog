<?php
declare(strict_types=1);

/**
 * One-time installer for fresh deployment (schema 36, all features included).
 * Delete this file after successful installation.
 */

session_start();

$configPath = __DIR__ . '/config/config.php';
$samplePath = __DIR__ . '/config/config.sample.php';
$errors = [];
$success = false;
$already = is_file($configPath);

if ($already) {
    $existing = require $configPath;
    if (!empty($existing['installed'])) {
        $success = true;
    }
}

function install_e(?string $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$success) {
    $dbHost = trim((string) ($_POST['db_host'] ?? 'localhost'));
    $dbName = trim((string) ($_POST['db_name'] ?? ''));
    $dbUser = trim((string) ($_POST['db_user'] ?? ''));
    $dbPass = (string) ($_POST['db_pass'] ?? '');
    $siteTitle = trim((string) ($_POST['site_title'] ?? 'Мой блог'));
    $siteDesc = trim((string) ($_POST['site_description'] ?? ''));
    $adminUser = trim((string) ($_POST['admin_user'] ?? 'admin'));
    $adminPass = (string) ($_POST['admin_pass'] ?? '');
    $adminPass2 = (string) ($_POST['admin_pass2'] ?? '');

    if ($dbName === '') {
        $errors[] = 'Укажите имя базы данных.';
    }
    if ($adminUser === '') {
        $errors[] = 'Укажите логин администратора.';
    }
    if (strlen($adminPass) < 6) {
        $errors[] = 'Пароль должен быть не короче 6 символов.';
    }
    if ($adminPass !== $adminPass2) {
        $errors[] = 'Пароли не совпадают.';
    }

    if (!$errors) {
        try {
            $dsn = sprintf('mysql:host=%s;charset=utf8mb4', $dbHost);
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $dbName) . '`
                        CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $pdo->exec('USE `' . str_replace('`', '``', $dbName) . '`');

            $pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(64) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  totp_secret VARCHAR(64) NULL DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  `key` VARCHAR(64) NOT NULL PRIMARY KEY,
  `value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  content MEDIUMTEXT NOT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  published_at DATETIME NULL,
  views INT UNSIGNED NOT NULL DEFAULT 0,
  rating_sum INT UNSIGNED NOT NULL DEFAULT 0,
  rating_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_posts_status_published (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_tags (
  post_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (post_id, tag_id),
  CONSTRAINT fk_pt_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE,
  CONSTRAINT fk_pt_tag FOREIGN KEY (tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS post_ratings (
  post_id INT UNSIGNED NOT NULL,
  voter_key CHAR(64) NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, voter_key),
  CONSTRAINT fk_pr_post FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

            $hash = password_hash($adminPass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('SELECT id FROM admins WHERE username = ? LIMIT 1');
            $stmt->execute([$adminUser]);
            if ($stmt->fetch()) {
                $upd = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE username = ?');
                $upd->execute([$hash, $adminUser]);
            } else {
                $ins = $pdo->prepare('INSERT INTO admins (username, password_hash) VALUES (?, ?)');
                $ins->execute([$adminUser, $hash]);
            }

            $settings = [
                'site_title' => $siteTitle !== '' ? $siteTitle : 'Мой блог',
                'site_description' => $siteDesc,
                'site_url' => '',
                'site_keywords' => '',
                'seo_author' => '',
                'seo_organization' => '',
                'seo_twitter' => '',
                'seo_og_image' => '',
                'google_site_verification' => '',
                'yandex_verification' => '',
                'posts_per_page' => '10',
                'load_mode' => 'pagination',
                'schema_version' => '36',
            ];
            $setStmt = $pdo->prepare(
                'INSERT INTO settings (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)'
            );
            foreach ($settings as $k => $v) {
                $setStmt->execute([$k, $v]);
            }

            $uploads = [
                __DIR__ . '/uploads',
                __DIR__ . '/uploads/images',
                __DIR__ . '/uploads/images/thumbs',
                __DIR__ . '/uploads/files',
                __DIR__ . '/uploads/totp',
            ];
            foreach ($uploads as $dir) {
                if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new RuntimeException('Не удалось создать каталог uploads.');
                }
                $probe = $dir . '/.write_test';
                if (@file_put_contents($probe, 'ok') === false) {
                    throw new RuntimeException(
                        'Каталог недоступен для записи: ' . str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $dir)
                        . '. Выдайте права на запись веб-серверу (chmod 775 или chown).'
                    );
                }
                @unlink($probe);
            }

            $denyPhp = <<<'HTA'
Options -Indexes

# Do NOT use "php_flag" here — on CGI/FPM hosts it causes HTTP 500 for all files in this folder.

<FilesMatch "\.(?i:php|phtml|php3|php4|php5|phar)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
  </IfModule>
</FilesMatch>
HTA;
            file_put_contents(__DIR__ . '/uploads/.htaccess', $denyPhp);
            $denyTotp = <<<'HTA'
# Storage only — PNG served via admin/totp-qr.php after auth check.

<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order deny,allow
  Deny from all
</IfModule>

<FilesMatch "\.(?i:php|phtml|php3|php4|php5|phar)$">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
  <IfModule !mod_authz_core.c>
    Order deny,allow
    Deny from all
  </IfModule>
</FilesMatch>
HTA;
            file_put_contents(__DIR__ . '/uploads/totp/.htaccess', $denyTotp);

            $config = [
                'db' => [
                    'host' => $dbHost,
                    'name' => $dbName,
                    'user' => $dbUser,
                    'pass' => $dbPass,
                    'charset' => 'utf8mb4',
                ],
                'site' => [
                    'title' => $settings['site_title'],
                    'description' => $siteDesc,
                    'url' => '',
                ],
                'installed' => true,
            ];

            $export = "<?php\n\nreturn " . var_export($config, true) . ";\n";
            if (file_put_contents($configPath, $export) === false) {
                throw new RuntimeException('Не удалось записать config/config.php. Проверьте права на запись.');
            }

            $success = true;
        } catch (Throwable $e) {
            $errors[] = 'Ошибка установки: ' . $e->getMessage();
        }
    }
}

$defaults = [
    'db_host' => $_POST['db_host'] ?? 'localhost',
    'db_name' => $_POST['db_name'] ?? '',
    'db_user' => $_POST['db_user'] ?? '',
    'site_title' => $_POST['site_title'] ?? 'Мой блог',
    'site_description' => $_POST['site_description'] ?? '',
    'admin_user' => $_POST['admin_user'] ?? 'admin',
];

$uploadsWritable = true;
$uploadsWritableHint = '';
$uploadsRoot = __DIR__ . '/uploads';
if (!is_dir($uploadsRoot)) {
    $uploadsWritable = is_writable(__DIR__);
    if (!$uploadsWritable) {
        $uploadsWritableHint = 'Корневая папка сайта недоступна для записи — установщик не сможет создать uploads/.';
    }
} elseif (!is_writable($uploadsRoot)) {
    $uploadsWritable = false;
    $uploadsWritableHint = 'Папка uploads/ недоступна для записи. Выдайте права веб-серверу (chmod 775).';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Установка блога</title>
    <style>
        :root {
            --bg: #f7f6f2;
            --text: #1c1f1e;
            --muted: #5c6562;
            --accent: #2f6f5e;
            --border: #ddd9d0;
            --danger: #a33b2d;
            --surface: #fff;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(900px 500px at 0% 0%, #e8f2ee, transparent 55%),
                var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }
        .card {
            width: 100%;
            max-width: 520px;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 2rem;
            box-shadow: 0 10px 30px rgba(28,31,30,.06);
        }
        h1 {
            margin: 0 0 .4rem;
            font-weight: 500;
            font-size: 1.75rem;
        }
        .lead { color: var(--muted); margin: 0 0 1.5rem; font-family: system-ui, sans-serif; font-size: .95rem; }
        label { display: block; font-family: system-ui, sans-serif; font-size: .85rem; font-weight: 600; margin: 0 0 .35rem; }
        input, textarea {
            width: 100%;
            padding: .7rem .85rem;
            border: 1px solid var(--border);
            border-radius: 8px;
            font: inherit;
            font-family: system-ui, sans-serif;
            margin-bottom: 1rem;
            background: #fbfaf7;
        }
        fieldset {
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1rem .25rem;
            margin: 0 0 1.25rem;
        }
        legend {
            font-family: system-ui, sans-serif;
            font-size: .8rem;
            font-weight: 600;
            color: var(--muted);
            padding: 0 .4rem;
        }
        .btn {
            display: inline-block;
            border: 0;
            background: var(--accent);
            color: #fff;
            padding: .75rem 1.25rem;
            border-radius: 999px;
            font-family: system-ui, sans-serif;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .errors {
            background: #f8e8e5;
            color: var(--danger);
            padding: .85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-family: system-ui, sans-serif;
            font-size: .9rem;
        }
        .ok {
            background: #e4f0eb;
            color: #245748;
            padding: 1rem;
            border-radius: 8px;
            font-family: system-ui, sans-serif;
        }
        .ok a { color: var(--accent); font-weight: 600; }
        .warn { margin-top: 1rem; color: var(--danger); font-family: system-ui, sans-serif; font-size: .9rem; }
    </style>
</head>
<body>
<div class="card">
    <h1>Установка блога</h1>
    <p class="lead">Создайте таблицы MySQL и учётную запись администратора.</p>

    <?php if ($success): ?>
        <div class="ok">
            <p><strong>Готово.</strong> Блог установлен (версия 36).</p>
            <p>
                <a href="index.php">Открыть сайт</a> ·
                <a href="admin/login.php">Войти в админку</a>
            </p>
        </div>
        <p class="warn">Обязательно удалите файл <code>install.php</code> с сервера.</p>
    <?php else: ?>
        <?php if ($errors): ?>
            <div class="errors">
                <?php foreach ($errors as $err): ?>
                    <div><?= install_e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!$uploadsWritable): ?>
            <div class="errors">
                <div><?= install_e($uploadsWritableHint ?: 'Папка uploads/ недоступна для записи.') ?></div>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <fieldset>
                <legend>База данных</legend>
                <label for="db_host">Хост</label>
                <input id="db_host" name="db_host" value="<?= install_e($defaults['db_host']) ?>" required>
                <label for="db_name">Имя БД</label>
                <input id="db_name" name="db_name" value="<?= install_e($defaults['db_name']) ?>" required>
                <label for="db_user">Пользователь</label>
                <input id="db_user" name="db_user" value="<?= install_e($defaults['db_user']) ?>" required>
                <label for="db_pass">Пароль</label>
                <input id="db_pass" name="db_pass" type="password" value="">
            </fieldset>

            <fieldset>
                <legend>Сайт</legend>
                <label for="site_title">Название</label>
                <input id="site_title" name="site_title" value="<?= install_e($defaults['site_title']) ?>" required>
                <label for="site_description">Краткое описание</label>
                <input id="site_description" name="site_description" value="<?= install_e($defaults['site_description']) ?>">
            </fieldset>

            <fieldset>
                <legend>Администратор</legend>
                <label for="admin_user">Логин</label>
                <input id="admin_user" name="admin_user" value="<?= install_e($defaults['admin_user']) ?>" required>
                <label for="admin_pass">Пароль</label>
                <input id="admin_pass" name="admin_pass" type="password" required>
                <label for="admin_pass2">Повтор пароля</label>
                <input id="admin_pass2" name="admin_pass2" type="password" required>
            </fieldset>

            <button class="btn" type="submit">Установить</button>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
