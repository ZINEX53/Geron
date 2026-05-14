<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
if (!$pdo) {
    errorResponse('Ошибка подключения к базе данных');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Все новости
if ($method === 'GET' && $action === 'all') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3;
    $stmt = $pdo->prepare("SELECT id, title, content, image, created_at FROM news WHERE is_published = 1 ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$limit]);
    successResponse(['news' => $stmt->fetchAll()]);
}

// Одна новость
if ($method === 'GET' && $action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) errorResponse('Неверный ID');
    
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ? AND is_published = 1");
    $stmt->execute([$id]);
    $news = $stmt->fetch();
    
    if (!$news) errorResponse('Новость не найдена', 404);
    successResponse(['news' => $news]);
}

errorResponse('Неверный запрос', 400);