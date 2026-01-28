<?php
session_start();

date_default_timezone_set('Europe/Sofia');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$meeting_id = isset($_GET['id']) ? trim($_GET['id']) : '';

$meetings_file = '../db/meetings.json';
if (!file_exists($meetings_file)) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

$meetings = json_decode(file_get_contents($meetings_file), true);
$meeting = null;
foreach ($meetings as $m) {
    if (($m['id'] ?? '') === $meeting_id) {
        $meeting = $m;
        break;
    }
}

if (!$meeting) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

// Load agencies to verify access and list participants
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

$agency = null;
foreach ($agencies as $a) {
    if (($a['name'] ?? '') === ($meeting['agency_name'] ?? '')) {
        $agency = $a;
        break;
    }
}

$hasAccess = false;
if ($_SESSION['role'] === 'Admin') {
    $hasAccess = true;
} else {
    if ($agency && !empty($agency['participants'])) {
        foreach ($agency['participants'] as $participant) {
            if (($participant['username'] ?? '') === $_SESSION['user']) {
                $hasAccess = true;
                break;
            }
        }
    }
}

if (!$hasAccess) {
    header('Location: dashboard.php?error=Нямате достъп');
    exit();
}

$duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60;
$meetingStart = new DateTime(($meeting['date'] ?? '') . ' ' . ($meeting['time'] ?? '00:00'));
if (!empty($meeting['started_at'])) {
    $overrideStart = new DateTime($meeting['started_at']);
    if ($overrideStart < $meetingStart) {
        $meetingStart = $overrideStart;
    }
}
$meetingEnd = clone $meetingStart;
$meetingEnd->modify("+{$duration} minutes");
if (!empty($meeting['ended_at'])) {
    $overrideEnd = new DateTime($meeting['ended_at']);
    if ($overrideEnd < $meetingEnd) {
        $meetingEnd = $overrideEnd;
    }
}
$now = new DateTime();

if ($now <= $meetingEnd) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието още не е приключило');
    exit();
}

$participants = $agency['participants'] ?? [];
$quorum = isset($agency['quorum']) ? intval($agency['quorum']) : 0;
$participantUsernames = [];
foreach ($participants as $participant) {
    if (!empty($participant['username'])) {
        $participantUsernames[] = $participant['username'];
    }
}

$questions = isset($meeting['questions']) && is_array($meeting['questions']) ? $meeting['questions'] : [];
$attendance = [];
foreach ($questions as $question) {
    $votes = isset($question['votes']) && is_array($question['votes']) ? $question['votes'] : [];
    $isAssoc = array_keys($votes) !== range(0, count($votes) - 1);
    if ($isAssoc) {
        foreach ($votes as $user => $voteValue) {
            if ($user !== '') {
                $attendance[$user] = true;
            }
        }
    } else {
        foreach ($votes as $voteEntry) {
            if (is_array($voteEntry) && !empty($voteEntry['user'])) {
                $attendance[$voteEntry['user']] = true;
            }
        }
    }
}

$attendees = [];
$absent = [];
foreach ($participantUsernames as $username) {
    if (isset($attendance[$username])) {
        $attendees[] = $username;
    } else {
        $absent[] = $username;
    }
}
$attendanceCount = count($attendees);
$hasQuorum = $quorum > 0 ? $attendanceCount >= $quorum : true;

