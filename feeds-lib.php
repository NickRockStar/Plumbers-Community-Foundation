<?php
// Developer note: shared RSS helpers used by news.php (public feed) and admin-api.php (import).

// RSS-ленты: агрегаторы и профильные издания по сантехнике в России
const FEEDS = [
    'https://news.google.com/rss/search?q=%D1%81%D0%B0%D0%BD%D1%82%D0%B5%D1%85%D0%BD%D0%B8%D0%BA%D0%B0+%D0%A0%D0%BE%D1%81%D1%81%D0%B8%D1%8F&hl=ru&gl=RU&ceid=RU:ru',
    'https://news.google.com/rss/search?q=%D0%B2%D0%BE%D0%B4%D0%BE%D1%81%D0%BD%D0%B0%D0%B1%D0%B6%D0%B5%D0%BD%D0%B8%D0%B5+%D0%A0%D0%BE%D1%81%D1%81%D0%B8%D1%8F&hl=ru&gl=RU&ceid=RU:ru',
];

function fetchUrl(string $url): string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) SantehProNews/1.0',
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($code === 200 && is_string($body)) {
            return $body;
        }
        return '';
    }
    return @file_get_contents($url) ?: '';
}

function parseFeed(string $body): array {
    $parsed = [];
    if ($body === '') {
        return $parsed;
    }
    if (function_exists('simplexml_load_string')) {
        $xml = @simplexml_load_string($body);
        if ($xml !== false && isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $parsed[] = [
                    'title' => trim((string)($item->title ?? '')),
                    'link' => trim((string)($item->link ?? '')),
                    'pubDate' => (string)($item->pubDate ?? ''),
                    'description' => trim(strip_tags((string)($item->description ?? ''))),
                ];
            }
            return $parsed;
        }
    }
    // Fallback parser when ext-simplexml is not available
    if (preg_match_all('/<item>(.*?)<\/item>/s', $body, $matches)) {
        foreach ($matches[1] as $raw) {
            $get = static function (string $tag) use ($raw): string {
                if (preg_match('/<' . $tag . '>(.*?)<\/' . $tag . '>/s', $raw, $m)) {
                    return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8');
                }
                if (preg_match('/<' . $tag . '[^>]*href=["\']([^"\']+)["\']/s', $raw, $m)) {
                    return trim($m[1]);
                }
                return '';
            };
            $parsed[] = [
                'title' => $get('title'),
                'link' => $get('link'),
                'pubDate' => $get('pubDate'),
                'description' => trim(strip_tags($get('description'))),
            ];
        }
    }
    return $parsed;
}

function rssItems(int $max = 6): array {
    $items = [];
    foreach (FEEDS as $feedUrl) {
        foreach (parseFeed(fetchUrl($feedUrl)) as $item) {
            $title = $item['title'];
            $link = $item['link'];
            $pubDate = $item['pubDate'];
            $description = $item['description'];
            if (function_exists('mb_strlen') && mb_strlen($description) > 180) {
                $description = mb_substr($description, 0, 177) . '...';
            }
            if ($title === '' || $link === '') {
                continue;
            }
            $items[] = [
                'title' => $title,
                'description' => $description,
                'date' => $pubDate ? date('Y-m-d', strtotime($pubDate)) : date('Y-m-d'),
                'timestamp' => $pubDate ? strtotime($pubDate) : time(),
                'link' => $link,
            ];
        }
    }
    usort($items, static fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
    return array_slice($items, 0, $max);
}
