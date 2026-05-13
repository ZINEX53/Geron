<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    successResponse(['isAdmin' => false]);
    exit;
}

$user = getCurrentUser();
if (!$user) {
    successResponse(['isAdmin' => false]);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
$stmt->execute([$user['email']]);
$admin = $stmt->fetch();

successResponse(['isAdmin' => (bool)$admin]);