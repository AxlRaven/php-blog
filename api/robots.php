<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');

$sitemap = absolute_url('sitemap.xml');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /api/\n";
echo "Disallow: /search\n";
echo "Disallow: /config/\n";
echo "Disallow: /includes/\n";
echo "\n";
echo "Sitemap: {$sitemap}\n";
