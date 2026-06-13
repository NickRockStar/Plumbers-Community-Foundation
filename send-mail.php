<?php
// Developer note: load .env early and keep SMTP credentials out of source code.

header('Content-Type: application/json; charset=utf-8');

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
$message = trim($_POST['message'] ?? '');

if ($name === '' || $email === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Заполните все поля.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Некорректный email.']);
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

$mail = new PHPMailer(true);

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
    $mail->Body = "Имя: {$name}\nEmail: {$email}\n\nСообщение:\n{$message}\n";

    $mail->send();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка отправки через SMTP: ' . $mail->ErrorInfo]);
}