<?php
// Developer note: load .env early and keep SMTP credentials out of source code.

header('Content-Type: application/json; charset=utf-8');
// JSON API: предупреждения PHP не должны попадать в ответ и ломать разбор на клиенте
ini_set('display_errors', '0');

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса.']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $phone === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Заполните все поля.']);
    exit;
}

if (($_POST['consent'] ?? '') !== 'on') {
    echo json_encode(['success' => false, 'message' => 'Необходимо согласие на обработку персональных данных.']);
    exit;
}

// Evidence of consent for FZ-152 (art. 9): timestamp + sender email, kept 3 years (see politika.html)
$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0775, true);
}
$consentEntry = json_encode([
    'consent_at' => date('c'),
    'form' => 'contact',
    'email' => $email,
    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
], JSON_UNESCAPED_UNICODE) . PHP_EOL;
@file_put_contents($storageDir . '/consent-log.jsonl', $consentEntry, FILE_APPEND | LOCK_EX);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный email.']);
    exit;
}

// Телефон: только цифры, пробелы, скобки, дефисы и + в начале; 10–15 цифр (E.164)
if (!preg_match('/^\+?[\d\s\-\(\)]{10,20}$/', $phone) || !($digits = preg_replace('/\D+/', '', $phone)) || strlen($digits) < 10 || strlen($digits) > 15) {
    echo json_encode(['success' => false, 'message' => 'Укажите корректный номер телефона: от 10 до 15 цифр, например +7 (999) 123-45-67.']);
    exit;
}

// Сообщение: тот же лимит, что и maxlength в форме
if (mb_strlen($message) > 300) {
    echo json_encode(['success' => false, 'message' => 'Сообщение не должно превышать 300 символов.']);
    exit;
}

$smtpHost = $_ENV['SMTP_HOST'] ?? '';
$smtpPort = (int)($_ENV['SMTP_PORT'] ?? 587);
$smtpSecure = $_ENV['SMTP_SECURE'] ?? 'tls';
$smtpUsername = $_ENV['SMTP_USERNAME'] ?? '';
$smtpPassword = $_ENV['SMTP_PASSWORD'] ?? '';
$mailFrom = $_ENV['MAIL_FROM'] ?? $smtpUsername;
$mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'СантехПро';
$mailTo = $_ENV['MAIL_TO'] ?? $smtpUsername;

if ($smtpHost === '' || $smtpUsername === '' || $smtpPassword === '') {
    echo json_encode(['success' => false, 'message' => 'SMTP не настроен: заполните .env (см. .env.example).']);
    exit;
}

$mail = new PHPMailer(true);

// Понятное пользователю описание сбоя SMTP; технические детали уходят в error_log
function smtpUserError(string $err): string {
    $e = mb_strtolower($err);
    if (str_contains($e, 'authenticate') || str_contains($e, 'password') || str_contains($e, 'credentials') || str_contains($e, 'username')) {
        return 'Не удалось войти на почтовый сервер: проверьте SMTP_USERNAME и SMTP_PASSWORD в .env.';
    }
    if (str_contains($e, 'connect') || str_contains($e, 'timed out') || str_contains($e, 'timeout') || str_contains($e, 'resolve') || str_contains($e, 'network')) {
        return 'Почтовый сервер недоступен: проверьте SMTP_HOST и SMTP_PORT в .env и подключение к интернету.';
    }
    if (str_contains($e, 'sender') || str_contains($e, 'from address') || str_contains($e, 'denied') || str_contains($e, 'spam')) {
        return 'Почтовый сервер отклонил отправителя: проверьте MAIL_FROM в .env.';
    }
    return 'Не удалось отправить сообщение через почтовый сервер. Попробуйте позже.';
}

try {
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUsername;
    $mail->Password = $smtpPassword;
    $mail->Port = $smtpPort;
    $mail->CharSet = 'UTF-8';

    if ($smtpSecure === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->setFrom($mailFrom, $mailFromName);
    $mail->addAddress($mailTo);
    $mail->addReplyTo($email, $name);

    $mail->isHTML(false);
    $mail->Subject = 'Новое сообщение с сайта СантехПро';
    $mail->Body = "Имя: {$name}\nТелефон: {$phone}\nEmail: {$email}\n\nСообщение:\n{$message}\n";

    $mail->send();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('[send-mail] PHPMailer: ' . $mail->ErrorInfo);
    echo json_encode(['success' => false, 'message' => smtpUserError($mail->ErrorInfo)]);
}