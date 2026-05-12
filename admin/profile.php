<?php
require_once 'config.php';
requireAdmin();
$admin = getAdmin();
$pdo = getDB();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // Проверка текущего пароля
    $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
    $stmt->execute([$_SESSION['admin_id']]);
    $currentHash = $stmt->fetch()['password'];
    
    if (!password_verify($currentPassword, $currentHash)) {
        $error = 'Неверный текущий пароль';
    } elseif ($newPassword && $newPassword !== $confirmPassword) {
        $error = 'Новые пароли не совпадают';
    } elseif ($newPassword && strlen($newPassword) < 6) {
        $error = 'Новый пароль минимум 6 символов';
    } else {
        // Проверка уникальности username
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? AND id != ?");
        $stmt->execute([$username, $_SESSION['admin_id']]);
        if ($stmt->fetch()) {
            $error = 'Логин уже занят';
        } else {
            if ($newPassword) {
                $stmt = $pdo->prepare("UPDATE admins SET name=?, email=?, phone=?, username=?, password=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $username, password_hash($newPassword, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET name=?, email=?, phone=?, username=? WHERE id=?");
                $stmt->execute([$name, $email, $phone, $username, $_SESSION['admin_id']]);
            }
            $message = 'Профиль обновлён';
            $admin = getAdmin();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль | Админ-панель</title>
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
            <a href="reviews.php"><i class="fas fa-star"></i> Отзывы</a>
            <a href="profile.php" class="active"><i class="fas fa-user-cog"></i> Профиль</a>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>Профиль администратора</h1></div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

        <div class="card" style="max-width:600px">
            <h3>Редактировать данные</h3>
            <form method="POST">
                <div class="form-group"><label>Имя</label><input type="text" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required></div>
                <div class="form-group"><label>Телефон</label><input type="tel" name="phone" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>"></div>
                <div class="form-group"><label>Логин</label><input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required></div>
                <hr style="margin:20px 0;border:none;border-top:1px solid #eee">
                <h4 style="margin-bottom:15px;color:var(--dark)">Смена пароля</h4>
                <div class="form-group"><label>Текущий пароль *</label><input type="password" name="current_password" required></div>
                <div class="form-group"><label>Новый пароль</label><input type="password" name="new_password" placeholder="Оставьте пустым, чтобы не менять"></div>
                <div class="form-group"><label>Подтвердите новый пароль</label><input type="password" name="confirm_password"></div>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </form>
        </div>
    </main>
</div>
</body>
</html>