<?php
require_once 'config.php';

if (isAdmin()) {
    header('Location: index.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Заполните все поля';
    } else {
        $pdo = getDB();
        if (!$pdo) {
            $error = 'Ошибка подключения к базе данных';
        } else {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                header('Location: index.php');
                exit;
            } else {
                $error = 'Неверный логин или пароль';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель ГЕРОН-АВТО</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <div class="login-logo">
                <i class="fas fa-truck-moving"></i>
                <h1>ГЕРОН-<span>АВТО</span></h1>
                <p>Админ-панель</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" novalidate>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Логин</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required autofocus autocomplete="off">
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Пароль</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Войти</button>
            </form>

            <div style="text-align:center;margin-top:20px">
                <a href="../GERON.HTML" style="color:#ff6b35;text-decoration:none;font-size:14px">
                    <i class="fas fa-arrow-left"></i> Вернуться на сайт
                </a>
            </div>
        </div>
    </div>
</body>
</html>