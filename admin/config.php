<?php
require_once __DIR__ . '/../config.php';

// Проверка админа
function isAdmin(): bool {
    return isset($_SESSION['admin_id']);
}

function getAdmin(): ?array {
    if (!isAdmin()) return null;
    $pdo = getDB();
    if (!$pdo) return null;
    $stmt = $pdo->prepare("SELECT id, username, name, email, phone FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    return $stmt->fetch() ?: null;
}

function requireAdmin(): void {
    if (!isAdmin()) {
        header('Location: login.php');
        exit;
    }
}