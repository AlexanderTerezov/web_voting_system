<?php
session_start();

// Set timezone
date_default_timezone_set('Europe/Sofia');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$username = $_SESSION['user'];
$email = $_SESSION['email'];
$role = $_SESSION['role'];
$loginTime = $_SESSION['login_time'];
$roleLabel = $role === 'Admin' ? 'Администратор' : ($role === 'User' ? 'Потребител' : $role);

require_once __DIR__ . '/db.php';
$pdo = getDb();

// Load agencies and participants
$agencies = $pdo->query('SELECT * FROM agencies ORDER BY created_at DESC')->fetchAll();
$participantsRows = $pdo->query('SELECT agency_id, username, role FROM agency_participants')->fetchAll();
$participantsByAgency = [];
$rolesByAgencyUser = [];
foreach ($participantsRows as $row) {
    $agencyId = (int)$row['agency_id'];
    if (!isset($participantsByAgency[$agencyId])) {
        $participantsByAgency[$agencyId] = [];
    }
    $participantsByAgency[$agencyId][] = [
        'username' => $row['username'],
        'role' => $row['role']
    ];
    if (!isset($rolesByAgencyUser[$agencyId])) {
        $rolesByAgencyUser[$agencyId] = [];
    }
    $rolesByAgencyUser[$agencyId][$row['username']] = $row['role'];
}
foreach ($agencies as &$agency) {
    $agencyId = (int)$agency['id'];
    $agency['participants'] = $participantsByAgency[$agencyId] ?? [];
}
unset($agency);

function formatRolesLabel($roles): string
{
    $labels = [];
    foreach (normalizeRoles($roles) as $role) {
        $labels[] = $role === 'secretary' ? 'Секретар' : 'Член';
    }
    return implode(', ', $labels);
}

function formatQuorumLabel(array $agency): string
{
    $quorumType = $agency['quorum_type'] ?? 'count';
    $quorumType = $quorumType === 'percent' ? 'percent' : 'count';
    if ($quorumType === 'percent') {
        $percent = isset($agency['quorum_percent']) ? (int)$agency['quorum_percent'] : 0;
        $total = isset($agency['participants']) && is_array($agency['participants']) ? count($agency['participants']) : 0;
        $required = $percent > 0 ? (int)ceil(($total * $percent) / 100) : 0;
        return $percent > 0 ? sprintf('%d%% (%d/%d)', $percent, $required, $total) : '0%';
    }

    return isset($agency['quorum']) ? (string)$agency['quorum'] : '0';
}

// Load meetings with agency name
$meetings = $pdo->query(
    'SELECT m.*, a.name AS agency_name
     FROM meetings m
     JOIN agencies a ON a.id = m.agency_id'
)->fetchAll();

// Filter agencies for normal users and check if they are secretary
$userAgencies = [];
$isSecretaryInAny = false;
if ($role !== 'Admin') {
    foreach ($agencies as $agency) {
        $agencyId = (int)$agency['id'];
        if (isset($rolesByAgencyUser[$agencyId][$username])) {
            $userRoles = normalizeRoles($rolesByAgencyUser[$agencyId][$username]);
            $userAgencies[] = [
                'agency' => $agency,
                'roles' => $userRoles,
                'id' => $agencyId
            ];
            if (in_array('secretary', $userRoles, true)) {
                $isSecretaryInAny = true;
            }
        }
    }
}

// Get upcoming and past meetings for user
$upcomingMeetings = [];
$pastMeetings = [];
$now = new DateTime();
$recurringMap = ['Once' => 'Еднократно', 'Daily' => 'Ежедневно', 'Weekly' => 'Седмично', 'Monthly' => 'Месечно'];

foreach ($meetings as $meeting) {
    $duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60; // Default 60 minutes
    $scheduledStart = new DateTime($meeting['date'] . ' ' . $meeting['time']);
    $meetingStart = $scheduledStart;
    if (!empty($meeting['started_at'])) {
        $meetingStart = new DateTime($meeting['started_at']);
    }
    $meetingEnd = clone $meetingStart;
    $meetingEnd->modify("+{$duration} minutes");
    if (!empty($meeting['ended_at'])) {
        $overrideEnd = new DateTime($meeting['ended_at']);
        if ($overrideEnd < $meetingEnd) {
            $meetingEnd = $overrideEnd;
        }
    }
    $scheduledEnd = clone $scheduledStart;
    $scheduledEnd->modify("+{$duration} minutes");
    if (!empty($meeting['ended_at'])) {
        $overrideEnd = new DateTime($meeting['ended_at']);
        if ($overrideEnd < $scheduledEnd) {
            $scheduledEnd = $overrideEnd;
        }
    }
    $meetingStarted = !empty($meeting['started_at']);
    $classificationEnd = $meetingStarted ? $meetingEnd : $scheduledEnd;

    // Check if user is part of this agency
    $agencyId = (int)$meeting['agency_id'];
    $isParticipant = $role === 'Admin' || isset($rolesByAgencyUser[$agencyId][$username]);
    
    if ($isParticipant) {
        if ($classificationEnd >= $now) {
            $upcomingMeetings[] = $meeting;
        } else {
            $pastMeetings[] = $meeting;
        }
    }
}

