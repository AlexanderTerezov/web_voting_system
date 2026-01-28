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

// Load agencies
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

// Load meetings
$meetings_file = '../db/meetings.json';
$meetings = [];
if (file_exists($meetings_file)) {
    $meetings = json_decode(file_get_contents($meetings_file), true);
}

// Get all meetings for user
$userMeetings = [];
$now = new DateTime();
$recurringMap = ['Once' => 'Еднократно', 'Daily' => 'Ежедневно', 'Weekly' => 'Седмично', 'Monthly' => 'Месечно'];

foreach ($meetings as $meeting) {
    // Calculate meeting end time based on duration
    $meetingStart = new DateTime($meeting['date'] . ' ' . $meeting['time']);
    if (!empty($meeting['started_at'])) {
        $overrideStart = new DateTime($meeting['started_at']);
        if ($overrideStart < $meetingStart) {
            $meetingStart = $overrideStart;
        }
    }
    $duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60; // Default 60 minutes
    $meetingEnd = clone $meetingStart;
    $meetingEnd->modify("+{$duration} minutes");
    if (!empty($meeting['ended_at'])) {
        $overrideEnd = new DateTime($meeting['ended_at']);
        if ($overrideEnd < $meetingEnd) {
            $meetingEnd = $overrideEnd;
        }
    }
    
    // Check if user is part of this agency
    $isParticipant = false;
    foreach ($agencies as $agency) {
        if ($agency['name'] === $meeting['agency_name']) {
            foreach ($agency['participants'] as $participant) {
                if ($participant['username'] === $username || $role === 'Admin') {
                    $isParticipant = true;
                    break 2;
                }
            }
        }
    }
    
    if ($isParticipant) {
        $meeting['end_time'] = $meetingEnd;
        $meeting['is_past'] = $meetingEnd < $now;
        $meeting['is_active'] = $now >= $meetingStart && $now <= $meetingEnd;
        $userMeetings[] = $meeting;
    }
}

// Sort all meetings by date/time (most recent first)
usort($userMeetings, function($a, $b) {
    $dateA = new DateTime($a['date'] . ' ' . $a['time']);
    $dateB = new DateTime($b['date'] . ' ' . $b['time']);
    return $dateB <=> $dateA;
});

