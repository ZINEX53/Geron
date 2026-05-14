<?php
require_once 'config.php';
requireAdmin();
$pdo = getDB();

// Добавление/редактирование
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $salary = trim($_POST['salary'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-wrench');
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $editId = $_POST['edit_id'] ?? null;
    
    if ($editId) {
        $stmt = $pdo->prepare("UPDATE vacancies SET title=?, description=?, salary=?, icon=?, is_active=? WHERE id=?");
        $stmt->execute([$title, $description, $salary, $icon, $isActive, $editId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO vacancies (title, description, salary, icon, is_active) VALUES (?,?,?,?,?)");
        $stmt->execute([$title, $description, $salary, $icon, $isActive]);
    }
    header('Location: vacancies.php');
    exit;
}

// Удаление
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM vacancies WHERE id = ?")->execute([(int)$_GET['delete']]);
    header('Location: vacancies.php');
    exit;
}

$vacancies = $pdo->query("SELECT * FROM vacancies ORDER BY id")->fetchAll();
$editVacancy = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM vacancies WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editVacancy = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вакансии | Админ-панель</title>
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
    <a href="resumes.php"><i class="fa-briefcase"></i> Заявки на вакансии</a>
    <a href="employees.php"><i class="fas fa-users"></i> Сотрудники</a>
    <a href="vacancies.php" class="active"><i class="fas fa-list-ul"></i> Вакансии</a>
    <a href="import-price.php"><i class="fas fa-file-import"></i> Импорт прайса</a>
    <a href="reviews.php"><i class="fa-star"></i> Отзывы</a>
    <a href="news.php"><i class="fas fa-newspaper"></i> Новости</a>
    <a href="profile.php"><i class="fas fa-user-cog"></i> Профиль</a>
    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</nav>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>Вакансии</h1></div>
        
        <div class="card">
            <h3><?= $editVacancy ? 'Редактировать вакансию' : 'Добавить вакансию' ?></h3>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?= $editVacancy['id'] ?? '' ?>">
                <div class="form-row">
                    <div class="form-group"><label>Название</label><input type="text" name="title" value="<?= htmlspecialchars($editVacancy['title'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Зарплата</label><input type="text" name="salary" value="<?= htmlspecialchars($editVacancy['salary'] ?? '') ?>" placeholder="от 85 000 ₽"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Иконка (Font Awesome)</label>
                        <select name="icon">
                            <option value="fa-wrench" <?= ($editVacancy && $editVacancy['icon'] === 'fa-wrench') ? 'selected' : '' ?>>fa-wrench (Гаечный ключ)</option>
                            <option value="fa-bolt" <?= ($editVacancy && $editVacancy['icon'] === 'fa-bolt') ? 'selected' : '' ?>>fa-bolt (Молния)</option>
                            <option value="fa-search" <?= ($editVacancy && $editVacancy['icon'] === 'fa-search') ? 'selected' : '' ?>>fa-search (Поиск)</option>
                            <option value="fa-tools" <?= ($editVacancy && $editVacancy['icon'] === 'fa-tools') ? 'selected' : '' ?>>fa-tools (Инструменты)</option>
                            <option value="fa-cog" <?= ($editVacancy && $editVacancy['icon'] === 'fa-cog') ? 'selected' : '' ?>>fa-cog (Шестерня)</option>
                            <option value="fa-car" <?= ($editVacancy && $editVacancy['icon'] === 'fa-car') ? 'selected' : '' ?>>fa-car (Автомобиль)</option>
                        </select>
                    </div>
                </div>
                <div class="form-group"><label>Описание</label><textarea name="description" rows="2"><?= htmlspecialchars($editVacancy['description'] ?? '') ?></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="is_active" <?= ($editVacancy && $editVacancy['is_active']) || !$editVacancy ? 'checked' : '' ?>> Активна</label></div>
                <button type="submit" class="btn btn-primary"><?= $editVacancy ? 'Сохранить' : 'Добавить' ?></button>
                <?php if ($editVacancy): ?>
                    <a href="vacancies.php" class="btn">Отмена</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead><tr><th>ID</th><th>Название</th><th>Зарплата</th><th>Иконка</th><th>Активна</th><th>Действия</th></tr></thead>
                <tbody>
                    <?php foreach ($vacancies as $v): ?>
                    <tr>
                        <td><?= $v['id'] ?></td>
                        <td><?= htmlspecialchars($v['title']) ?></td>
                        <td><?= htmlspecialchars($v['salary']) ?></td>
                        <td><i class="fas <?= $v['icon'] ?>"></i> <?= $v['icon'] ?></td>
                        <td><?= $v['is_active'] ? '✅' : '❌' ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="?edit=<?= $v['id'] ?>" class="btn btn-primary btn-small"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?= $v['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('Удалить?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>