<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') errorResponse('Метод не разрешен', 405);

$d = json_decode(file_get_contents('php://input'), true);
$email = trim($d['email'] ?? '');
$code = trim($d['code'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) errorResponse('Введите email');
if (!ctype_digit($code) || strlen($code) !== 6) errorResponse('Введите 6-значный код');

$pdo = getDB();
if (!$pdo) errorResponse('Ошибка БД');

$stmt = $pdo->prepare("SELECT id, expires_at FROM email_verification WHERE email = ? AND code = ? AND is_used = 0 ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$email, $code]);
$v = $stmt->fetch();

if (!$v) errorResponse('Неверный код');
if (strtotime($v['expires_at']) < time()) errorResponse('Код истёк');

$pdo->prepare("UPDATE email_verification SET is_used = 1 WHERE id = ?")->execute([$v['id']]);
successResponse(['verified' => true], 'Email подтверждён');