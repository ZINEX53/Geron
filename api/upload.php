<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) errorResponse('Ошибка загрузки');

$file = $_FILES['file'];
$type = $_POST['type'] ?? 'general';
$subdir = in_array($type, ['resume', 'request']) ? $type . 's/' : 'general/';
$dir = __DIR__ . '/../uploads/' . $subdir;

if (!is_dir($dir)) mkdir($dir, 0755, true);

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($file['type'], $allowed)) errorResponse('Недопустимый тип файла');
if ($file['size'] > 5 * 1024 * 1024) errorResponse('Максимум 5 МБ');

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$name = uniqid() . '_' . time() . '.' . $ext;
$path = $dir . $name;

if (move_uploaded_file($file['tmp_name'], $path)) {
    $rel = 'uploads/' . $subdir . $name;
    successResponse(['path' => $rel, 'url' => '/' . $rel], 'Файл загружен');
}
errorResponse('Ошибка сохранения');