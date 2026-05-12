<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') errorResponse('Метод не разрешен', 405);

$data = json_decode(file_get_contents('php://input'), true);
$name = trim($data['name'] ?? '');
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';
$phone = trim($data['phone'] ?? '');
$verified = $data['verified'] ?? false;

if (empty($name)) errorResponse('Введите имя');
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) errorResponse('Введите корректный email');
if (empty($password) || strlen($password) < 6) errorResponse('Пароль минимум 6 символов');
if (empty($phone)) errorResponse('Введите телефон');

$pdo = getDB();
if (!$pdo) errorResponse('Ошибка БД');

$stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) errorResponse('Email уже зарегистрирован');

if (!$verified) {
    $stmt = $pdo->prepare("SELECT id FROM email_verification WHERE email = ? AND is_used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$email]);
    if (!$stmt->fetch()) errorResponse('Email не подтверждён', 403);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
$stmt->execute([$name, $email, $phone, $hash]);
$userId = $pdo->lastInsertId();

$_SESSION['user_id'] = $userId;

$pdo->prepare("DELETE FROM email_verification WHERE email = ?")->execute([$email]);

successResponse(['user' => ['id' => $userId, 'name' => $name, 'email' => $email, 'phone' => $phone]], 'Регистрация успешна');