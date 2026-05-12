<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Метод не разрешен', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    errorResponse('Введите корректный email');
}

$pdo = getDB();
if (!$pdo) {
    errorResponse('Ошибка подключения к базе данных');
}

// Проверяем, не зарегистрирован ли уже email
$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    errorResponse('Пользователь с таким email уже существует');
}

// Генерируем код
$code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

// Удаляем старые коды
$pdo->prepare("DELETE FROM email_verification WHERE email = ? AND is_used = 0")->execute([$email]);

// Сохраняем новый код
$pdo->prepare("INSERT INTO email_verification (email, code, expires_at) VALUES (?,?,?)")->execute([$email, $code, $expires]);

// Пробуем отправить email
$emailSent = false;
try {
    $subject = 'Код подтверждения - ГЕРОН-АВТО';
    $body = "
    <html><body>
        <h2>Код подтверждения регистрации</h2>
        <p>Ваш код:</p>
        <div style='font-size:36px;color:#ff6b35;text-align:center;padding:20px;letter-spacing:10px'>{$code}</div>
        <p>Код действителен 10 минут.</p>
    </body></html>";
    
    $emailSent = sendEmail($email, $subject, $body);
} catch (Exception $e) {
    error_log("Send code error: " . $e->getMessage());
}

if ($emailSent) {
    successResponse(['message' => 'Код отправлен на email'], 'Код отправлен');
} else {
    // Для тестов — показываем код в ответе
    successResponse([
        'test_code' => $code,
        'message' => 'Код сгенерирован. Почта не настроена.'
    ], 'Код сгенерирован');
}