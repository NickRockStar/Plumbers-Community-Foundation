<?php
// Developer note: aggregates fresh plumbing news from Russian RSS feeds,
// caches results for 1 hour and falls back to admin-managed data/news.json.

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/feeds-lib.php';

const CACHE_FILE = __DIR__ . '/cache/news-cache.json';
const CACHE_TTL = 3600; // 1 hour
const MAX_ITEMS = 6;

function serveCache(): void {
    $cached = json_decode(file_get_contents(CACHE_FILE), true);
    echo json_encode($cached, JSON_UNESCAPED_UNICODE);
    exit;
}

if (file_exists(CACHE_FILE) && time() - filemtime(CACHE_FILE) < CACHE_TTL) {
    serveCache();
}

$items = rssItems(MAX_ITEMS);

if ($items === []) {
    // All feeds failed — serve admin-managed news
    $local = json_decode(file_get_contents(__DIR__ . '/data/news.json'), true) ?: [];
    $fallback = array_map(static fn($n) => $n + ['timestamp' => strtotime($n['date'] ?? 'now'), 'source' => 'local'], $local);
    echo json_encode(['items' => array_slice($fallback, 0, MAX_ITEMS), 'source' => 'local'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = ['items' => $items, 'source' => 'rss', 'updated' => time()];

if (!is_dir(__DIR__ . '/cache')) {
    @mkdir(__DIR__ . '/cache', 0775, true);
}
@file_put_contents(CACHE_FILE, json_encode($payload, JSON_UNESCAPED_UNICODE));

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
