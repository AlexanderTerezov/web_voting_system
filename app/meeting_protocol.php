<?php
session_start();

date_default_timezone_set('Europe/Sofia');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$meeting_id = isset($_GET['id']) ? trim($_GET['id']) : '';

require_once __DIR__ . '/db.php';
$pdo = getDb();

$meetingStmt = $pdo->prepare(
    'SELECT m.*, a.name AS agency_name, a.quorum AS agency_quorum, a.quorum_type AS agency_quorum_type, a.quorum_percent AS agency_quorum_percent
     FROM meetings m
     JOIN agencies a ON a.id = m.agency_id
     WHERE m.id = :id'
);
$meetingStmt->execute([':id' => $meeting_id]);
$meeting = $meetingStmt->fetch();

if (!$meeting) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

$participantsStmt = $pdo->prepare('SELECT username, role FROM agency_participants WHERE agency_id = :id');
$participantsStmt->execute([':id' => $meeting['agency_id']]);
$participants = $participantsStmt->fetchAll();
$participantRoles = [];
foreach ($participants as $participant) {
    $participantRoles[$participant['username']] = $participant['role'];
}

$hasAccess = $_SESSION['role'] === 'Admin' || isset($participantRoles[$_SESSION['user']]);

if (!$hasAccess) {
    header('Location: dashboard.php?error=Нямате достъп');
    exit();
}

$duration = isset($meeting['duration']) ? intval($meeting['duration']) : 60;
$scheduledStart = new DateTime(($meeting['date'] ?? '') . ' ' . ($meeting['time'] ?? '00:00'));
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
$now = new DateTime();

if ($now <= $meetingEnd) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Заседанието още не е приключило');
    exit();
}

$quorum = isset($meeting['agency_quorum']) ? (int)$meeting['agency_quorum'] : 0;
$quorumType = $meeting['agency_quorum_type'] ?? 'count';
$quorumType = $quorumType === 'percent' ? 'percent' : 'count';
$quorumPercent = isset($meeting['agency_quorum_percent']) ? (int)$meeting['agency_quorum_percent'] : 0;
$participantUsernames = [];
foreach ($participants as $participant) {
    if (!empty($participant['username'])) {
        $participantUsernames[] = $participant['username'];
    }
}
$totalParticipants = count($participantUsernames);
$requiredQuorum = $quorum;
if ($quorumType === 'percent' && $quorumPercent > 0) {
    $requiredQuorum = (int)ceil(($totalParticipants * $quorumPercent) / 100);
}

$attendanceStmt = $pdo->prepare('SELECT username FROM meeting_attendance WHERE meeting_id = :meeting_id AND present = 1');
$attendanceStmt->execute([':meeting_id' => $meeting_id]);
$attendanceRows = $attendanceStmt->fetchAll(PDO::FETCH_COLUMN);
$attendanceSet = [];
foreach ($attendanceRows as $username) {
    if ($username !== null && $username !== '') {
        $attendanceSet[$username] = true;
    }
}

$questionsStmt = $pdo->prepare('SELECT * FROM questions WHERE meeting_id = :meeting_id ORDER BY sort_order ASC, created_at ASC');
$questionsStmt->execute([':meeting_id' => $meeting_id]);
$questions = $questionsStmt->fetchAll();

$votesStmt = $pdo->prepare(
    'SELECT v.question_id, v.username, v.vote, v.statement FROM votes v
     JOIN questions q ON q.id = v.question_id
     WHERE q.meeting_id = :meeting_id'
);
$votesStmt->execute([':meeting_id' => $meeting_id]);
$votesRows = $votesStmt->fetchAll();
$votesByQuestion = [];
$statementsByQuestion = [];
foreach ($votesRows as $voteRow) {
    $qid = $voteRow['question_id'];
    $username = $voteRow['username'];
    $votesByQuestion[$qid][$username] = $voteRow['vote'];
    $statementsByQuestion[$qid][$username] = $voteRow['statement'];
}
foreach ($questions as &$question) {
    $question['votes'] = $votesByQuestion[$question['id']] ?? [];
    $question['statements'] = $statementsByQuestion[$question['id']] ?? [];
}
unset($question);
$attendees = [];
$absent = [];
if (!empty($attendanceSet)) {
    foreach ($participantUsernames as $username) {
        if (isset($attendanceSet[$username])) {
            $attendees[] = $username;
        } else {
            $absent[] = $username;
        }
    }
} else {
    $attendance = [];
    foreach ($questions as $question) {
        $votes = isset($question['votes']) && is_array($question['votes']) ? $question['votes'] : [];
        foreach ($votes as $user => $voteValue) {
            if ($user !== '') {
                $attendance[$user] = true;
            }
        }
    }
    foreach ($participantUsernames as $username) {
        if (isset($attendance[$username])) {
            $attendees[] = $username;
        } else {
            $absent[] = $username;
        }
    }
}
$attendanceCount = count($attendees);
$hasQuorum = $requiredQuorum > 0 ? $attendanceCount >= $requiredQuorum : true;

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
                    <?php if ($requiredQuorum > 0): ?>
                        <?php if ($quorumType === 'percent' && $quorumPercent > 0): ?>
                            Изискван: <?php echo $quorumPercent; ?>% &middot; Присъствали: <?php echo $attendanceCount; ?> (минимум <?php echo $requiredQuorum; ?> от <?php echo $totalParticipants; ?>)
                        <?php else: ?>
                            Изискван: <?php echo $requiredQuorum; ?> &middot; Присъствали: <?php echo $attendanceCount; ?>
                        <?php endif; ?>
                        
                        <?php if (!$hasQuorum): ?>
                            <span class="muted"> Няма кворум</span>
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
                $statements = isset($question['statements']) && is_array($question['statements']) ? $question['statements'] : [];
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
                                <th>Изказване</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($participantUsernames)): ?>
                                <?php foreach ($participantUsernames as $username): ?>
                                    <?php
                                    $rawVoteValue = $voteMap[$username] ?? null;
                                    $voteValue = $rawVoteValue !== null && isset($voteLabels[$rawVoteValue]) ? $voteLabels[$rawVoteValue] : 'няма вот';
                                    $statementValue = $statements[$username] ?? '';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($username); ?></td>
                                        <td><?php echo htmlspecialchars($voteValue); ?></td>
                                        <td><?php echo htmlspecialchars($statementValue !== '' ? $statementValue : '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="muted">Няма участници за този орган.</td>
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
