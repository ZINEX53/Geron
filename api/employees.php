<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
$employees = $pdo->query("SELECT * FROM employees WHERE is_active = 1 ORDER BY sort_order")->fetchAll();
successResponse(['employees' => $employees]);