$autoPrint = isset($_GET['print']) && $_GET['print'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Протокол от заседание</title>
    <style>
        :root{
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --bg: #ffffff;
        }
        *{ box-sizing: border-box; }
        body{
            margin: 0;
            font-family: "Georgia", "Times New Roman", serif;
            background: var(--bg);
            color: var(--text);
            padding: 24px;
        }
        .page{
            max-width: 900px;
            margin: 0 auto;
        }
        h1{
            font-size: 24px;
            margin: 0 0 8px 0;
        }
        h2{
            font-size: 18px;
            margin: 24px 0 10px 0;
        }
        .meta{
            color: var(--muted);
            font-size: 13px;
        }
        .card{
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px;
            margin-top: 12px;
        }
        .row{
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }
        .row div{
            min-width: 200px;
        }
        .label{
            font-weight: 700;
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .value{
            margin-top: 4px;
            font-size: 15px;
        }
        table{
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td{
            border: 1px solid var(--border);
            padding: 8px;
            font-size: 13px;
            text-align: left;
            vertical-align: top;
        }
        th{
            background: #f9fafb;
        }
        .muted{
            color: var(--muted);
        }
        @media print {
            body{ padding: 0; }
            .page{ max-width: none; }
        }
    </style>
</head>
<body>
    <div class="page">
        <h1>Протокол от заседание</h1>
        <div class="meta">Генериран на <?php echo date('Y-m-d H:i'); ?></div>

        <div class="card">
            <div class="row">
                <div>
                    <div class="label">Заседание</div>
                    <div class="value"><?php echo htmlspecialchars($meeting['name'] ?? 'Заседание без име'); ?></div>
                </div>
                <div>
                    <div class="label">Орган</div>
                    <div class="value"><?php echo htmlspecialchars($meeting['agency_name'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Дата и час</div>
                    <div class="value"><?php echo htmlspecialchars($meeting['date'] ?? ''); ?> <?php echo htmlspecialchars($meeting['time'] ?? ''); ?></div>
                </div>
                <div>
                    <div class="label">Продължителност</div>
                    <div class="value"><?php echo $duration; ?> минути</div>
                </div>
            </div>
            <div style="margin-top: 12px;">
                <div class="label">Описание / Дневен ред</div>
                <div class="value"><?php echo !empty($meeting['reason']) ? htmlspecialchars($meeting['reason']) : '<span class="muted">Няма описание</span>'; ?></div>
            </div>
            <div style="margin-top: 12px;">
                <div class="label">Коментари / Протокол</div>
                <div class="value"><?php echo !empty($meeting['comments']) ? htmlspecialchars($meeting['comments']) : '<span class="muted">Няма записани коментари</span>'; ?></div>
            </div>
        </div>

        <h2>Участници</h2>
        <div class="card">
            <div class="row">
                <div>
                    <div class="label">Присъствали (гласували поне веднъж)</div>
                    <div class="value"><?php echo !empty($attendees) ? htmlspecialchars(implode(', ', $attendees)) : '—'; ?></div>
                </div>
                <div>
                    <div class="label">Отсъствали</div>
                    <div class="value"><?php echo !empty($absent) ? htmlspecialchars(implode(', ', $absent)) : '—'; ?></div>
                </div>
            </div>
            <div style="margin-top: 12px;">
                <div class="label">Кворум</div>
                <div class="value">
                    <?php if ($quorum > 0): ?>
                        Изискван: <?php echo $quorum; ?> · Присъствали: <?php echo $attendanceCount; ?>
                        <?php if (!$hasQuorum): ?>
                            <span class="muted"> · Няма кворум</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="muted">Не е зададен</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <h2>Дневен ред и гласуване</h2>
        <?php if (empty($questions)): ?>
            <div class="card"><span class="muted">Няма записани точки по дневния ред.</span></div>
        <?php else: ?>
            <?php foreach ($questions as $index => $question): ?>
                <?php
                $votes = isset($question['votes']) && is_array($question['votes']) ? $question['votes'] : [];
                $voteMap = [];
                $voteLabels = ['yes' => 'Да', 'no' => 'Не', 'abstain' => 'Въздържал се'];
                $isAssoc = array_keys($votes) !== range(0, count($votes) - 1);
                if ($isAssoc) {
                    foreach ($votes as $user => $voteValue) {
                        $voteMap[$user] = $voteValue;
                    }
                } else {
                    foreach ($votes as $voteEntry) {
                        if (is_array($voteEntry) && !empty($voteEntry['user'])) {
                            $voteMap[$voteEntry['user']] = $voteEntry['vote'] ?? '';
                        }
                    }
                }
                ?>
                <div class="card">
                    <div class="label">Точка <?php echo $index + 1; ?></div>
                    <div class="value"><?php echo htmlspecialchars($question['text'] ?? 'Без заглавие'); ?></div>
                    <?php if (!empty($question['details'])): ?>
                        <div class="value" style="margin-top: 6px;"><?php echo htmlspecialchars($question['details']); ?></div>
                    <?php endif; ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Участник</th>
                                <th>Вот</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($participantUsernames)): ?>
                                <?php foreach ($participantUsernames as $username): ?>
                                    <?php
                                    $rawVoteValue = $voteMap[$username] ?? null;
                                    $voteValue = $rawVoteValue !== null && isset($voteLabels[$rawVoteValue]) ? $voteLabels[$rawVoteValue] : 'няма вот';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($username); ?></td>
                                        <td><?php echo htmlspecialchars($voteValue); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="2" class="muted">Няма участници за този орган.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php if ($autoPrint): ?>
        <script>
            window.onload = function() {
                window.print();
            };
        </script>
    <?php endif; ?>
</body>
</html>
