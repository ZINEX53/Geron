<?php
require_once 'config.php';
requireAdmin();
$admin = getAdmin();
$pdo = getDB();

// Статистика
$newRequests = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'new'")->fetchColumn();
$inProgress = $pdo->query("SELECT COUNT(*) FROM requests WHERE status = 'in_progress'")->fetchColumn();
$newResumes = $pdo->query("SELECT COUNT(*) FROM resumes WHERE status = 'new'")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель | ГЕРОН-АВТО</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-logo">
                <i class="fas fa-truck-moving"></i>
                <span>ГЕРОН-АВТО</span>
            </div>
            <nav class="sidebar-nav">
                <a href="index.php" class="active"><i class="fas fa-home"></i> Главная</a>
                <a href="requests.php"><i class="fas fa-tools"></i> Заявки на ремонт</a>
                <a href="resumes.php"><i class="fas fa-briefcase"></i> Заявки на вакансии</a>
                <a href="employees.php"><i class="fas fa-users"></i> Сотрудники</a>
                <a href="vacancies.php"><i class="fas fa-list-ul"></i> Вакансии</a>
                <a href="import-price.php"><i class="fas fa-file-import"></i> Импорт прайса</a>
                <a href="reviews.php"><i class="fas fa-star"></i> Отзывы</a>
                <a href="profile.php"><i class="fas fa-user-cog"></i> Профиль</a>
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
            </nav>
        </aside>
        <main class="main-content">
            <header class="top-bar">
                <h1>Панель управления</h1>
                <span class="admin-name"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($admin['name']) ?></span>
            </header>
            <div class="stats-grid">
                <div class="stat-card stat-new">
                    <i class="fas fa-file-alt"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= $newRequests ?></span>
                        <span class="stat-label">Новых заявок на ремонт</span>
                    </div>
                </div>
                <div class="stat-card stat-progress">
                    <i class="fas fa-spinner"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= $inProgress ?></span>
                        <span class="stat-label">В работе</span>
                    </div>
                </div>
                <div class="stat-card stat-resume">
                    <i class="fas fa-briefcase"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= $newResumes ?></span>
                        <span class="stat-label">Новых резюме</span>
                    </div>
                </div>
                <div class="stat-card stat-users">
                    <i class="fas fa-user"></i>
                    <div class="stat-info">
                        <span class="stat-value"><?= $totalUsers ?></span>
                        <span class="stat-label">Пользователей</span>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>