// Sort upcoming by date/time (ascending)
usort($upcomingMeetings, function($a, $b) {
    $dateA = new DateTime($a['date'] . ' ' . $a['time']);
    $dateB = new DateTime($b['date'] . ' ' . $b['time']);
    return $dateA <=> $dateB;
});

// Sort past by date/time (descending - most recent first)
usort($pastMeetings, function($a, $b) {
    $dateA = new DateTime($a['date'] . ' ' . $a['time']);
    $dateB = new DateTime($b['date'] . ' ' . $b['time']);
    return $dateB <=> $dateA;
});

// Get last 5 past meetings for quick view
$recentPastMeetings = array_slice($pastMeetings, 0, 5);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Табло</title>
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
            display: flex;
            flex-direction: column;
            gap: 18px;
        }
        .header, .admin-panel, .agencies-section, .meetings-section{
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }
        h1{
            font-size: 22px;
            line-height: 1.2;
            margin: 0 0 10px 0;
            letter-spacing: -0.01em;
        }
        h2{
            font-size: 18px;
            margin: 0 0 12px 0;
        }
        h3{
            margin: 0 0 8px 0;
            font-size: 16px;
        }
        .user-info{
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 10px;
            margin-top: 12px;
        }
        .user-info p{ margin: 6px 0; color: var(--muted); }
        .user-role{
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
            background: #eef2ff;
            color: #1f2937;
        }
        .admin-role{ background: rgba(31,75,153,0.12); color: var(--accent); }
        .user-role-badge{ background: rgba(31,75,153,0.12); color: var(--accent); }

        .form-group{ margin-bottom: 14px; }
        .form-group label{
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-group input:not([type="checkbox"]), .form-group select{
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            background: #fff;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }
        .form-group input:not([type="checkbox"]):focus, .form-group select:focus{
            border-color: rgba(31,75,153,0.55);
            box-shadow: 0 0 0 4px rgba(31,75,153,0.12);
        }
        .participant-item{
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }
        .participant-item input[type="email"]{ flex: 1.2; }
        .participant-roles{
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1;
        }
        .participant-roles label{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--muted);
            padding: 6px 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            margin: 0;
        }
        .participant-roles input{ margin: 0; }
        .add-participant-btn, .remove-participant-btn{
            padding: 8px 12px;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            background: #111827;
            color: #fff;
        }
        .add-participant-btn{
            margin-bottom: 10px;
        }
        .remove-participant-btn{
            background: #fff;
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .submit-btn{
            width: 100%;
            padding: 11px 12px;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            background: #111827;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 80ms ease, opacity 120ms ease;
        }
        .submit-btn:hover{ opacity: 0.95; }
        .submit-btn:active{ transform: translateY(1px); }
        .logout-btn{
            padding: 8px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #fff;
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            font-weight: 600;
            transition: box-shadow 120ms ease;
        }
        .logout-btn:hover{ box-shadow: 0 4px 12px rgba(17,24,39,0.08); }
        .message{
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            margin: 14px 0;
            font-size: 14px;
        }
        .error{
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .success{
            background: var(--success-bg);
            color: var(--success-text);
            border-color: rgba(6,95,70,0.25);
        }
        .agency-card, .meeting-card{
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 16px;
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .agency-info, .meeting-info{
            font-size: 14px;
            color: var(--muted);
            margin: 4px 0;
        }
        .participant-list{
            margin-top: 10px;
            font-size: 14px;
        }
        .participant{
            display: inline-block;
            padding: 4px 10px;
            margin: 3px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 999px;
            font-size: 13px;
        }
        .participant.secretary{
            background: rgba(31,75,153,0.12);
            color: var(--accent);
            border-color: rgba(31,75,153,0.25);
        }
        .delete-agency-btn, .edit-agency-btn, .create-meeting-btn, .view-meeting-btn, .delete-meeting-btn{
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
        .delete-agency-btn, .delete-meeting-btn{
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .edit-agency-btn{
            background: #fff;
            color: var(--accent);
        }
        .create-meeting-btn{
            background: #111827;
            color: #fff;
        }
        .view-meeting-btn{
            background: var(--accent);
            color: #fff;
            border-color: var(--accent);
        }
        .delete-agency-btn:hover, .edit-agency-btn:hover, .create-meeting-btn:hover, .view-meeting-btn:hover, .delete-meeting-btn:hover{
            box-shadow: 0 4px 12px rgba(17,24,39,0.12);
        }
        .agency-actions{
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 12px;
        }
        .inline-form{
            display: inline;
        }
        
        /* Meeting sections styling */
        .meeting-header-wrapper{
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
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
        
        .empty-state{
            text-align: center;
            padding: 40px 20px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <h1>Табло</h1>
                <div style="display: flex; gap: 10px;">
                    <a href="all_meetings.php" class="logout-btn" style="background: var(--accent); color: #fff;">Всички заседания</a>
                    <a href="logout.php" class="logout-btn">Изход</a>
                </div>
            </div>
            <div class="user-info">
                <p><strong>Потребителско име:</strong> <?php echo htmlspecialchars($username); ?></p>
                <p><strong>Имейл:</strong> <?php echo htmlspecialchars($email); ?></p>
                <p><strong>Роля:</strong> <span class="user-role <?php echo $role === 'Admin' ? 'admin-role' : 'user-role-badge'; ?>"><?php echo htmlspecialchars($roleLabel); ?></span></p>
                <p><strong>Време на вход:</strong> <?php echo htmlspecialchars($loginTime); ?></p>
                <p><strong>Текущо време на сървъра:</strong> <?php echo date('Y-m-d H:i:s'); ?> (<?php echo date_default_timezone_get(); ?>)</p>
            </div>
        </div>

        <!-- Upcoming Meetings -->
        <?php if (!empty($upcomingMeetings)): ?>
        <div class="meetings-section">
            <div class="meeting-header-wrapper">
                <h2>Предстоящи заседания</h2>
            </div>
            <?php foreach ($upcomingMeetings as $meeting): ?>
                <?php
                // Check if user can delete this meeting
                $canDelete = false;
                if ($role === 'Admin') {
                    $canDelete = true;
                } else {
                    foreach ($agencies as $agency) {
                        if ($agency['name'] === $meeting['agency_name']) {
                            foreach ($agency['participants'] as $participant) {
                                if ($participant['username'] === $username && hasRole($participant['role'], 'secretary')) {
                                    $canDelete = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                // Calculate end time
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
                $meetingStarted = !empty($meeting['started_at']);
                $isActive = $meetingStarted && $now >= $meetingStart && $now <= $endTime;
                $recurringLabel = isset($meeting['recurring']) && isset($recurringMap[$meeting['recurring']]) ? $recurringMap[$meeting['recurring']] : ($meeting['recurring'] ?? '');
                ?>
                <div class="meeting-card">
                    <div class="meeting-name">
                        <?php echo htmlspecialchars($meeting['name'] ?? 'Заседание без име'); ?>
                        <?php if ($isActive): ?>
                            <span class="meeting-status status-active">Активно</span>
                        <?php else: ?>
                            <span class="meeting-status status-upcoming">Предстоящо</span>
                        <?php endif; ?>
                    </div>
                    <h3><?php echo htmlspecialchars($meeting['agency_name']); ?></h3>
                    <p class="meeting-info"><strong>Дата:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                    <p class="meeting-info"><strong>Час:</strong> <?php echo htmlspecialchars($meeting['time']); ?> - <?php echo $endTime->format('H:i'); ?> (<?php echo $duration; ?> минути)</p>
                    <p class="meeting-info"><strong>Повтаряемост:</strong> <?php echo htmlspecialchars($recurringLabel); ?></p>
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
        </div>
        <?php endif; ?>

        <!-- Recent Past Meetings -->
        <?php if (!empty($recentPastMeetings)): ?>
        <div class="meetings-section">
            <div class="meeting-header-wrapper">
                <h2>Скорошни минали заседания</h2>
            </div>
            <?php foreach ($recentPastMeetings as $meeting): ?>
                <?php
                // Check if user can delete this meeting
                $canDelete = false;
                if ($role === 'Admin') {
                    $canDelete = true;
                } else {
                    foreach ($agencies as $agency) {
                        if ($agency['name'] === $meeting['agency_name']) {
                            foreach ($agency['participants'] as $participant) {
                                if ($participant['username'] === $username && hasRole($participant['role'], 'secretary')) {
                                    $canDelete = true;
                                    break 2;
                                }
                            }
                        }
                    }
                }
                
                // Calculate end time
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
                $meetingStarted = !empty($meeting['started_at']);
                $isActive = $meetingStarted && $now >= $meetingStart && $now <= $endTime;
                $recurringLabel = isset($meeting['recurring']) && isset($recurringMap[$meeting['recurring']]) ? $recurringMap[$meeting['recurring']] : ($meeting['recurring'] ?? '');
                ?>
                <div class="meeting-card">
                    <div class="meeting-name">
                        <?php echo htmlspecialchars($meeting['name'] ?? 'Заседание без име'); ?>
                        <span class="meeting-status status-past">Минало</span>
                    </div>
                    <h3><?php echo htmlspecialchars($meeting['agency_name']); ?></h3>
                    <p class="meeting-info"><strong>Дата:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                    <p class="meeting-info"><strong>Час:</strong> <?php echo htmlspecialchars($meeting['time']); ?> - <?php echo $endTime->format('H:i'); ?> (<?php echo $duration; ?> минути)</p>
                    <p class="meeting-info"><strong>Повтаряемост:</strong> <?php echo htmlspecialchars($recurringLabel); ?></p>
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
        </div>
        <?php elseif (empty($upcomingMeetings)): ?>
        <div class="meetings-section">
            <h2>Заседания</h2>
            <div class="empty-state">
                <p>Все още няма насрочени заседания</p>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($role === 'Admin'): ?>
            <!-- Admin Panel: Create Agency -->
            <div class="admin-panel">
                <h2>Създай нов орган</h2>
                <?php if (isset($_GET['success'])): ?>
                    <div class="message success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
                
                <form action="create_agency.php" method="POST" id="agencyForm">
                    <div class="form-group">
                        <label for="agency_name">Име на орган</label>
                        <input type="text" id="agency_name" name="agency_name" required>
                    </div>
                    <div class="form-group">
                        <label for="default_questions">Автоматични точки за всяко заседание</label>
                        <textarea id="default_questions" 
                        id="default_questions" 
                        name="default_questions" 
                        rows="5" 
                        style="width: 100%; box-sizing: border-box; padding: 10px; border-radius: 10px; border: 1px solid #e5e7eb;"
                        placeholder="
* Откриване на заседанието
* Проверка на кворума
* Разни"></textarea>
                        
                    </div>
                    <small style="color: var(--muted);">Тези точки ще се добавят автоматично към всяко ново заседание на този орган.</small>
                    <div class="form-group">
                        <label for="quorum_type">Кворум</label>
                        <select id="quorum_type" name="quorum_type">
                            <option value="count">Брой присъстващи</option>
                            <option value="percent">Процент от всички</option>
                        </select>
                    </div>
                    <div class="form-group" id="quorum_count_group">
                        <label for="quorum_count">Минимален брой</label>
                        <input type="number" id="quorum_count" name="quorum_count" min="1" step="1" inputmode="numeric" pattern="[0-9]*" required>
                    </div>
                    <div class="form-group" id="quorum_percent_group" style="display: none;">
                        <label for="quorum_percent">Процент от всички участници</label>
                        <input type="number" id="quorum_percent" name="quorum_percent" min="1" max="100" step="1" inputmode="numeric" pattern="[0-9]*">
                    </div>
                    
                    <div class="form-group">
                        <label for="participants_bulk">Участници</label>
                        <textarea
                            id="participants_bulk"
                            name="participants_bulk"
                            rows="6"
                            required
                            style="width: 100%; box-sizing: border-box; padding: 10px; border-radius: 10px; border: 1px solid #e5e7eb;"
                            placeholder="example1@email.com, U, S&#10;example2@email.com, _, S&#10;example3@email.com, _, _"></textarea>
                        <small style="color: var(--muted); display: block; margin-top: 6px;">
                            Формат: един потребител на ред. U = Член, S = Секретар. Използвайте "_" за няма роля.
                        </small>
                    </div>
                    
                    <button type="submit" class="submit-btn">Създай орган</button>
                </form>
            </div>

            <!-- Admin View: All Agencies -->
            <div class="agencies-section">
                <h2>Всички органи</h2>
                <?php if (empty($agencies)): ?>
                    <p style="color: #999; text-align: center;">Все още няма създадени органи.</p>
                <?php else: ?>
                    <?php foreach ($agencies as $agency): ?>
                        <?php $agencyId = (int)$agency['id']; ?>
                        <div class="agency-card">
                            <h3><?php echo htmlspecialchars($agency['name']); ?></h3>
                            <p class="agency-info"><strong>Кворум:</strong> <?php echo htmlspecialchars(formatQuorumLabel($agency)); ?></p>
                            <p class="agency-info"><strong>Общо участници:</strong> <?php echo count($agency['participants']); ?></p>
                            <p class="agency-info"><strong>Създаден:</strong> <?php echo htmlspecialchars($agency['created_at']); ?></p>
                            
                            <div class="participant-list">
                                <strong>Участници:</strong><br>
                                <?php 
                                $adminIsSecretary = false;
                                foreach ($agency['participants'] as $participant): 
                                    if ($participant['username'] === $_SESSION['user'] && hasRole($participant['role'], 'secretary')) {
                                        $adminIsSecretary = true;
                                    }
                                ?>
                                    <span class="participant <?php echo hasRole($participant['role'], 'secretary') ? 'secretary' : ''; ?>">
                                        <?php echo htmlspecialchars($participant['username']); ?>
                                        <?php echo '(' . htmlspecialchars(formatRolesLabel($participant['role'])) . ')'; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>

                            <div class="agency-actions">
                                <form action="delete_agency.php" method="POST" class="inline-form">
                                    <input type="hidden" name="agency_id" value="<?php echo $agencyId; ?>">
                                    <button type="submit" class="delete-agency-btn" onclick="return confirm('Сигурни ли сте, че искате да изтриете този орган?')">Изтрий орган</button>
                                </form>

                                <a href="edit_agency.php?id=<?php echo $agencyId; ?>" class="edit-agency-btn">Редактирай орган</a>

                                <?php if ($adminIsSecretary): ?>
                                    <a href="create_meeting.php?agency_id=<?php echo $agencyId; ?>" class="create-meeting-btn">Създай заседание</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- User View: Their Agencies Only -->
            <div class="agencies-section">
                <h2>Моите органи</h2>
                <?php if (empty($userAgencies)): ?>
                    <p style="color: #999; text-align: center;">Все още не сте член на орган.</p>
                <?php else: ?>
                    <?php foreach ($userAgencies as $item): ?>
                        <?php $agency = $item['agency']; $userRoles = $item['roles']; $agencyId = $item['id']; ?>
                        <div class="agency-card">
                            <h3><?php echo htmlspecialchars($agency['name']); ?></h3>
                            <p class="agency-info"><strong>Вашата роля:</strong> 
                                <span class="participant <?php echo in_array('secretary', $userRoles, true) ? 'secretary' : ''; ?>">
                                    <?php echo htmlspecialchars(formatRolesLabel($userRoles)); ?>
                                </span>
                            </p>
                            <p class="agency-info"><strong>Кворум:</strong> <?php echo htmlspecialchars(formatQuorumLabel($agency)); ?></p>
                            <p class="agency-info"><strong>Общо участници:</strong> <?php echo count($agency['participants']); ?></p>
                            
                            <div class="participant-list">
                                <strong>Всички участници:</strong><br>
                                <?php foreach ($agency['participants'] as $participant): ?>
                                    <span class="participant <?php echo hasRole($participant['role'], 'secretary') ? 'secretary' : ''; ?>">
                                        <?php echo htmlspecialchars($participant['username']); ?>
                                        <?php echo '(' . htmlspecialchars(formatRolesLabel($participant['role'])) . ')'; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            
                            <?php if (in_array('secretary', $userRoles, true)): ?>
                                <div class="agency-actions">
                                    <a href="create_meeting.php?agency_id=<?php echo $agencyId; ?>" class="create-meeting-btn">Създай заседание</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Quorum form functions
        function toggleQuorumInputs() {
            const typeSelect = document.getElementById('quorum_type');
            if (!typeSelect) {
                return;
            }
            const isPercent = typeSelect.value === 'percent';
            const countGroup = document.getElementById('quorum_count_group');
            const percentGroup = document.getElementById('quorum_percent_group');
            const countInput = document.getElementById('quorum_count');
            const percentInput = document.getElementById('quorum_percent');
            if (countGroup) {
                countGroup.style.display = isPercent ? 'none' : 'block';
            }
            if (percentGroup) {
                percentGroup.style.display = isPercent ? 'block' : 'none';
            }
            if (countInput) {
                countInput.required = !isPercent;
                countInput.disabled = isPercent;
            }
            if (percentInput) {
                percentInput.required = isPercent;
                percentInput.disabled = !isPercent;
            }
        }

        const quorumTypeSelect = document.getElementById('quorum_type');
        if (quorumTypeSelect) {
            quorumTypeSelect.addEventListener('change', toggleQuorumInputs);
        }
        toggleQuorumInputs();
    </script>
</body>
</html>
