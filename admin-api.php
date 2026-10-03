<?php
// Developer note: admin API for managing site content blocks (about/projects/news).
// Auth: password from .env (ADMIN_PASSWORD), PHP session. Content stored in data/*.json.

header('Content-Type: application/json; charset=utf-8');
// JSON API: предупреждения PHP не должны попадать в ответ и ломать разбор на клиенте
ini_set('display_errors', '0');

require __DIR__ . '/feeds-lib.php';

const DATA_DIR = __DIR__ . '/data';
const SECTIONS = ['about', 'mission', 'directions', 'projects', 'news', 'docs'];
const MAX_ITEMS = 50;
// Лимиты текста согласованы с maxlength полей в admin.html и гарантируют вёрстку карточек
const MAX_TEXT = 500;
const MAX_TITLE = 100;
const MAX_NEWS_TITLE = 150;
const MAX_BADGE = 40;

session_start();

function jsonOut(array $payload, int $code = 200): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function isLoggedIn(): bool {
    return ($_SESSION['admin'] ?? false) === true;
}

function sectionFile(string $section): string {
    return DATA_DIR . '/' . $section . '.json';
}

// Normalize + sanitize items per section schema
function sanitizeItems(string $section, array $items): array {
    $clean = [];
    $str = static function ($v, int $max) {
        return mb_substr(trim(strip_tags((string)$v)), 0, $max);
    };
    foreach (array_slice($items, 0, MAX_ITEMS) as $item) {
        if (!is_array($item)) continue;
        if ($section === 'about') {
            $entry = ['title' => $str($item['title'] ?? '', MAX_TITLE), 'text' => $str($item['text'] ?? '', MAX_TEXT)];
            if (!empty($item['link']['text'])) {
                $entry['link'] = ['text' => $str($item['link']['text'], 200)];
                $linkVal = $str($item['link']['modal'] ?? $item['link']['href'] ?? '', 300);
                if ($linkVal !== '') {
                    if (str_starts_with($linkVal, '#')) {
                        $entry['link']['modal'] = $linkVal;
                    } else {
                        $entry['link']['href'] = $linkVal;
                    }
                }
            }
            if ($entry['title'] === '' && $entry['text'] === '') continue;
            $clean[] = $entry;
        } elseif ($section === 'projects') {
            $entry = [
                'title' => $str($item['title'] ?? '', MAX_TITLE),
                'text' => $str($item['text'] ?? '', MAX_TEXT),
                'image' => $str($item['image'] ?? '', 500),
            ];
            if (!empty($item['badge']['text'])) {
                $types = ['primary', 'success', 'warning', 'danger', 'info', 'secondary'];
                $type = in_array($item['badge']['type'] ?? '', $types, true) ? $item['badge']['type'] : 'primary';
                $entry['badge'] = ['text' => $str($item['badge']['text'], MAX_BADGE), 'type' => $type];
            }
            if (!empty($item['link'])) {
                $entry['link'] = filter_var($str($item['link'], 500), FILTER_VALIDATE_URL) ?: '';
            }
            if ($entry['title'] === '') continue;
            $clean[] = $entry;
        } elseif ($section === 'mission') {
            // Миссия: одна цитата, кавычки-«ёлочки» добавляются на сайте
            $quote = $str($item['quote'] ?? '', 300);
            if ($quote === '') continue;
            $clean[] = ['quote' => $quote];
        } elseif ($section === 'directions') {
            // Направления: номер карточки генерируется по порядку на сайте
            $entry = [
                'title' => $str($item['title'] ?? '', MAX_TITLE),
                'text' => $str($item['text'] ?? '', MAX_TEXT),
            ];
            if ($entry['title'] === '') continue;
            $clean[] = $entry;
        } elseif ($section === 'docs') {
            // Документы блока «Открытые данные фонда»: иконка из белого списка, тег и статус
            $icons = ['fa-file-lines', 'fa-shield-halved', 'fa-chart-column', 'fa-landmark', 'fa-file-pdf', 'fa-hand-holding-heart', 'fa-book-open'];
            $icon = $str($item['icon'] ?? '', 60);
            $link = $str($item['link'] ?? '', 500);
            $entry = [
                'title' => $str($item['title'] ?? '', MAX_TITLE),
                'text' => $str($item['text'] ?? '', MAX_TEXT),
                'icon' => in_array($icon, $icons, true) ? $icon : 'fa-file-lines',
                'badgeColor' => in_array($item['badgeColor'] ?? '', ['warning', 'secondary', 'success', 'primary'], true) ? $item['badgeColor'] : 'warning',
            ];
            $tag = $str($item['tag'] ?? '', 30);
            if ($tag !== '') $entry['tag'] = $tag;
            if ($link !== '') $entry['link'] = $link;
            $linkText = $str($item['linkText'] ?? '', 100);
            if ($linkText !== '') $entry['linkText'] = $linkText;
            $badge = $str($item['badge'] ?? '', 100);
            if ($badge !== '') $entry['badge'] = $badge;
            if ($entry['title'] === '') continue;
            $clean[] = $entry;
        } else { // news
            $link = $str($item['link'] ?? '', 500);
            $entry = [
                'title' => $str($item['title'] ?? '', MAX_NEWS_TITLE),
                'description' => $str($item['description'] ?? '', MAX_TEXT),
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $str($item['date'] ?? '', 10)) ? $str($item['date'], 10) : date('Y-m-d'),
                'link' => filter_var($link, FILTER_VALIDATE_URL) ?: '',
            ];
            $sourceName = $str($item['source']['name'] ?? '', 200);
            $sourceLink = $str($item['source']['link'] ?? '', 500);
            if ($sourceName !== '') {
                $entry['source'] = ['name' => $sourceName];
                $validSourceLink = filter_var($sourceLink, FILTER_VALIDATE_URL);
                if ($validSourceLink) $entry['source']['link'] = $validSourceLink;
            }
            if ($entry['title'] === '') continue;
            $clean[] = $entry;
        }
    }
    return $clean;
}