// Pagination
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
$totalMeetings = count($userMeetings);
$totalPages = ceil($totalMeetings / $perPage);
$currentPage = max(1, min($currentPage, $totalPages));
$start = ($currentPage - 1) * $perPage;
$pageMeetings = array_slice($userMeetings, $start, $perPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Всички заседания</title>
    <style>
        :root{
            --bg: #1110;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
            --radius: 14px;
            --accent: #1f4b99;
            --danger-bg: #fef2f2;
            --danger-text: #991b1b;
            --success-bg: #ecfdf5;
            --success-text: #065f46;
        }

        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body{
            margin: 0;
            font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji", "Segoe UI Emoji";
            background: var(--bg);
            color: var(--text);
            padding: 24px;
        }
        .container{
            max-width: 1100px;
            margin: 0 auto;
        }
        .header, .meetings-section{
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
            margin-bottom: 18px;
        }
        h1{
            font-size: 22px;
            line-height: 1.2;
            margin: 0 0 10px 0;
            letter-spacing: -0.01em;
        }
        h3{
            margin: 0 0 8px 0;
            font-size: 16px;
        }
        .back-btn{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #fff;
            color: var(--accent);
            text-decoration: none;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-weight: 600;
            margin-bottom: 14px;
        }
        .back-btn:hover{ box-shadow: 0 4px 12px rgba(17,24,39,0.08); }
        .meeting-card{
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .meeting-info{
            font-size: 14px;
            color: var(--muted);
            margin: 4px 0;
        }
        .meeting-status{
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
        }
        .status-upcoming{
            background: #dbeafe;
            color: #1e40af;
        }
        .status-past{
            background: #f3f4f6;
            color: #6b7280;
        }
        .status-active{
            background: #dcfce7;
            color: #166534;
        }
        .meeting-name{
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 6px;
            color: var(--text);
        }
        .view-meeting-btn, .delete-meeting-btn{
            padding: 8px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 10px;
            margin-right: 8px;
            font-size: 14px;
            transition: box-shadow 120ms ease;
        }
        .view-meeting-btn{
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .delete-meeting-btn{
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .view-meeting-btn:hover, .delete-meeting-btn:hover{
            box-shadow: 0 4px 12px rgba(17,24,39,0.12);
        }
        .pagination-controls{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        .pagination-info{
            font-size: 14px;
            color: var(--muted);
        }
        .pagination-buttons{
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .page-btn{
            padding: 6px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            color: var(--text);
        }
        .page-btn:hover{
            background: #f8fafc;
        }
        .page-btn.disabled{
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        .per-page-select{
            padding: 6px 10px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            margin-left: 8px;
        }
        .empty-state{
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
        .filter-buttons{
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }
        .filter-btn{
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            color: var(--text);
        }
        .filter-btn.active{
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .filter-btn:hover{
            box-shadow: 0 2px 8px rgba(17,24,39,0.08);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Назад към таблото</a>
        
        <div class="header">
            <h1>Всички заседания</h1>
            <p style="color: var(--muted); margin: 8px 0 0 0;">
                Пълна история на заседанията, до които имате достъп
            </p>
        </div>

        <div class="meetings-section">
            <div class="filter-buttons">
                <a href="?filter=all&per_page=<?php echo $perPage; ?>" class="filter-btn <?php echo (!isset($_GET['filter']) || $_GET['filter'] === 'all') ? 'active' : ''; ?>">Всички</a>
                <a href="?filter=active&per_page=<?php echo $perPage; ?>" class="filter-btn <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'active') ? 'active' : ''; ?>">Активни</a>
                <a href="?filter=upcoming&per_page=<?php echo $perPage; ?>" class="filter-btn <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'upcoming') ? 'active' : ''; ?>">Предстоящи</a>
                <a href="?filter=past&per_page=<?php echo $perPage; ?>" class="filter-btn <?php echo (isset($_GET['filter']) && $_GET['filter'] === 'past') ? 'active' : ''; ?>">Минали</a>
            </div>

            <?php
            // Apply filter
            $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
            $filteredMeetings = $userMeetings;
            
            if ($filter === 'active') {
                $filteredMeetings = array_filter($userMeetings, function($m) { return !empty($m['is_active']); });
            } elseif ($filter === 'upcoming') {
                $filteredMeetings = array_filter($userMeetings, function($m) { return !$m['is_past'] && empty($m['is_active']); });
            } elseif ($filter === 'past') {
                $filteredMeetings = array_filter($userMeetings, function($m) { return $m['is_past']; });
            }
            
            $filteredMeetings = array_values($filteredMeetings);
            $totalFiltered = count($filteredMeetings);
            $totalPages = ceil($totalFiltered / $perPage);
            $currentPage = max(1, min($currentPage, max(1, $totalPages)));
            $start = ($currentPage - 1) * $perPage;
            $pageMeetings = array_slice($filteredMeetings, $start, $perPage);
            ?>

            <?php if (empty($pageMeetings)): ?>
                <div class="empty-state">
                    <p>Няма намерени заседания</p>
                </div>
            <?php else: ?>
                <?php foreach ($pageMeetings as $meeting): ?>
                    <?php
                    // Check if user can delete this meeting
                    $canDelete = false;
                    if ($role === 'Admin') {
                        $canDelete = true;
                    } else {
                        foreach ($agencies as $agency) {
                            if ($agency['name'] === $meeting['agency_name']) {
                                foreach ($agency['participants'] as $participant) {
                                    if ($participant['username'] === $username && $participant['role'] === 'secretary') {
                                        $canDelete = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                    }
                    
                    $duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60;
                    $meetingStart = new DateTime($meeting['date'] . ' ' . $meeting['time']);
                    if (!empty($meeting['started_at'])) {
                        $overrideStart = new DateTime($meeting['started_at']);
                        if ($overrideStart < $meetingStart) {
                            $meetingStart = $overrideStart;
                        }
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
