<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

$pdo = getDB();
if (!$pdo) {
    errorResponse('Ошибка подключения к базе данных');
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// CREATE
if ($method === 'POST' && $action === 'create') {
    if (!isLoggedIn()) {
        errorResponse('Необходимо авторизоваться', 401);
    }
    
    $d = json_decode(file_get_contents('php://input'), true);
    
    $svc = trim($d['service_type'] ?? '');
    $veh = trim($d['vehicle_model'] ?? '');
    $prob = trim($d['problem_description'] ?? '');
    $date = trim($d['preferred_date'] ?? '');
    $phone = trim($d['phone'] ?? '');
    $photo = $d['photo'] ?? null;
    
    if (empty($svc)) errorResponse('Выберите тип услуги');
    if (empty($veh)) errorResponse('Укажите модель автомобиля');
    if (empty($prob)) errorResponse('Опишите проблему');
    if (empty($date)) errorResponse('Выберите дату');
    if (empty($phone)) errorResponse('Укажите телефон');
    
    $stmt = $pdo->prepare("INSERT INTO requests (user_id, service_type, vehicle_model, problem_description, photo, preferred_date, phone) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([$_SESSION['user_id'], $svc, $veh, $prob, $photo, $date, $phone]);
    
    $id = $pdo->lastInsertId();
    
    logToFile('requests_log.txt', sprintf(
        "[%s] Заявка #%d | %s | %s | %s | %s | %s\n---\n",
        date('Y-m-d H:i:s'), $id, $svc, $veh, $prob, $date, $phone
    ));
    
    try {
        $subject = "Новая заявка на ремонт #{$id} - ГЕРОН-АВТО";
        
        $photoBlock = '';
        if ($photo) {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base = $proto . '://' . $_SERVER['HTTP_HOST'];
            $photoBlock = '
            <div class="section">
                <span class="section-title">📸 Фото неисправности</span>
                <div style="text-align:center;padding:10px">
                    <a href="' . $base . '/' . $photo . '"><img src="' . $base . '/' . $photo . '" style="max-width:100%;border-radius:8px"></a>
                </div>
            </div>';
        }
        
        $body = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { margin: 0; padding: 0; font-family: "Segoe UI", Arial, sans-serif; background: #f0f2f5; }
                .wrapper { max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
                .header { background: linear-gradient(135deg, #1a2b4c, #2d3f63); padding: 30px; text-align: center; }
                .header h1 { color: #ffffff; margin: 0 0 5px; font-size: 22px; }
                .header .id { display: inline-block; background: #ff6b35; color: #fff; padding: 5px 15px; border-radius: 20px; font-size: 13px; font-weight: 600; margin-top: 10px; }
                .content { padding: 30px; }
                .section { margin-bottom: 25px; }
                .section-title { font-size: 16px; color: #1a2b4c; font-weight: 700; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 2px solid #ff6b35; display: inline-block; }
                .info-row { display: flex; padding: 12px 15px; background: #f8f9fa; border-radius: 8px; margin-bottom: 8px; align-items: center; }
                .info-label { color: #666; font-size: 13px; min-width: 100px; font-weight: 600; }
                .info-value { color: #1a2b4c; font-size: 15px; font-weight: 500; }
                .highlight { color: #ff6b35; font-weight: 700; }
                .text-block { background: #f8f9fa; padding: 15px; border-radius: 8px; color: #333; font-size: 14px; line-height: 1.6; border-left: 3px solid #ff6b35; }
                .footer { background: #1a2b4c; padding: 20px; text-align: center; }
                .footer p { color: #8899aa; margin: 0; font-size: 12px; }
                .footer .company { color: #ff6b35; font-weight: 700; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="wrapper">
                <div class="header">
                    <h1>🔧 Новая заявка на ремонт</h1>
                    <div class="id">Заявка #' . $id . '</div>
                </div>
                <div class="content">
                    <div class="section">
                        <span class="section-title">📋 Услуга</span>
                        <div class="info-row">
                            <span class="info-label">Тип:</span>
                            <span class="info-value highlight">' . htmlspecialchars($svc) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Автомобиль:</span>
                            <span class="info-value">' . htmlspecialchars($veh) . '</span>
                        </div>
                    </div>
                    
                    <div class="section">
                        <span class="section-title">📝 Описание</span>
                        <div class="text-block">' . nl2br(htmlspecialchars($prob)) . '</div>
                    </div>
                    
                    ' . $photoBlock . '
                    
                    <div class="section">
                        <span class="section-title">📅 Детали</span>
                        <div class="info-row">
                            <span class="info-label">Дата:</span>
                            <span class="info-value">' . htmlspecialchars($date) . '</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Телефон:</span>
                            <span class="info-value"><a href="tel:' . htmlspecialchars($phone) . '" style="color:#1a2b4c;text-decoration:none">' . htmlspecialchars($phone) . '</a></span>
                        </div>
                    </div>
                </div>
                <div class="footer">
                    <p class="company">ГЕРОН-АВТО</p>
                    <p>г. Санкт-Петербург, пр-кт. Екатериненский, 3 литера Б</p>
                    <p>Заявка создана: ' . date('d.m.Y H:i') . '</p>
                </div>
            </div>
        </body>
        </html>';
        
        $sent = sendEmail(MAIL_TO, $subject, $body);
        if (!$sent) {
            error_log("Email для заявки #{$id} НЕ отправлен");
        }
    } catch (Exception $e) {
        error_log("Ошибка email для заявки #{$id}: " . $e->getMessage());
    }
    
    successResponse(['request_id' => $id], 'Заявка создана');
}

// MY
if ($method === 'GET' && $action === 'my') {
    if (!isLoggedIn()) {
        errorResponse('Необходимо авторизоваться', 401);
    }
    $stmt = $pdo->prepare("SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    successResponse(['requests' => $stmt->fetchAll()]);
}

// CANCEL
if ($method === 'POST' && $action === 'cancel') {
    if (!isLoggedIn()) {
        errorResponse('Необходимо авторизоваться', 401);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if ($id <= 0) errorResponse('Неверный ID заявки');
    
    $stmt = $pdo->prepare("UPDATE requests SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    if ($stmt->rowCount() === 0) errorResponse('Заявка не найдена', 404);
    successResponse([], 'Заявка отменена');
}

errorResponse('Неверный запрос', 400);