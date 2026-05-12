<?php
require_once '../config.php';
session_destroy();
successResponse([], 'Выход выполнен');