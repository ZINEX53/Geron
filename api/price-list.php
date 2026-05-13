<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
if (!$pdo) errorResponse('Ошибка БД');

$category = $_GET['category'] ?? 'all';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM price_list WHERE is_active = 1";
$params = [];

if ($category !== 'all') {
    $sql .= " AND category = ?";
    $params[] = $category;
}

if (!empty($search)) {
    $sql .= " AND (service_name LIKE ? OR description LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY sort_order, category, service_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Категории для фильтра
$categories = $pdo->query("SELECT DISTINCT category FROM price_list WHERE is_active = 1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

successResponse(['items' => $items, 'categories' => $categories]);