<?php
require_once 'config.php';
requireAdmin();
$pdo = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Ошибка загрузки файла';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle, 0, ';');
        
        // Очищаем старые данные
        $pdo->exec("DELETE FROM price_list");
        
        $count = 0;
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) >= 4) {
                $stmt = $pdo->prepare("INSERT INTO price_list (category, service_name, description, price, unit, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    trim($row[0]),
                    trim($row[1]),
                    trim($row[2] ?? ''),
                    (float)str_replace(',', '.', $row[3]),
                    trim($row[4] ?? 'шт.'),
                    $count
                ]);
                $count++;
            }
        }
        fclose($handle);
        $message = "Импортировано {$count} позиций";
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Импорт прайс-листа | Админ-панель</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-logo"><i class="fas fa-truck-moving"></i><span>ГЕРОН-АВТО</span></div>
        <nav class="sidebar-nav">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="requests.php"><i class="fas fa-tools"></i> Заявки</a>
            <a href="resumes.php"><i class="fas fa-briefcase"></i> Резюме</a>
            <a href="employees.php"><i class="fas fa-users"></i> Сотрудники</a>
            <a href="vacancies.php"><i class="fas fa-list-ul"></i> Вакансии</a>
            <a href="import-price.php" class="active"><i class="fas fa-file-import"></i> Импорт прайса</a>
            <a href="reviews.php"><i class="fas fa-star"></i> Отзывы</a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> Профиль</a>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>Импорт прайс-листа из Excel</h1></div>
        
        <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

        <div class="card">
            <h3>Загрузить CSV-файл</h3>
            <p style="color:#666;margin-bottom:15px">
                Файл должен быть в формате CSV с разделителем <b>;</b>.<br>
                Колонки: <b>Категория; Услуга; Описание; Цена; Ед.изм</b>
            </p>
            <form method="POST" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Выберите CSV-файл</label>
                    <input type="file" name="excel_file" accept=".csv" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Импортировать</button>
                <a href="../price-list.html" class="btn btn-success" target="_blank"><i class="fas fa-eye"></i> Посмотреть прайс</a>
            </form>
        </div>
    </main>
</div>
</body>
</html>