<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'geron_auto');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') !== 0) putenv(trim($line));
    }
}

define('MAIL_HOST', 'smtp.yandex.ru');
define('MAIL_PORT', 587);
define('MAIL_USER', getenv('MAIL_USER') ?: 'your@email.ru');
define('MAIL_PASS', getenv('MAIL_PASS') ?: 'your_password');
define('MAIL_FROM', getenv('MAIL_FROM') ?: 'your@email.ru');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'ГЕРОН-АВТО');
define('MAIL_TO', getenv('MAIL_TO') ?: 'admin@email.ru');

ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
session_start();

function getDB(): ?PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log("DB Error: " . $e->getMessage());
            return null;
        }
    }
    return $pdo;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) return null;
    $pdo = getDB();
    if (!$pdo) return null;
    $stmt = $pdo->prepare("SELECT id, name, email, phone, avatar, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function errorResponse(string $message, int $status = 400): void {
    jsonResponse(['success' => false, 'message' => $message], $status);
}

function successResponse(array $data = [], string $message = 'Успешно'): void {
    jsonResponse(['success' => true, 'message' => $message] + $data);
}

function sendEmail(string $to, string $subject, string $body): bool {
    $mailerPath = __DIR__ . '/vendor/PHPMailer/';
    
    if (!file_exists($mailerPath . 'PHPMailer.php')) {
        error_log("PHPMailer не найден в {$mailerPath}");
        return false;
    }
    
    require_once $mailerPath . 'Exception.php';
    require_once $mailerPath . 'PHPMailer.php';
    require_once $mailerPath . 'SMTP.php';
    
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = function($str, $level) {
            error_log("PHPMailer: {$str}");
        };
        $mail->isSMTP();
        $mail->Host = MAIL_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = MAIL_USER;
        $mail->Password = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = MAIL_PORT;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->send();
        error_log("Email отправлен на {$to}");
        return true;
    } catch (Exception $e) {
        error_log("Email error: " . $e->getMessage());
        return false;
    }
}

function logToFile(string $file, string $entry): void {
    $path = __DIR__ . '/' . $file;
    file_put_contents($path, $entry, FILE_APPEND | LOCK_EX);
}