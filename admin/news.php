<?php
require_once 'config.php';
requireAdmin();
$pdo = getDB();

// Добавление/редактирование
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $editId = $_POST['edit_id'] ?? null;
    
    // Загрузка картинки
    $image = $_POST['existing_image'] ?? null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (in_array($file['type'], $allowed) && $file['size'] <= 5 * 1024 * 1024) {
            $dir = __DIR__ . '/../uploads/news/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'news_' . uniqid() . '.' . $ext;
            move_uploaded_file($file['tmp_name'], $dir . $filename);
            $image = 'uploads/news/' . $filename;
        }
    }
    
    if (empty($title) || empty($content)) {
        $error = 'Заполните заголовок и текст';
    } else {
        if ($editId) {
            $stmt = $pdo->prepare("UPDATE news SET title=?, content=?, image=?, is_published=? WHERE id=?");
            $stmt->execute([$title, $content, $image, $isPublished, $editId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO news (title, content, image, is_published) VALUES (?,?,?,?)");
            $stmt->execute([$title, $content, $image, $isPublished]);
        }
        header('Location: news.php?ok=1');
        exit;
    }
}

// Удаление
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM news WHERE id = ?")->execute([(int)$_GET['delete']]);
    header('Location: news.php?deleted=1');
    exit;
}

$news = $pdo->query("SELECT * FROM news ORDER BY created_at DESC")->fetchAll();
$editNews = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editNews = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Новости | Админ-панель</title>
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
    <a href="vacancies.php"><i class="fas fa-list-ul"></i> Вакансии</a>
    <a href="import-price.php"><i class="fas fa-file-import"></i> Импорт прайса</a>
    <a href="reviews.php"><i class="fa-star"></i> Отзывы</a>
    <a href="news.php" class="active"><i class="fas fa-newspaper"></i> Новости</a>
    <a href="profile.php"><i class="fas fa-user-cog"></i> Профиль</a>
    <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</nav>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>Новости</h1></div>
        
        <?php if (isset($_GET['ok'])): ?>
            <div class="alert alert-success">Новость сохранена</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Новость удалена</div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="card">
            <h3><?= $editNews ? 'Редактировать новость' : 'Добавить новость' ?></h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="edit_id" value="<?= $editNews['id'] ?? '' ?>">
                <input type="hidden" name="existing_image" value="<?= $editNews['image'] ?? '' ?>">
                
                <div class="form-group">
                    <label>Заголовок</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($editNews['title'] ?? '') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Текст новости</label>
                    <textarea name="content" rows="6" required><?= htmlspecialchars($editNews['content'] ?? '') ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Картинка</label>
                    <?php if (!empty($editNews['image'])): ?>
                        <div style="margin-bottom:10px">
                            <img src="../<?= $editNews['image'] ?>" style="max-width:200px;border-radius:8px">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_published" <?= ($editNews && $editNews['is_published']) || !$editNews ? 'checked' : '' ?>>
                        Опубликовать
                    </label>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary"><?= $editNews ? 'Сохранить' : 'Добавить' ?></button>
                    <?php if ($editNews): ?>
                        <a href="news.php" class="btn">Отмена</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr><th>ID</th><th>Заголовок</th><th>Дата</th><th>Статус</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($news as $n): ?>
                    <tr>
                        <td><?= $n['id'] ?></td>
                        <td><?= htmlspecialchars($n['title']) ?></td>
                        <td><?= date('d.m.Y H:i', strtotime($n['created_at'])) ?></td>
                        <td><?= $n['is_published'] ? '✅ Опубликована' : '❌ Скрыта' ?></td>
                        <td>
                            <div class="btn-group">
                                <a href="?edit=<?= $n['id'] ?>" class="btn btn-primary btn-small"><i class="fas fa-edit"></i></a>
                                <a href="?delete=<?= $n['id'] ?>" class="btn btn-danger btn-small" onclick="return confirm('Удалить новость?')"><i class="fas fa-trash"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($news)): ?>
                        <tr><td colspan="5" style="text-align:center;color:#999">Нет новостей</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>