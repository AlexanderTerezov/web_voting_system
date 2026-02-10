<?php
session_start();

// Set timezone (adjust to your timezone)
date_default_timezone_set('Europe/Sofia');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$username = $_SESSION['user'];
$role = $_SESSION['role'];

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/recurring_meetings.php';
$pdo = getDb();
ensureRecurringMeetings($pdo);

// Load agencies and participants
$agencies = $pdo->query('SELECT * FROM agencies ORDER BY created_at DESC')->fetchAll();
$participantsRows = $pdo->query('SELECT agency_id, username, role FROM agency_participants')->fetchAll();
$rolesByAgencyUser = [];
foreach ($participantsRows as $row) {
    $agencyId = (int)$row['agency_id'];
    if (!isset($rolesByAgencyUser[$agencyId])) {
        $rolesByAgencyUser[$agencyId] = [];
    }
    $rolesByAgencyUser[$agencyId][$row['username']] = $row['role'];
}

// Load meetings with agency name
$meetings = $pdo->query(
    'SELECT m.*, a.name AS agency_name
     FROM meetings m
     JOIN agencies a ON a.id = m.agency_id'
)->fetchAll();

// Get all meetings for user
$userMeetings = [];
$now = new DateTime();
$recurringMap = ['Once' => 'Еднократно', 'Daily' => 'Ежедневно', 'Weekly' => 'Седмично', 'Monthly' => 'Месечно'];

foreach ($meetings as $meeting) {
    $duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60;
$scheduledStart = new DateTime($meeting['date'] . ' ' . $meeting['time']);
$meetingStart = $scheduledStart;
if (!empty($meeting['started_at'])) {
    $meetingStart = new DateTime($meeting['started_at']);
}
$endTime = clone $meetingStart;
$endTime->modify("+{$duration} minutes");
if (!empty($meeting['ended_at'])) {
    $overrideEnd = new DateTime($meeting['ended_at']);
    if ($overrideEnd < $endTime) {
        $endTime = $overrideEnd;
    }
}
$recurringLabel = isset($meeting['recurring']) && isset($recurringMap[$meeting['recurring']]) ? $recurringMap[$meeting['recurring']] : ($meeting['recurring'] ?? '');
                    ?>
                    <div class="meeting-card">
                        <div class="meeting-name">
                            <?php echo htmlspecialchars($meeting['name'] ?? 'Заседание без име'); ?>
                            <?php if ($meeting['is_past']): ?>
                                <span class="meeting-status status-past">Минало</span>
                            <?php elseif (!empty($meeting['is_active'])): ?>
                                <span class="meeting-status status-active">Активно</span>
                            <?php else: ?>
                                <span class="meeting-status status-upcoming">Предстоящо</span>
                            <?php endif; ?>
                        </div>
                        <h3><?php echo htmlspecialchars($meeting['agency_name']); ?></h3>
                        <p class="meeting-info"><strong>Дата:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                        <p class="meeting-info"><strong>Час:</strong> <?php echo htmlspecialchars($meeting['time']); ?> - <?php echo $endTime->format('H:i'); ?> (<?php echo $duration; ?> минути)</p>
                        <p class="meeting-info"><strong>Повтаряемост:</strong> <?php echo htmlspecialchars($recurringLabel); ?></p>
                        <p class="meeting-info"><strong>Създадено от:</strong> <?php echo htmlspecialchars($meeting['created_by']); ?></p>
                        <div style="margin-top: 10px;">
                            <a href="view_meeting.php?id=<?php echo $meeting['id']; ?>" class="view-meeting-btn">Виж заседание</a>
                            <?php if ($canDelete): ?>
                                <form action="delete_meeting.php" method="POST" style="display: inline;">
                                    <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                    <button type="submit" class="delete-meeting-btn" onclick="return confirm('Сигурни ли сте, че искате да изтриете това заседание?')">Изтрий</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Pagination -->
                <div class="pagination-controls">
                    <div class="pagination-info">
                        Показва <?php echo $start + 1; ?>-<?php echo min($start + $perPage, $totalFiltered); ?> от <?php echo $totalFiltered; ?>
                        <label>
                            Записи на страница:
                            <select class="per-page-select" onchange="window.location.href='?page=1&per_page=' + this.value + '&filter=<?php echo $filter; ?>'">
                                <option value="5" <?php echo $perPage === 5 ? 'selected' : ''; ?>>5</option>
                                <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10</option>
                                <option value="20" <?php echo $perPage === 20 ? 'selected' : ''; ?>>20</option>
                                <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50</option>
                            </select>
                        </label>
                    </div>
                    <div class="pagination-buttons">
                        <a href="?page=<?php echo max(1, $currentPage - 1); ?>&per_page=<?php echo $perPage; ?>&filter=<?php echo $filter; ?>" 
                           class="page-btn <?php echo $currentPage === 1 ? 'disabled' : ''; ?>">Назад</a>
                        <span style="padding: 0 8px; color: var(--muted);">Страница <?php echo $currentPage; ?> от <?php echo max(1, $totalPages); ?></span>
                        <a href="?page=<?php echo min($totalPages, $currentPage + 1); ?>&per_page=<?php echo $perPage; ?>&filter=<?php echo $filter; ?>" 
                           class="page-btn <?php echo $currentPage === $totalPages || $totalPages === 0 ? 'disabled' : ''; ?>">Напред</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
