<?php
require_once 'config.php';
requireAdmin();
$pdo = getDB();

// Добавление/редактирование
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $experience = trim($_POST['experience'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sortOrder = (int)($_POST['sort_order'] ?? 0);
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $editId = $_POST['edit_id'] ?? null;
    
    if ($editId) {
        $stmt = $pdo->prepare("UPDATE employees SET name=?, position=?, experience=?, description=?, sort_order=?, is_active=? WHERE id=?");
        $stmt->execute([$name, $position, $experience, $description, $sortOrder, $isActive, $editId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO employees (name, position, experience, description, sort_order, is_active) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$name, $position, $experience, $description, $sortOrder, $isActive]);
    }
    header('Location: employees.php');
    exit;
}

// Удаление
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([(int)$_GET['delete']]);
    header('Location: employees.php');
    exit;
}

$employees = $pdo->query("SELECT * FROM employees ORDER BY sort_order")->fetchAll();
$editEmployee = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editEmployee = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сотрудники | Админ-панель</title>
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
            <a href="employees.php" class="active"><i class="fas fa-users"></i> Сотрудники</a>
            <a href="vacancies.php"><i class="fas fa-list-ul"></i> Вакансии</a>
            <a href="import-price.php"><i class="fas fa-file-import"></i> Импорт прайса</a>
            <a href="reviews.php"><i class="fas fa-star"></i> Отзывы</a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> Профиль</a>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>Сотрудники</h1></div>
        
        <div class="card">
            <h3><?= $editEmployee ? 'Редактировать сотрудника' : 'Добавить сотрудника' ?></h3>
            <form method="POST">
                <input type="hidden" name="edit_id" value="<?= $editEmployee['id'] ?? '' ?>">
                <div class="form-row">
                    <div class="form-group"><label>Имя</label><input type="text" name="name" value="<?= htmlspecialchars($editEmployee['name'] ?? '') ?>" required></div>
                    <div class="form-group"><label>Должность</label><input type="text" name="position" value="<?= htmlspecialchars($editEmployee['position'] ?? '') ?>" required></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Опыт</label><input type="text" name="experience" value="<?= htmlspecialchars($editEmployee['experience'] ?? '') ?>"></div>
                    <div class="form-group"><label>Порядок</label><input type="number" name="sort_order" value="<?= $editEmployee['sort_order'] ?? 0 ?>"></div>
                </div>
                <div class="form-group"><label>Описание</label><textarea name="description" rows="2"><?= htmlspecialchars($editEmployee['description'] ?? '') ?></textarea></div>
                <div class="form-group"><label><input type="checkbox" name="is_active" <?= ($editEmployee && $editEmployee['is_active']) || !$editEmployee ? 'checked' : '' ?>> Активен</label></div>
                <button type="submit" class="btn btn-primary"><?= $editEmployee ? 'Сохранить' : 'Добавить' ?></button>
                <?php if ($editEmployee): ?>
                    <a href="employees.php" class="btn">Отмена</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead><tr><th>ID</th><th>Имя</th><th>Должность</th><th>Опыт</th><th>Активен</th><th>Действия</th></tr></thead>
                <tbody>
                    <?php foreach ($employees as $e): ?>
                    <tr>
                        <td><?= $e['id'] ?></td>
                        <td><?= htmlspecialchars($e['name']) ?></td>
                        <td><?= htmlspecialchars($e['position']) ?></td>
                        <td><?= htmlspecialchars($e['experience']) ?></td>
                        <td><?= $e['is_active'] ? '✅' : '❌' ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="?edit=<?= $e['id'] ?>" class="btn btn-primary btn-small"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?= $e['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('Удалить?')"><i class="fas fa-trash"></i></a>
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