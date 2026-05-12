<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
if (!$pdo) errorResponse('Ошибка БД');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// CREATE
if ($method === 'POST' && $action === 'create') {
    if (!isLoggedIn()) errorResponse('Авторизуйтесь', 401);
    $d = json_decode(file_get_contents('php://input'), true);
    $rating = (int)($d['rating'] ?? 0);
    $comment = trim($d['comment'] ?? '');
    if ($rating < 1 || $rating > 5) errorResponse('Рейтинг 1-5');
    if (empty($comment)) errorResponse('Введите текст');
    
    $stmt = $pdo->prepare("SELECT id FROM reviews WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetch()) errorResponse('Вы уже оставляли отзыв');
    
    $pdo->prepare("INSERT INTO reviews (user_id, rating, comment) VALUES (?,?,?)")->execute([$_SESSION['user_id'], $rating, $comment]);
    successResponse(['review_id' => $pdo->lastInsertId()], 'Отзыв добавлен');
}

// ALL
if ($method === 'GET' && $action === 'all') {
    $reviews = $pdo->query("SELECT r.*, u.name as user_name, DATE_FORMAT(r.created_at, '%d.%m.%Y') as date_formatted FROM reviews r JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC")->fetchAll();
    $stats = $pdo->query("SELECT AVG(rating) as avg, COUNT(*) as cnt FROM reviews")->fetch();
    successResponse(['reviews' => $reviews, 'average_rating' => round($stats['avg'] ?? 0, 1), 'total_reviews' => $stats['cnt'] ?? 0]);
}

// MY
if ($method === 'GET' && $action === 'my') {
    if (!isLoggedIn()) errorResponse('Авторизуйтесь', 401);
    $stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $review = $stmt->fetch();
    successResponse(['review' => $review, 'has_review' => (bool)$review]);
}

// UPDATE
if ($method === 'POST' && $action === 'update') {
    if (!isLoggedIn()) errorResponse('Авторизуйтесь', 401);
    $d = json_decode(file_get_contents('php://input'), true);
    $rating = (int)($d['rating'] ?? 0);
    $comment = trim($d['comment'] ?? '');
    if ($rating < 1 || $rating > 5) errorResponse('Рейтинг 1-5');
    if (empty($comment)) errorResponse('Введите текст');
    $pdo->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE user_id = ?")->execute([$rating, $comment, $_SESSION['user_id']]);
    successResponse([], 'Отзыв обновлен');
}

errorResponse('Неверный запрос', 400);