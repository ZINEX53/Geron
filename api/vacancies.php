<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$vacancies = $pdo->query("SELECT * FROM vacancies WHERE is_active = 1 ORDER BY id")->fetchAll();
successResponse(['vacancies' => $vacancies]);