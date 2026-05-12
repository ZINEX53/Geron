<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user) successResponse(['user' => $user, 'loggedIn' => true]);
}
successResponse(['loggedIn' => false]);