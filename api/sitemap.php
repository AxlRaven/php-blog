<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$urls = seo_sitemap_urls();

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $item) {
    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars((string) $item['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
    if (!empty($item['lastmod'])) {
        echo '    <lastmod>' . htmlspecialchars((string) $item['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</lastmod>\n";
    }
    if (!empty($item['changefreq'])) {
        echo '    <changefreq>' . htmlspecialchars((string) $item['changefreq'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</changefreq>\n";
    }
    if (!empty($item['priority'])) {
        echo '    <priority>' . htmlspecialchars((string) $item['priority'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</priority>\n";
    }
    echo "  </url>\n";
}
echo "</urlset>\n";
