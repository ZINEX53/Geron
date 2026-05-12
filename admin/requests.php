<?php
require_once 'config.php';
requireAdmin();
$pdo = getDB();

$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? '';

// Смена статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $status = $_POST['status'];
        $repairDate = $_POST['repair_date'] ?? null;
        $repairTime = $_POST['repair_time'] ?? null;
        $comment = $_POST['admin_comment'] ?? '';
        
        $stmt = $pdo->prepare("UPDATE requests SET status = ?, repair_date = ?, repair_time = ?, admin_comment = ? WHERE id = ?");
        $stmt->execute([$status, $repairDate, $repairTime, $comment, $id]);
        
        // Отправка email клиенту
        $stmt = $pdo->prepare("SELECT r.*, u.email, u.name FROM requests r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        
        if ($req) {
            $statusText = ['new' => 'Новая', 'in_progress' => 'Принята в работу', 'completed' => 'Выполнена', 'cancelled' => 'Отменена'];
            $subject = "Статус заявки #{$id} изменён - ГЕРОН-АВТО";
            $body = "
            <div style='max-width:600px;margin:0 auto;font-family:Arial,sans-serif'>
                <div style='background:#1a2b4c;color:#fff;padding:20px;text-align:center'><h2>Статус заявки изменён</h2></div>
                <div style='padding:20px;background:#fff'>
                    <p><b>Заявка #{$id}</b></p>
                    <p><b>Услуга:</b> {$req['service_type']}</p>
                    <p><b>Статус:</b> <span style='color:#ff6b35;font-weight:700'>{$statusText[$status]}</span></p>
                    " . ($repairDate ? "<p><b>Дата ремонта:</b> {$repairDate}</p>" : "") . "
                    " . ($repairTime ? "<p><b>Время:</b> {$repairTime}</p>" : "") . "
                    " . ($comment ? "<p><b>Комментарий:</b> {$comment}</p>" : "") . "
                </div>
            </div>";
            sendEmail($req['email'], $subject, $body);
        }
        
        header('Location: requests.php?updated=1');
        exit;
    }
    
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM requests WHERE id = ?")->execute([$id]);
        header('Location: requests.php?deleted=1');
        exit;
    }
}

// Фильтрация
$sql = "SELECT r.*, u.name as user_name, u.email as user_email FROM requests r JOIN users u ON r.user_id = u.id WHERE 1=1";
$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $statusFilter;
}
if ($dateFilter) {
    $sql .= " AND (r.preferred_date = ? OR r.repair_date = ?)";
    $params[] = $dateFilter;
    $params[] = $dateFilter;
}

$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

// Даты с заявками для календаря
$datesStmt = $pdo->query("SELECT DISTINCT preferred_date FROM requests WHERE status != 'cancelled' UNION SELECT DISTINCT repair_date FROM requests WHERE repair_date IS NOT NULL");
$busyDates = $datesStmt->fetchAll(PDO::FETCH_COLUMN);

