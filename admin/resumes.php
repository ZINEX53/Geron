<?php
require_once 'config.php';
requireAdmin();
$pdo = getDB();

// Смена статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)$_POST['id'];
    
    if ($_POST['action'] === 'update_status') {
        $status = $_POST['status'];
        $pdo->prepare("UPDATE resumes SET status = ? WHERE id = ?")->execute([$status, $id]);
        header('Location: resumes.php?updated=1');
        exit;
    }
    
    if ($_POST['action'] === 'delete') {
        $pdo->prepare("DELETE FROM resumes WHERE id = ?")->execute([$id]);
        header('Location: resumes.php?deleted=1');
        exit;
    }
}

$statusFilter = $_GET['status'] ?? 'all';
$sql = "SELECT * FROM resumes";
$params = [];
if ($statusFilter !== 'all') {
    $sql .= " WHERE status = ?";
    $params[] = $statusFilter;
}
$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$resumes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки на вакансии | Админ-панель</title>
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
            <a href="resumes.php" class="active"><i class="fas fa-briefcase"></i> Заявки на вакансии</a>
            <a href="employees.php"><i class="fas fa-users"></i> Сотрудники</a>
            <a href="vacancies.php"><i class="fas fa-list-ul"></i> Вакансии</a>
            <a href="import-price.php"><i class="fas fa-file-import"></i> Импорт прайса</a>
            <a href="reviews.php"><i class="fas fa-star"></i> Отзывы</a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> Профиль</a>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>Заявки на вакансии</h1></div>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Статус обновлён</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Заявка удалена</div>
        <?php endif; ?>

        <div class="filter-bar" style="margin-bottom:20px">
            <form>
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Все</option>
                    <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>Новые</option>
                    <option value="viewed" <?= $statusFilter === 'viewed' ? 'selected' : '' ?>>Просмотрены</option>
                    <option value="accepted" <?= $statusFilter === 'accepted' ? 'selected' : '' ?>>Приняты</option>
                    <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Отклонены</option>
                </select>
                <?php if ($statusFilter !== 'all'): ?>
                    <a href="resumes.php" class="btn btn-small">Сбросить</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr><th>ID</th><th>Должность</th><th>ФИО</th><th>Телефон</th><th>Email</th><th>Статус</th><th>Дата</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($resumes as $r): ?>
                    <tr>
                        <td>#<?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['position']) ?></td>
                        <td><?= htmlspecialchars($r['full_name']) ?></td>
                        <td><?= htmlspecialchars($r['phone']) ?></td>
                        <td><?= htmlspecialchars($r['email']) ?></td>
                        <td><span class="status status-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                        <td><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-small" onclick="openResumeModal(<?= htmlspecialchars(json_encode($r)) ?>)"><i class="fas fa-eye"></i></button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Удалить?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                    <button class="btn btn-danger btn-small"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div class="modal-overlay" id="resumeModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Заявка на вакансию</h3>
            <button class="modal-close" onclick="closeResumeModal()">&times;</button>
        </div>
        <div class="modal-body" id="resumeModalBody"></div>
    </div>
</div>

<script>
function openResumeModal(data) {
    const body = document.getElementById('resumeModalBody');
    body.innerHTML = `
        <div class="form-group"><label>Должность</label><p><strong>${data.position}</strong></p></div>
        <div class="form-group"><label>ФИО</label><p>${data.full_name}</p></div>
        <div class="form-group"><label>Телефон</label><p><a href="tel:${data.phone}">${data.phone}</a></p></div>
        <div class="form-group"><label>Email</label><p><a href="mailto:${data.email}">${data.email}</a></p></div>
        ${data.call_time ? `<div class="form-group"><label>Время звонка</label><p>${data.call_time}</p></div>` : ''}
        ${data.age ? `<div class="form-group"><label>Возраст</label><p>${data.age} лет</p></div>` : ''}
        ${data.experience ? `<div class="form-group"><label>Опыт</label><p>${data.experience}</p></div>` : ''}
        ${data.comment ? `<div class="form-group"><label>Комментарий</label><p>${data.comment}</p></div>` : ''}
        <form method="POST">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="id" value="${data.id}">
            <div class="form-group">
                <label>Статус</label>
                <select name="status">
                    <option value="new" ${data.status === 'new' ? 'selected' : ''}>Новая</option>
                    <option value="viewed" ${data.status === 'viewed' ? 'selected' : ''}>Просмотрена</option>
                    <option value="accepted" ${data.status === 'accepted' ? 'selected' : ''}>Принята</option>
                    <option value="rejected" ${data.status === 'rejected' ? 'selected' : ''}>Отклонена</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Сохранить</button>
        </form>
    `;
    document.getElementById('resumeModal').classList.add('active');
}

function closeResumeModal() {
    document.getElementById('resumeModal').classList.remove('active');
}

document.getElementById('resumeModal').addEventListener('click', function(e) {
    if (e.target === this) closeResumeModal();
});
</script>
</body>
</html>