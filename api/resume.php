<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Метод не разрешен', 405);
}

$d = json_decode(file_get_contents('php://input'), true);

$position = trim($d['position'] ?? '');
$name = trim($d['full_name'] ?? '');
$phone = trim($d['phone'] ?? '');
$email = trim($d['email'] ?? '');
$callTime = trim($d['call_time'] ?? '');
$age = trim($d['age'] ?? '') ?: null;
$exp = trim($d['experience'] ?? '') ?: null;
$comment = trim($d['comment'] ?? '') ?: null;

if (!$position || !$name || !$phone || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    errorResponse('Заполните все обязательные поля');
}

if ($age && ($age < 18 || $age > 100)) {
    errorResponse('Возраст должен быть от 18 до 100 лет');
}

$pdo = getDB();
if (!$pdo) {
    errorResponse('Ошибка подключения к базе данных');
}

$stmt = $pdo->prepare("INSERT INTO resumes (position, full_name, phone, email, call_time, age, experience, comment) VALUES (?,?,?,?,?,?,?,?)");
$stmt->execute([$position, $name, $phone, $email, $callTime ?: null, $age, $exp, $comment]);

$id = $pdo->lastInsertId();

logToFile('resume_log.txt', sprintf(
    "[%s] Заявка #%d | %s | %s | %s | %s\n---\n",
    date('Y-m-d H:i:s'), $id, $position, $name, $phone, $email
));

try {
    $subject = "Новая заявка на вакансию #{$id} - ГЕРОН-АВТО";
    
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
            .info-label { color: #666; font-size: 13px; min-width: 130px; font-weight: 600; }
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
                <h1>📋 Новая заявка на вакансию</h1>
                <div class="id">Заявка #' . $id . '</div>
            </div>
            <div class="content">
                <div class="section">
                    <span class="section-title">📌 Вакансия</span>
                    <div class="info-row">
                        <span class="info-label">Должность:</span>
                        <span class="info-value highlight">' . htmlspecialchars($position) . '</span>
                    </div>
                </div>
                
                <div class="section">
                    <span class="section-title">👤 Контакты</span>
                    <div class="info-row">
                        <span class="info-label">ФИО:</span>
                        <span class="info-value">' . htmlspecialchars($name) . '</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Телефон:</span>
                        <span class="info-value"><a href="tel:' . htmlspecialchars($phone) . '" style="color:#1a2b4c;text-decoration:none">' . htmlspecialchars($phone) . '</a></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><a href="mailto:' . htmlspecialchars($email) . '" style="color:#1a2b4c;text-decoration:none">' . htmlspecialchars($email) . '</a></span>
                    </div>
                    ' . ($callTime ? '
                    <div class="info-row">
                        <span class="info-label">Время звонка:</span>
                        <span class="info-value">🕐 ' . htmlspecialchars($callTime) . '</span>
                    </div>' : '') . '
                    ' . ($age ? '
                    <div class="info-row">
                        <span class="info-label">Возраст:</span>
                        <span class="info-value">' . htmlspecialchars($age) . ' лет</span>
                    </div>' : '') . '
                </div>
                
                ' . ($exp ? '
                <div class="section">
                    <span class="section-title">💼 Опыт работы</span>
                    <div class="text-block">' . nl2br(htmlspecialchars($exp)) . '</div>
                </div>' : '') . '
                
                ' . ($comment ? '
                <div class="section">
                    <span class="section-title">💬 Комментарий</span>
                    <div class="text-block">' . nl2br(htmlspecialchars($comment)) . '</div>
                </div>' : '') . '
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
    
    if ($sent) {
        error_log("Email для заявки #{$id} отправлен успешно");
    } else {
        error_log("Email для заявки #{$id} НЕ отправлен");
    }
} catch (Exception $e) {
    error_log("Ошибка отправки email для заявки #{$id}: " . $e->getMessage());
}

successResponse(['resume_id' => $id], 'Заявка отправлена!');