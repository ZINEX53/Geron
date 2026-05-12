<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') errorResponse('Метод не разрешен', 405);

$data = json_decode(file_get_contents('php://input'), true);
$email = trim($data['email'] ?? '');
$password = $data['password'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) errorResponse('Введите корректный email');
if (empty($password)) errorResponse('Введите пароль');

$pdo = getDB();
if (!$pdo) errorResponse('Ошибка БД');

$stmt = $pdo->prepare("SELECT id, name, email, phone, avatar, password FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) errorResponse('Неверный email или пароль');

$_SESSION['user_id'] = $user['id'];
unset($user['password']);
successResponse(['user' => $user], 'Вход выполнен');