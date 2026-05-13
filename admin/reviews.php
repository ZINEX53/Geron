<?php
require_once 'config.php';
requireAdmin();
$pdo = getDB();

// Удаление
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM reviews WHERE id = ?")->execute([(int)$_GET['delete']]);
    header('Location: reviews.php?deleted=1');
    exit;
}

$reviews = $pdo->query("
    SELECT r.*, u.name as user_name, u.email as user_email, DATE_FORMAT(r.created_at, '%d.%m.%Y %H:%i') as date_formatted 
    FROM reviews r JOIN users u ON r.user_id = u.id 
    ORDER BY r.created_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отзывы | Админ-панель</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-logo"><i class="fas fa-truck-moving"></i><span>ГЕРОН-АВТО</span></div>
        <nav class="sidebar-nav">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="requests.php"><i class="fas fa-tools"></i> Заявки на ремонт</a>
            <a href="resumes.php"><i class="fas fa-briefcase"></i> Заявки на вакансии</a>
            <a href="employees.php"><i class="fas fa-users"></i> Сотрудники</a>
            <a href="vacancies.php"><i class="fas fa-list-ul"></i> Вакансии</a>
            <a href="import-price.php"><i class="fas fa-file-import"></i> Импорт прайса</a>
            <a href="reviews.php" class="active"><i class="fas fa-star"></i> Отзывы</a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> Профиль</a>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>Отзывы</h1></div>
        
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Отзыв удалён</div>
        <?php endif; ?>

        <div class="table-container">
            <table>
                <thead><tr><th>ID</th><th>Пользователь</th><th>Оценка</th><th>Текст</th><th>Дата</th><th>Действия</th></tr></thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td><?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['user_name']) ?><br><small><?= $r['user_email'] ?></small></td>
                        <td><?= str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) ?></td>
                        <td style="max-width:300px"><?= htmlspecialchars(mb_substr($r['comment'], 0, 100)) ?>...</td>
                        <td><?= $r['date_formatted'] ?></td>
                        <td>
                            <a href="?delete=<?= $r['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('Удалить отзыв?')"><i class="fas fa-trash"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reviews)): ?>
                        <tr><td colspan="6" style="text-align:center;color:#999">Нет отзывов</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>