<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

header('Content-Type: application/rss+xml; charset=UTF-8');

$items = seo_rss_items(20);
$feedUrl = absolute_url('feed');
$siteLink = absolute_url();
$updated = $items[0]['published_at'] ?? $items[0]['updated_at'] ?? date('c');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title><?= htmlspecialchars(site_title(), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></title>
    <link><?= htmlspecialchars($siteLink, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></link>
    <description><?= htmlspecialchars(site_description(), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></description>
    <language>ru-RU</language>
    <lastBuildDate><?= htmlspecialchars(date(DATE_RSS, strtotime((string) $updated)), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></lastBuildDate>
    <atom:link href="<?= htmlspecialchars($feedUrl, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?>" rel="self" type="application/rss+xml"/>
    <?php foreach ($items as $post): ?>
      <?php
      $split = split_more((string) $post['content']);
      $body = markdown_to_html($split['excerpt']);
      $link = absolute_url('post/' . $post['slug']);
      $pub = $post['published_at'] ?? $post['created_at'];
      ?>
    <item>
      <title><?= htmlspecialchars((string) $post['title'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></title>
      <link><?= htmlspecialchars($link, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></link>
      <guid isPermaLink="true"><?= htmlspecialchars($link, ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></guid>
      <pubDate><?= htmlspecialchars(date(DATE_RSS, strtotime((string) $pub)), ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></pubDate>
      <description><![CDATA[<?= $body ?>]]></description>
      <content:encoded><![CDATA[<?= $body ?>]]></content:encoded>
      <?php foreach ($post['tags'] as $tag): ?>
      <category><?= htmlspecialchars((string) $tag['name'], ENT_XML1 | ENT_COMPAT, 'UTF-8') ?></category>
      <?php endforeach; ?>
    </item>
    <?php endforeach; ?>
  </channel>
</rss>
