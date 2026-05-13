<?php
require_once 'config.php';
requireAdmin();
$pdo = getDB();

$statusFilter = $_GET['status'] ?? 'all';
$dateFilter = $_GET['date'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $id = (int)$_POST['id'];
    $action = $_POST['action'];
    
    if ($action === 'update_status') {
        $status = $_POST['status'];
        $repairDate = $_POST['repair_date'] ?: null;
        $repairTime = $_POST['repair_time'] ?: null;
        $comment = $_POST['admin_comment'] ?? '';
        $bayNumber = (int)($_POST['bay_number'] ?? 0);
        
        $stmt = $pdo->prepare("UPDATE requests SET status = ?, repair_date = ?, repair_time = ?, admin_comment = ? WHERE id = ?");
        $stmt->execute([$status, $repairDate, $repairTime, $comment, $id]);
        
        if ($bayNumber > 0 && $repairDate && $repairTime) {
            $pdo->prepare("DELETE FROM service_bays WHERE request_id = ?")->execute([$id]);
            $pdo->prepare("INSERT INTO service_bays (bay_number, request_id, repair_date, repair_time) VALUES (?, ?, ?, ?)")
                ->execute([$bayNumber, $id, $repairDate, $repairTime]);
        }
        
        if ($status === 'completed' || $status === 'cancelled') {
            $pdo->prepare("DELETE FROM service_bays WHERE request_id = ?")->execute([$id]);
        }
        
        $stmt = $pdo->prepare("SELECT r.*, u.email, u.name FROM requests r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        
        if ($req) {
            $statusText = ['new' => 'Новая', 'in_progress' => 'Принята в работу', 'completed' => 'Выполнена', 'cancelled' => 'Отменена'];
            $subject = "Статус заявки #{$id} изменён - ГЕРОН-АВТО";
            $body = "<div style='max-width:600px;margin:0 auto;font-family:Arial,sans-serif'><div style='background:#1a2b4c;color:#fff;padding:20px;text-align:center'><h2>Статус заявки изменён</h2></div><div style='padding:20px;background:#fff'><p><b>Заявка #{$id}</b></p><p><b>Услуга:</b> {$req['service_type']}</p><p><b>Автомобиль:</b> {$req['vehicle_model']}</p><p><b>Новый статус:</b> <span style='color:#ff6b35;font-weight:700'>{$statusText[$status]}</span></p>".($repairDate?"<p><b>Дата ремонта:</b> {$repairDate}</p>":"").($repairTime?"<p><b>Время:</b> {$repairTime}</p>":"").($bayNumber?"<p><b>Яма №{$bayNumber}</b></p>":"").($comment?"<p><b>Комментарий:</b><br>{$comment}</p>":"")."</div><div style='background:#1a2b4c;color:#8899aa;padding:15px;text-align:center;font-size:12px'>ГЕРОН-АВТО | г. Санкт-Петербург</div></div>";
            sendEmail($req['email'], $subject, $body);
        }
        
        header('Location: requests.php?date=' . $dateFilter . '&updated=1');
        exit;
    }
    
    if ($action === 'delete') {
        $pdo->prepare("DELETE FROM service_bays WHERE request_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM requests WHERE id = ?")->execute([$id]);
        header('Location: requests.php?date=' . $dateFilter . '&deleted=1');
        exit;
    }
    
    if ($action === 'free_slot') {
        $slotId = (int)$_POST['slot_id'];
        $pdo->prepare("DELETE FROM service_bays WHERE id = ?")->execute([$slotId]);
        header('Location: requests.php?date=' . $dateFilter . '&freed=1');
        exit;
    }
}

$sql = "SELECT r.*, u.name as user_name, u.email as user_email FROM requests r JOIN users u ON r.user_id = u.id WHERE 1=1";
$params = [];
if ($statusFilter !== 'all') { $sql .= " AND r.status = ?"; $params[] = $statusFilter; }
$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll();

$bays = [];
for ($i = 1; $i <= 4; $i++) {
    $stmt = $pdo->prepare("SELECT sb.*, r.vehicle_model, r.service_type, u.name as client_name, u.phone as client_phone FROM service_bays sb LEFT JOIN requests r ON sb.request_id = r.id LEFT JOIN users u ON r.user_id = u.id WHERE sb.bay_number = ? AND sb.repair_date = ? ORDER BY sb.repair_time");
    $stmt->execute([$i, $dateFilter]);
    $bays[$i] = $stmt->fetchAll();
}

$datesStmt = $pdo->query("SELECT DISTINCT preferred_date FROM requests WHERE status != 'cancelled' UNION SELECT DISTINCT repair_date FROM requests WHERE repair_date IS NOT NULL");
$busyDates = $datesStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заявки на ремонт | Админ-панель</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="admin.css">
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
        <div class="top-bar"><h1>Управление заявками на ремонт</h1></div>
        
        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">Статус заявки обновлён. Клиент уведомлён по email.</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">Заявка удалена.</div>
        <?php endif; ?>
        <?php if (isset($_GET['freed'])): ?>
            <div class="alert alert-success">Слот в яме освобождён.</div>
        <?php endif; ?>

<div class="calendar">
    <div class="calendar-nav">
        <button class="btn btn-small" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
        <h3 id="calendarMonth"></h3>
        <button class="btn btn-small" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
    </div>
    <div class="calendar-grid" id="calendarGrid"></div>
</div>

        <h3 class="bays-title">Состояние ям — <?= date('d.m.Y', strtotime($dateFilter)) ?></h3>
        <div class="bays-container">
            <?php for ($i = 1; $i <= 4; $i++): ?>
            <div class="bay-col">
                <div class="bay-header">Яма №<?= $i ?></div>
                <div class="bay-slots">
                    <?php if (empty($bays[$i])): ?>
                        <div class="bay-empty">Свободна весь день</div>
                    <?php else: ?>
                        <?php foreach ($bays[$i] as $slot): ?>
                        <div class="bay-slot">
                            <span class="slot-time"><?= $slot['repair_time'] ?></span>
                            <span class="slot-id">#<?= $slot['request_id'] ?></span>
                            <div class="slot-car"><?= htmlspecialchars($slot['vehicle_model'] ?: $slot['service_type']) ?></div>
                            <div class="slot-client"><?= htmlspecialchars($slot['client_name'] ?: '—') ?> | <?= htmlspecialchars($slot['client_phone'] ?: '—') ?></div>
                            <form method="POST" class="slot-free-form" onsubmit="return confirm('Убрать с ямы?')">
                                <input type="hidden" name="action" value="free_slot">
                                <input type="hidden" name="slot_id" value="<?= $slot['id'] ?>">
                                <button class="btn btn-danger btn-xs"><i class="fas fa-times"></i></button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endfor; ?>
        </div>

        <div class="filter-bar">
            <form class="filter-form">
                <select name="status" onchange="this.form.submit()">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Все заявки</option>
                    <option value="new" <?= $statusFilter === 'new' ? 'selected' : '' ?>>Новые</option>
                    <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>В работе</option>
                    <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Выполнены</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Отменены</option>
                </select>
                <input type="hidden" name="date" value="<?= $dateFilter ?>">
                <?php if ($statusFilter !== 'all'): ?>
                    <a href="requests.php?date=<?= $dateFilter ?>" class="btn btn-small">Сбросить</a>
                <?php endif; ?>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr><th>ID</th><th>Клиент</th><th>Услуга</th><th>Автомобиль</th><th>Дата</th><th>Время</th><th>Статус</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                    <tr>
                        <td>#<?= $r['id'] ?></td>
                        <td><?= htmlspecialchars($r['user_name']) ?><br><small><?= htmlspecialchars($r['user_email']) ?></small></td>
                        <td><?= htmlspecialchars($r['service_type']) ?></td>
                        <td><?= htmlspecialchars($r['vehicle_model']) ?></td>
                        <td><?= $r['repair_date'] ? date('d.m.Y', strtotime($r['repair_date'])) : ($r['preferred_date'] ? date('d.m.Y', strtotime($r['preferred_date'])) : '—') ?></td>
                        <td><?= $r['repair_time'] ?: '—' ?></td>
                        <td><span class="status status-<?= $r['status'] ?>"><?= ['new'=>'Новая','in_progress'=>'В работе','completed'=>'Выполнена','cancelled'=>'Отменена'][$r['status']] ?? $r['status'] ?></span></td>
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

<div class="modal-overlay" id="editModal">
    <div class="modal">
        <div class="modal-header"><h3>Редактировать заявку</h3><button class="modal-close" onclick="closeModal()">&times;</button></div>
        <div class="modal-body">
            <form method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id" id="editId">
                <div class="form-group"><label>Статус</label><select name="status" id="editStatus"><option value="new">Новая</option><option value="in_progress">В работе</option><option value="completed">Выполнена</option><option value="cancelled">Отменена</option></select></div>
                <div class="form-row">
                    <div class="form-group"><label>Дата ремонта</label><input type="date" name="repair_date" id="editDate" value="<?= $dateFilter ?>"></div>
                    <div class="form-group"><label>Время</label><select name="repair_time" id="editTime"><option value="">Выберите</option><?php for($h=8;$h<=20;$h++){foreach(['00','30'] as $m){$t="$h:$m";echo"<option value='$t'>$t</option>";}} ?></select></div>
                </div>
                <div class="form-group"><label>Яма</label><select name="bay_number" id="editBay"><option value="">Не назначать</option><option value="1">Яма №1</option><option value="2">Яма №2</option><option value="3">Яма №3</option><option value="4">Яма №4</option></select></div>
                <div class="form-group"><label>Комментарий</label><textarea name="admin_comment" id="editComment" rows="3"></textarea></div>
                <button type="submit" class="btn btn-primary btn-block">Сохранить</button>
            </form>
        </div>
    </div>
</div>

<script>
const busyDates = <?= json_encode($busyDates) ?>;
let currentMonth = new Date().getMonth(), currentYear = new Date().getFullYear();
const selectedDate = '<?= $dateFilter ?>', statusFilter = '<?= $statusFilter ?>';

function renderCalendar() {
    const grid = document.getElementById('calendarGrid'), title = document.getElementById('calendarMonth');
    const months = ['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
    title.textContent = months[currentMonth] + ' ' + currentYear;
    let html = ['Пн','Вт','Ср','Чт','Пт','Сб','Вс'].map(d => `<div class="calendar-day-header">${d}</div>`).join('');
    const firstDay = (new Date(currentYear, currentMonth, 1).getDay() || 7), lastDate = new Date(currentYear, currentMonth + 1, 0).getDate();
    const today = new Date().toISOString().split('T')[0];
    for (let i = 1; i < firstDay; i++) html += '<div class="calendar-day"></div>';
    for (let d = 1; d <= lastDate; d++) {
        const ds = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        html += `<div class="calendar-day${ds===today?' today':''}${ds===selectedDate?' selected':''}${busyDates.includes(ds)?' has-requests':''}" onclick="window.location='?date=${ds}&status=${statusFilter}'">${d}${busyDates.includes(ds)?'<div class="dot"></div>':''}</div>`;
    }
    grid.innerHTML = html;
}

function changeMonth(d) { currentMonth += d; if (currentMonth < 0) { currentMonth = 11; currentYear--; } if (currentMonth > 11) { currentMonth = 0; currentYear++; } renderCalendar(); }
function openEditModal(data) {
    document.getElementById('editId').value = data.id;
    document.getElementById('editStatus').value = data.status;
    document.getElementById('editDate').value = data.repair_date || selectedDate;
    const ts = document.getElementById('editTime'); if (ts && data.repair_time) ts.value = data.repair_time;
    document.getElementById('editComment').value = data.admin_comment || '';
    document.getElementById('editModal').classList.add('active');
}
function closeModal() { document.getElementById('editModal').classList.remove('active'); }
document.getElementById('editModal').addEventListener('click', function(e) { if (e.target === this) closeModal(); });
renderCalendar();
</script>
</body>
</html>