$action = $_REQUEST['action'] ?? '';

// --- Public: login/logout ---
if ($action === 'login') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonOut(['success' => false, 'message' => 'Неверный метод.'], 405);
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0);
    if ($_SESSION['login_attempts'] >= 5) {
        jsonOut(['success' => false, 'message' => 'Слишком много попыток входа. Подождите 10 минут.'], 429);
    }
    $password = (string)($_POST['password'] ?? '');
    $adminPassword = (string)($_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD') ?: '');
    // Load .env manually if not set by other entrypoints
    if ($adminPassword === '' && is_file(__DIR__ . '/.env')) {
        foreach (parse_ini_file(__DIR__ . '/.env') ?: [] as $k => $v) {
            if ($k === 'ADMIN_PASSWORD') $adminPassword = (string)$v;
        }
    }
    if ($adminPassword === '') {
        jsonOut(['success' => false, 'message' => 'Админ-панель не настроена: задайте ADMIN_PASSWORD в .env']);
    }
    if (hash_equals($adminPassword, $password)) {
        $_SESSION['admin'] = true;
        $_SESSION['login_attempts'] = 0;
        jsonOut(['success' => true]);
    }
    $_SESSION['login_attempts']++;
    sleep(1);
    jsonOut(['success' => false, 'message' => 'Неверный пароль.']);
}

if ($action === 'logout') {
    $_SESSION = [];
    session_destroy();
    jsonOut(['success' => true]);
}

if ($action === 'state') {
    jsonOut(['loggedIn' => isLoggedIn()]);
}

// --- Everything below requires auth ---
if (!isLoggedIn()) {
    jsonOut(['success' => false, 'message' => 'Требуется авторизация.'], 401);
}

if ($action === 'data') {
    $out = [];
    foreach (SECTIONS as $s) {
        $out[$s] = json_decode(file_get_contents(sectionFile($s)), true) ?: [];
    }
    jsonOut(['success' => true, 'data' => $out]);
}

if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = (string)($_POST['section'] ?? '');
    if (!in_array($section, SECTIONS, true)) {
        jsonOut(['success' => false, 'message' => 'Неизвестная секция.'], 400);
    }
    $items = json_decode((string)($_POST['items'] ?? '[]'), true);
    if (!is_array($items)) {
        jsonOut(['success' => false, 'message' => 'Некорректные данные.'], 400);
    }
    $clean = sanitizeItems($section, $items);
    $file = sectionFile($section);
    $ok = file_put_contents($file, json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
    if ($ok === false) {
        jsonOut(['success' => false, 'message' => 'Не удалось записать файл (проверьте права на data/).'], 500);
    }
    // invalidate news cache so fallback changes show up immediately
    @unlink(__DIR__ . '/cache/news-cache.json');
    jsonOut(['success' => true, 'saved' => count($clean)]);
}

if ($action === 'rss') {
    try {
        $items = rssItems(6);
        jsonOut(['success' => true, 'items' => $items]);
    } catch (Throwable $e) {
        error_log('[admin-api] rss: ' . $e->getMessage());
        jsonOut(['success' => false, 'message' => 'Ленты новостей временно недоступны. Попробуйте позже.']);
    }
}

jsonOut(['success' => false, 'message' => 'Неизвестное действие.'], 400);
