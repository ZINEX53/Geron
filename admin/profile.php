<?php
require_once 'config.php';
requireAdmin();
$admin = getAdmin();
$pdo = getDB();
$message = '';
$error = '';

// Обновление профиля
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
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

// Добавление нового админа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $email = trim($_POST['admin_email'] ?? '');
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Введите корректный email';
    } else {
        // Проверяем, есть ли такой админ уже
        $stmt = $pdo->prepare("SELECT id FROM admins WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Этот пользователь уже администратор';
        } else {
            // Проверяем, есть ли пользователь с таким email
            $stmt = $pdo->prepare("SELECT id, name FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error = 'Пользователь с таким email не найден на сайте';
            } else {
                // Генерируем логин и пароль
                $login = 'admin_' . substr(md5($email . time()), 0, 8);
                $password = substr(bin2hex(random_bytes(4)), 0, 8);
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Добавляем админа
                $stmt = $pdo->prepare("INSERT INTO admins (username, password, name, email, phone, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$login, $hash, $user['name'], $email, null, $_SESSION['admin_id']]);
                
                // Отправляем письмо
                $subject = "Вы стали администратором ГЕРОН-АВТО";
                $body = "
                <div style='max-width:600px;margin:0 auto;font-family:Arial,sans-serif'>
                    <div style='background:#1a2b4c;color:#fff;padding:25px;text-align:center'>
                        <h1>ГЕРОН-АВТО</h1>
                        <p>Вы назначены администратором</p>
                    </div>
                    <div style='padding:25px;background:#fff'>
                        <p>Здравствуйте, <b>{$user['name']}</b>!</p>
                        <p>Вам предоставлен доступ к админ-панели автосервиса ГЕРОН-АВТО.</p>
                        <div style='background:#f8f9fa;padding:20px;border-radius:8px;margin:20px 0'>
                            <p><b>Логин:</b> <span style='color:#ff6b35;font-size:18px'>{$login}</span></p>
                            <p><b>Пароль:</b> <span style='color:#ff6b35;font-size:18px'>{$password}</span></p>
                        </div>
                        <p><b>Ссылка для входа:</b> <a href='http://localhost/geroin/admin/login.php'>http://localhost/geroin/admin/login.php</a></p>
                        <p style='color:#999;font-size:13px'>Рекомендуем сменить пароль после первого входа.</p>
                    </div>
                </div>";
                
                sendEmail($email, $subject, $body);
                $message = "Админ {$login} создан. Данные отправлены на {$email}";
            }
        }
    }
}

// Понижение админа
if (isset($_GET['demote'])) {
    $id = (int)$_GET['demote'];
    if ($id == $_SESSION['admin_id']) {
        $error = 'Нельзя понизить самого себя';
    } else {
        $stmt = $pdo->prepare("UPDATE admins SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Администратор отключён';
    }
}

// Повышение обратно
if (isset($_GET['activate'])) {
    $id = (int)$_GET['activate'];
    $stmt = $pdo->prepare("UPDATE admins SET is_active = 1 WHERE id = ?");
    $stmt->execute([$id]);
    $message = 'Администратор активирован';
}

// Список всех админов
$allAdmins = $pdo->query("SELECT * FROM admins ORDER BY is_active DESC, created_at DESC")->fetchAll();
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
        
        <?php if ($message): ?><div class="alert alert-success"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="card">
            <h3>Мои данные</h3>
            <form method="POST">
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group"><label>Имя</label><input type="text" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required></div>
                <div class="form-group"><label>Телефон</label><input type="tel" name="phone" value="<?= htmlspecialchars($admin['phone'] ?? '') ?>"></div>
                <div class="form-group"><label>Логин</label><input type="text" name="username" value="<?= htmlspecialchars($admin['username']) ?>" required></div>
                <hr>
                <h4>Смена пароля</h4>
                <div class="form-group"><label>Текущий пароль *</label><input type="password" name="current_password" required></div>
                <div class="form-group"><label>Новый пароль</label><input type="password" name="new_password" placeholder="Оставьте пустым, чтобы не менять"></div>
                <div class="form-group"><label>Подтвердите пароль</label><input type="password" name="confirm_password"></div>
                <button type="submit" class="btn btn-primary">Сохранить</button>
            </form>
        </div>

        <div class="card">
            <h3>Добавить администратора</h3>
            <p style="color:#666;margin-bottom:15px">Пользователь с таким email должен быть зарегистрирован на сайте.</p>
            <form method="POST">
                <input type="hidden" name="action" value="add_admin">
                <div class="form-group"><label>Email пользователя</label><input type="email" name="admin_email" placeholder="user@example.com" required></div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Сделать админом</button>
            </form>
        </div>

        <div class="card">
            <h3>Все администраторы</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Логин</th><th>Имя</th><th>Email</th><th>Телефон</th><th>Статус</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allAdmins as $a): ?>
                        <tr>
                            <td><?= $a['id'] ?></td>
                            <td><?= htmlspecialchars($a['username']) ?></td>
                            <td><?= htmlspecialchars($a['name']) ?></td>
                            <td><?= htmlspecialchars($a['email']) ?></td>
                            <td><?= htmlspecialchars($a['phone'] ?? '—') ?></td>
                            <td><span class="status <?= $a['is_active'] ? 'status-completed' : 'status-cancelled' ?>"><?= $a['is_active'] ? 'Активен' : 'Отключён' ?></span></td>
                            <td>
                                <?php if ($a['id'] != $_SESSION['admin_id']): ?>
                                    <?php if ($a['is_active']): ?>
                                        <a href="?demote=<?= $a['id'] ?>" class="btn btn-warning btn-small" onclick="return confirm('Понизить до пользователя?')"><i class="fas fa-arrow-down"></i> Понизить</a>
                                    <?php else: ?>
                                        <a href="?activate=<?= $a['id'] ?>" class="btn btn-success btn-small"><i class="fas fa-arrow-up"></i> Активировать</a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color:#999">Это вы</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>
</body>
</html>