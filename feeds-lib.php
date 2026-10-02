<?php
// Developer note: shared RSS helpers used by news.php (public feed) and admin-api.php (import).
// Ленты грузятся параллельно; общие новостные ленты фильтруются по тематике сообщества,
// чтобы в импорт попадали только профильные материалы.

// RSS-ленты: только российские источники (общие новостные + отраслевая строительная)
const FEEDS = [
    'https://www.kommersant.ru/RSS/news.xml',
    'https://tass.ru/rss/v2.xml',
    'https://ria.ru/export/rss2/archive/index.xml',
    'https://www.interfax.ru/rss.asp',
    'https://lenta.ru/rss/news',
    'https://www.m24.ru/rss.xml',
    'https://www.ng.ru/rss/',
    'https://ura.news/rss',
    'https://www.stroygaz.ru/rss/',
];

// Тематика импорта: новость без совпадения в заголовке/описании отбрасывается
const TOPIC_KEYWORDS = [
    'сантехник', 'водоснабж', 'водоотведен', 'водоканал', 'канализац', 'жкх',
    'трубопровод', 'котельн', 'отоплен', 'теплоснабж', 'горячей воды', 'отключение воды',
    'коммунальн', 'слесар', 'смесител', 'бойлер', 'водонагрева', 'насос', 'водопровод', 'прорыв',
    'утечк', 'затоп', 'водоочист', 'септик', 'скважин', 'колодц',
];

function fetchUrl(string $url): string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
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

// Все ленты параллельно через curl_multi: общее время = самой медленной ленте
function fetchUrls(array $urls): array {
    $out = array_fill_keys($urls, '');
    if (!function_exists('curl_multi_init')) {
        foreach ($urls as $u) {
            $out[$u] = fetchUrl($u);
        }
        return $out;
    }
    $mh = curl_multi_init();
    $handles = [];
    foreach ($urls as $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0.0.0 Safari/537.36',
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        curl_multi_add_handle($mh, $ch);
        $handles[$u] = $ch;
    }
    do {
        $status = curl_multi_exec($mh, $active);
        if ($status !== CURLM_OK) {
            break;
        }
        curl_multi_select($mh, 0.2);
    } while ($active);
    foreach ($handles as $u => $ch) {
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $body = curl_multi_getcontent($ch);
        $out[$u] = ($code === 200 && is_string($body)) ? $body : '';
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $out;
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
        $get = static function (string $tag, string $raw): string {
            if (preg_match('/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/is', $raw, $m)) {
                $v = $m[1];
            } elseif (preg_match('/<' . $tag . '[^>]*href=["\']([^"\']+)["\']/s', $raw, $m)) {
                $v = $m[1];
            } else {
                return '';
            }
            $v = preg_replace('/<!\[CDATA\[(.*?)\]\]>/s', '$1', $v);
            return trim(html_entity_decode(strip_tags($v), ENT_QUOTES | ENT_XML1, 'UTF-8'));
        };
        foreach ($matches[1] as $raw) {
            $parsed[] = [
                'title' => $get('title', $raw),
                'link' => $get('link', $raw),
                'pubDate' => $get('pubDate', $raw),
                'description' => $get('description', $raw),
            ];
        }
    }
    return $parsed;
}

function isTopicItem(array $item): bool {
    $hay = mb_strtolower($item['title'] . ' ' . $item['description']);
    foreach (TOPIC_KEYWORDS as $kw) {
        if (mb_stripos($hay, $kw) !== false) {
            return true;
        }
    }
    return false;
}

function rssItems(int $max = 6): array {
    $items = [];
    $seen = [];
    foreach (fetchUrls(array_values(FEEDS)) as $body) {
        foreach (parseFeed((string)$body) as $item) {
            $title = $item['title'];
            $link = $item['link'];
            $pubDate = $item['pubDate'];
            $description = $item['description'];
            if (function_exists('mb_strlen') && mb_strlen($description) > 180) {
                $description = mb_substr($description, 0, 177) . '...';
            }
            if ($title === '' || $link === '' || isset($seen[$title])) {
                continue;
            }
            $seen[$title] = true;
            $items[] = [
                'title' => $title,
                'description' => $description,
                'date' => $pubDate ? date('Y-m-d', strtotime($pubDate)) : date('Y-m-d'),
                'timestamp' => $pubDate ? strtotime($pubDate) : time(),
                'link' => $link,
            ];
        }
    }
    $items = array_values(array_filter($items, 'isTopicItem'));
    usort($items, static fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
    return array_slice($items, 0, $max);
}