// Заявки на выбранную дату (для календаря)
$dayRequests = [];
if ($dateFilter) {
    $stmt = $pdo->prepare("SELECT r.*, u.name FROM requests r JOIN users u ON r.user_id = u.id WHERE (r.preferred_date = ? OR r.repair_date = ?) AND r.status != 'cancelled' ORDER BY r.repair_time");
    $stmt->execute([$dateFilter, $dateFilter]);
    $dayRequests = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки на ремонт | Админ-панель</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
    <style>
        .calendar-section { display: grid; grid-template-columns: 350px 1fr; gap: 25px; margin-bottom: 30px; }
        @media (max-width: 900px) { .calendar-section { grid-template-columns: 1fr; } }
        .day-request-card { padding: 10px; margin-bottom: 8px; background: #f8f9fa; border-radius: 6px; border-left: 3px solid var(--accent); font-size: 13px; }
        .day-request-card .time { font-weight: 700; color: var(--accent); }
        .filter-bar { display: flex; gap: 15px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-bar select, .filter-bar input { padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px; }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-logo"><i class="fas fa-truck-moving"></i><span>ГЕРОН-АВТО</span></div>
        <nav class="sidebar-nav">
            <a href="index.php"><i class="fas fa-home"></i> Главная</a>
            <a href="requests.php" class="active"><i class="fas fa-tools"></i> Заявки на ремонт</a>
            <a href="resumes.php"><i class="fas fa-briefcase"></i> Заявки на вакансии</a>
            <a href="employees.php"><i class="fas fa-users"></i> Сотрудники</a>
            <a href="vacancies.php"><i class="fas fa-list-ul"></i> Вакансии</a>
            <a href="reviews.php"><i class="fas fa-star"></i> Отзывы</a>
            <a href="profile.php"><i class="fas fa-user-cog"></i> Профиль</a>
            <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Выйти</a>
        </nav>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>Заявки на ремонт</h1></div>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Статус заявки обновлён, клиент уведомлён по email</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Заявка удалена</div>
        <?php endif; ?>

        <div class="calendar-section">
            <div class="calendar">
                <div class="calendar-nav">
                    <button class="btn btn-small" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <h3 id="calendarMonth"></h3>
                    <button class="btn btn-small" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="calendar-grid" id="calendarGrid"></div>
            </div>
            <div class="card">
                <h3><?= $dateFilter ? 'Заявки на ' . date('d.m.Y', strtotime($dateFilter)) : 'Выберите дату' ?></h3>
                <?php if ($dayRequests): ?>
                    <?php foreach ($dayRequests as $dr): ?>
                        <div class="day-request-card">
                            <span class="time"><?= $dr['repair_time'] ? $dr['repair_time'] : '--:--' ?></span> — 
                            #<?= $dr['id'] ?> <?= htmlspecialchars($dr['service_type']) ?> (<?= htmlspecialchars($dr['name']) ?>)
                            <span class="status status-<?= $dr['status'] ?>"><?= $dr['status'] ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($dateFilter): ?>
                    <p style="color:#999">Нет заявок на эту дату</p>
                <?php else: ?>
                    <p style="color:#999">Выберите дату в календаре</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="filter-bar">
            <form style="display:flex;gap:15px;flex-wrap:wrap;align-items:center">
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Все статусы</option>
                    <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>Новые</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>В работе</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Выполнены</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Отменены</option>
                </select>
                <input type="date" name="date" value="<?= $dateFilter ?>" onchange="this.form.submit()">
                <?php if ($statusFilter !== 'all' || $dateFilter): ?>
                    <a href="requests.php" class="btn btn-small">Сбросить</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th><th>Клиент</th><th>Услуга</th><th>Авто</th><th>Дата</th><th>Время</th><th>Статус</th><th>Действия</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td>#<?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['user_name']) ?><br><small><?= $r['user_email'] ?></small></td>
                        <td><?= htmlspecialchars($r['service_type']) ?></td>
                        <td><?= htmlspecialchars($r['vehicle_model']) ?></td>
                        <td><?= $r['repair_date'] ?: $r['preferred_date'] ?></td>
                        <td><?= $r['repair_time'] ?: '--:--' ?></td>
                        <td><span class="status status-<?= $r['status'] ?>"><?= $r['status'] ?></span></td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-small" onclick="openEditModal(<?= htmlspecialchars(json_encode($r)) ?>)"><i class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Удалить заявку?')">
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

<!-- Модалка редактирования -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header">
            <h3>Редактировать заявку</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="editId">
                <div class="form-group">
                    <label>Статус</label>
                    <select name="status" id="editStatus">
                        <option value="new">Новая</option>
                        <option value="in_progress">В работе</option>
                        <option value="completed">Выполнена</option>
                        <option value="cancelled">Отменена</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Дата ремонта</label><input type="date" name="repair_date" id="editDate"></div>
                    <div class="form-group"><label>Время</label><input type="time" name="repair_time" id="editTime"></div>
                </div>
                <div class="form-group"><label>Комментарий</label><textarea name="admin_comment" id="editComment" rows="3"></textarea></div>
                <button type="submit" class="btn btn-primary btn-block">Сохранить</button>
            </form>
        </div>
    </div>
</div>

<script>
const busyDates = <?= json_encode($busyDates) ?>;
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();
const selectedDate = '<?= $dateFilter ?>';

function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    const title = document.getElementById('calendarMonth');
    const months = ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
    title.textContent = months[currentMonth] + ' ' + currentYear;
    
    const days = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'];
    let html = days.map(d => `<div class="calendar-day-header">${d}</div>`).join('');
    
    const firstDay = new Date(currentYear, currentMonth, 1).getDay() || 7;
    const lastDate = new Date(currentYear, currentMonth + 1, 0).getDate();
    const today = new Date().toISOString().split('T')[0];
    
    for (let i = 1; i < firstDay; i++) html += '<div class="calendar-day"></div>';
    for (let d = 1; d <= lastDate; d++) {
        const dateStr = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const hasReq = busyDates.includes(dateStr);
        const isToday = dateStr === today;
        const isSel = dateStr === selectedDate;
        html += `<div class="calendar-day${isToday?' today':''}${isSel?' selected':''}${hasReq?' has-requests':''}" 
            onclick="window.location='?date=${dateStr}&status=<?= $statusFilter ?>'">
            ${d}${hasReq?'<div class="dot"></div>':''}</div>`;
    }
    grid.innerHTML = html;
}

function changeMonth(d) {
    currentMonth += d;
    if (currentMonth < 0) { currentMonth = 11; currentYear--; }
    if (currentMonth > 11) { currentMonth = 0; currentYear++; }
    renderCalendar();
}

function openEditModal(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editStatus').value = data.status;
    document.getElementById('editDate').value = data.repair_date || '';
    document.getElementById('editTime').value = data.repair_time || '';
    document.getElementById('editComment').value = data.admin_comment || '';
    document.getElementById('editModal').classList.add('active');
}

function closeModal() {
    document.getElementById('editModal').classList.remove('active');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

renderCalendar();
</script>
</body>
</html>