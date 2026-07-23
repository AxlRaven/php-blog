<?php
/**
 * Bootstrap: session, config, autoload helpers
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . '/config/config.php');

$configFile = CONFIG_PATH;
if (!is_file($configFile)) {
    // Not installed yet
    if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/install.php');
        exit;
    }
    $config = require ROOT_PATH . '/config/config.sample.php';
} else {
    $config = require $configFile;
    if (empty($config['installed']) && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
        header('Location: ' . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/install.php');
        exit;
    }
}

require_once ROOT_PATH . '/includes/helpers.php';
require_once ROOT_PATH . '/includes/db.php';
require_once ROOT_PATH . '/includes/totp.php';
require_once ROOT_PATH . '/includes/auth.php';
require_once ROOT_PATH . '/includes/images.php';
require_once ROOT_PATH . '/includes/seo.php';
require_once ROOT_PATH . '/vendor/Parsedown.php';

$GLOBALS['config'] = $config;
