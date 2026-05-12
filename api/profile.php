<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (!isLoggedIn()) errorResponse('Необходимо авторизоваться', 401);
    $user = getCurrentUser();
    if (!$user) errorResponse('Пользователь не найден', 404);
    
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM requests WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total = $stmt->fetch()['c'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as c FROM requests WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$_SESSION['user_id']]);
    $completed = $stmt->fetch()['c'];
    
    successResponse(['user' => $user, 'stats' => ['total_requests' => $total, 'completed_requests' => $completed]]);
}

if ($method === 'POST') {
    if (!isLoggedIn()) errorResponse('Необходимо авторизоваться', 401);
    $data = json_decode(file_get_contents('php://input'), true);
    $name = trim($data['name'] ?? '');
    $phone = trim($data['phone'] ?? '');
    if (empty($name)) errorResponse('Введите имя');
    
    $pdo = getDB();
    $params = [$name, $phone];
    $sql = "UPDATE users SET name = ?, phone = ?";
    
    if (!empty($data['current_password']) && !empty($data['new_password'])) {
        if (strlen($data['new_password']) < 6) errorResponse('Новый пароль минимум 6 символов');
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if (!password_verify($data['current_password'], $stmt->fetch()['password'])) errorResponse('Неверный текущий пароль');
        $sql .= ", password = ?";
        $params = [$name, $phone, password_hash($data['new_password'], PASSWORD_DEFAULT)];
    }
    $params[] = $_SESSION['user_id'];
    $sql .= " WHERE id = ?";
    $pdo->prepare($sql)->execute($params);
    successResponse([], 'Профиль обновлен');
}

errorResponse('Неверный запрос', 400);