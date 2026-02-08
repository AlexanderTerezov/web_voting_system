<?php
session_start();

// Set timezone
date_default_timezone_set('Europe/Sofia');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$meeting_id = isset($_GET['id']) ? $_GET['id'] : '';

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/recurring_meetings.php';
$pdo = getDb();
ensureRecurringMeetings($pdo);

$meetingStmt = $pdo->prepare(
    'SELECT m.*, a.name AS agency_name, a.quorum AS agency_quorum
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

// Check if user has access to this meeting
$hasAccess = $_SESSION['role'] === 'Admin' || isset($participantRoles[$_SESSION['user']]);

if (!$hasAccess) {
    header('Location: dashboard.php?error=Нямате достъп');
    exit();
}

// Determine if user can manage questions (secretary or admin)
$canManageQuestions = $_SESSION['role'] === 'Admin' || hasRole($participantRoles[$_SESSION['user']] ?? '', 'secretary');

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
$meetingActive = $now >= $meetingStart && $now <= $meetingEnd;
$meetingStarted = $now >= $meetingStart;
$meetingEnded = $now > $meetingEnd;

$questionsStmt = $pdo->prepare('SELECT * FROM questions WHERE meeting_id = :meeting_id ORDER BY created_at ASC');
$questionsStmt->execute([':meeting_id' => $meeting_id]);
$questions = $questionsStmt->fetchAll();

$attachmentsStmt = $pdo->prepare(
    'SELECT a.* FROM attachments a
     JOIN questions q ON q.id = a.question_id
     WHERE q.meeting_id = :meeting_id'
);
$attachmentsStmt->execute([':meeting_id' => $meeting_id]);
$attachmentsRows = $attachmentsStmt->fetchAll();
$attachmentsByQuestion = [];
foreach ($attachmentsRows as $attachment) {
    $attachmentsByQuestion[$attachment['question_id']][] = $attachment;
}

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
    $qid = $question['id'];
    $question['attachments'] = $attachmentsByQuestion[$qid] ?? [];
    $question['votes'] = $votesByQuestion[$qid] ?? [];
    $question['statements'] = $statementsByQuestion[$qid] ?? [];
}
unset($question);

$questionNumbers = [];
foreach ($questions as $index => $question) {
    if (!empty($question['id'])) {
        $questionNumbers[$question['id']] = $index + 1;
    }
}

$currentQuestion = null;
$futureQuestions = [];
$pastQuestions = [];
foreach ($questions as $question) {
    $status = $question['status'] ?? 'future';
    if ($status === 'current' && $currentQuestion === null) {
        $currentQuestion = $question;
        continue;
    }
    if ($status === 'past') {
        $pastQuestions[] = $question;
        continue;
    }
    $futureQuestions[] = $question;
}

$voteLabels = ['yes' => 'Да', 'no' => 'Не', 'abstain' => 'Въздържал се'];

function summarizeVotes(array $votes, string $username): array
{
    $counts = ['yes' => 0, 'no' => 0, 'abstain' => 0];
    $userVote = null;
    foreach ($votes as $user => $voteValue) {
        if (isset($counts[$voteValue])) {
            $counts[$voteValue]++;
        }
        if ($user === $username) {
            $userVote = $voteValue;
        }
    }
    return [$counts, $userVote];
}

$recurringMap = ['Once' => 'Еднократно', 'Daily' => 'Ежедневно', 'Weekly' => 'Седмично', 'Monthly' => 'Месечно'];
$recurringLabel = isset($meeting['recurring']) && isset($recurringMap[$meeting['recurring']]) ? $recurringMap[$meeting['recurring']] : ($meeting['recurring'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заседание</title>
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
            max-width: 1000px;
            margin: 0 auto;
        }
        .meeting-header, .meeting-content{
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }
        .meeting-header{ margin-bottom: 18px; }
        .meeting-content{
            min-height: 260px;
            padding: 0;
            border: none;
            background: none;
            box-shadow: none;
        }
        h1{
            font-size: 20px;
            line-height: 1.2;
            margin: 0 0 10px 0;
            letter-spacing: -0.01em;
        }
        .meeting-info{
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 10px 12px;
            border-radius: 10px;
            margin-top: 12px;
            font-size: 14px;
            color: var(--muted);
        }
        .meeting-info p{
            margin: 6px 0;
            color: var(--text);
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
            margin-top: 12px;
            font-weight: 600;
        }
        .protocol-btn{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            background: #111827;
            color: #fff;
            text-decoration: none;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            margin-top: 12px;
            font-weight: 600;
        }
        .back-btn:hover{ text-decoration: none; box-shadow: 0 4px 12px rgba(17,24,39,0.08); }
        .empty-message{
            color: var(--muted);
            font-size: 15px;
        }
        .section{
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 20px;
            margin-bottom: 18px;
        }
        .status-banner{
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 14px;
            color: var(--text);
        }
        .status-pill{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .status-upcoming{ background: #dbeafe; color: #1e40af; }
        .status-active{ background: #dcfce7; color: #166534; }
        .status-ended{ background: #f3f4f6; color: #6b7280; }
        .status-meta{
            color: var(--muted);
            font-size: 13px;
            margin-top: 4px;
        }
        .message{
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .message.success{
            background: #ecfdf5;
            color: #065f46;
            border-color: rgba(6,95,70,0.2);
        }
        .message.error{
            background: #fef2f2;
            color: #991b1b;
            border-color: rgba(153,27,27,0.25);
        }
        .form-group{ margin-bottom: 12px; }
        .form-group label{
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-group input, .form-group textarea{
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            background: #fff;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }
        .form-group textarea{ min-height: 90px; resize: vertical; }
        .form-group input:focus, .form-group textarea:focus{
            border-color: rgba(31,75,153,0.55);
            box-shadow: 0 0 0 4px rgba(31,75,153,0.12);
        }
        .submit-btn{
            padding: 10px 14px;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            background: #111827;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .questions-list{
            display: grid;
            gap: 12px;
        }
        .question-card{
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            background: #fff;
        }
        .question-card.current{
            border-color: rgba(31,75,153,0.35);
            box-shadow: 0 12px 24px rgba(31,75,153,0.12);
        }
        .question-header{
            display: flex;
            flex-direction: column;
            gap: 6px;
            margin-bottom: 8px;
        }
        .question-status{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .question-status.current{ background: #dbeafe; color: #1e40af; }
        .question-status.future{ background: #fef3c7; color: #92400e; }
        .question-status.past{ background: #e5e7eb; color: #4b5563; }
        .question-title{
            font-weight: 700;
            font-size: 16px;
        }
        .question-meta{
            color: var(--muted);
            font-size: 12px;
        }
        .question-desc{
            color: var(--text);
            font-size: 14px;
            margin: 8px 0 0 0;
            white-space: pre-wrap;
        }
        .attachments{
            margin-top: 10px;
        }
        .attachment-grid{
            display: grid;
            gap: 10px;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        }
        .attachment-item{
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 8px;
            background: #f8fafc;
            font-size: 13px;
            color: var(--text);
        }
        .attachment-item img{
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 6px;
            display: block;
        }
        .vote-area{
            margin-top: 12px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .agenda-group{
            margin-top: 16px;
        }
        .agenda-title{
            margin: 0 0 10px 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        .vote-counts{
            font-size: 13px;
            color: var(--muted);
        }
        .vote-buttons{
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .vote-btn{
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
        }
        .vote-btn.small{
            padding: 4px 10px;
            font-size: 12px;
        }
        .vote-btn.yes{ border-color: rgba(22,101,52,0.4); color: #166534; }
        .vote-btn.no{ border-color: rgba(153,27,27,0.35); color: #991b1b; }
        .vote-btn.abstain{ border-color: rgba(107,114,128,0.5); color: #374151; }
        .vote-btn.active{
            background: #111827;
            color: #fff;
            border-color: #111827;
        }
        .vote-disabled{
            color: var(--muted);
            font-size: 13px;
        }
        .vote-table{
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .vote-table th, .vote-table td{
            border: 1px solid var(--border);
            padding: 8px;
            font-size: 13px;
            text-align: left;
        }
        .vote-table th{
            background: #f8fafc;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 11px;
        }
        .vote-actions{
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        .statement-input{
            width: 100%;
            min-height: 46px;
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 6px 8px;
            font-size: 12px;
            margin-bottom: 6px;
            resize: vertical;
        }
        .statement-list{
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed var(--border);
        }
        .statement-title{
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .statement-item{
            font-size: 13px;
            color: var(--text);
            margin-bottom: 6px;
            white-space: pre-wrap;
        }
        .statement-name{
            font-weight: 700;
            margin-right: 6px;
        }
        .question-actions{
            margin-top: 10px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .action-btn{
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
        }
        .action-btn.primary{
            background: #111827;
            color: #fff;
            border-color: #111827;
        }
        .action-btn.ghost{
            color: var(--accent);
        }
        .status-actions{
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .status-action-btn{
            padding: 6px 12px;
            border-radius: 999px;
            border: 1px solid var(--border);
            background: #fff;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
        }
        .status-action-btn.end{
            background: #fee2e2;
            color: #991b1b;
            border-color: rgba(153,27,27,0.25);
        }
        .status-action-btn.extend{
            background: #dbeafe;
            color: #1e40af;
            border-color: rgba(30,64,175,0.25);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="meeting-header">
            <h1><?php echo htmlspecialchars($meeting['name'] ?? 'Заседание без име'); ?></h1>
            <p style="font-size: 14px; color: var(--muted); margin: 8px 0 0 0;">
                <?php echo htmlspecialchars($meeting['agency_name']); ?>
            </p>
            <div class="meeting-info">
                <p><strong>Дата:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                <p><strong>Час:</strong> <?php echo htmlspecialchars($meeting['time']); ?> - <?php echo $meetingEnd->format('H:i'); ?> (<?php echo $duration; ?> минути)</p>
                <p><strong>Повтаряемост:</strong> <?php echo htmlspecialchars($recurringLabel); ?></p>
                <p><strong>Създадено от:</strong> <?php echo htmlspecialchars($meeting['created_by']); ?></p>
                <?php if (!empty($meeting['reason'])): ?>
                    <p><strong>Описание / Дневен ред:</strong> <?php echo htmlspecialchars($meeting['reason']); ?></p>
                <?php endif; ?>
            </div>
            <a href="dashboard.php" class="back-btn">← Назад към таблото</a>
            <?php if ($meetingEnded): ?>
                <a href="meeting_protocol.php?id=<?php echo urlencode($meeting['id']); ?>&print=1" target="_blank" rel="noopener noreferrer" class="protocol-btn">Генерирай PDF протокол</a>
            <?php endif; ?>
        </div>

        <div class="meeting-content">
            <?php if (isset($_GET['success'])): ?>
                <div class="message success"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            <?php if (isset($_GET['error'])): ?>
                <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>

            <div class="section">
                <div class="status-banner">
                    <div>
                        <strong>Статус:</strong>
                        <?php if ($meetingActive): ?>
                            <span class="status-pill status-active">Активно</span>
                        <?php elseif ($meetingStarted): ?>
                            <span class="status-pill status-ended">Приключило</span>
                        <?php else: ?>
                            <span class="status-pill status-upcoming">Предстоящо</span>
                        <?php endif; ?>
                        <div class="status-meta">
                            Начало: <?php echo $meetingStart->format('Y-m-d H:i'); ?> · Край: <?php echo $meetingEnd->format('Y-m-d H:i'); ?>
                        </div>
                    </div>
                    <div class="status-meta">
                        <?php if ($meetingActive): ?>
                            Гласуването е отворено.
                        <?php elseif ($meetingEnded): ?>
                            Заседанието приключи.
                        <?php else: ?>
                            Гласуването се отваря при начало на заседанието.
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($canManageQuestions): ?>
                    <div style="margin-top: 10px; display: flex; justify-content: flex-end;">
                        <div class="status-actions">
                            <?php if ($meetingActive): ?>
                                <form action="update_meeting_time.php" method="POST">
                                    <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                                    <input type="hidden" name="action_type" value="extend">
                                    <input type="hidden" name="extend_minutes" value="15">
                                    <button type="submit" class="status-action-btn extend">Удължи +15 мин</button>
                                </form>
                                <form action="update_meeting_time.php" method="POST">
                                    <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                                    <input type="hidden" name="action_type" value="end_early">
                                    <button type="submit" class="status-action-btn end" onclick="return confirm('Да приключим заседанието по-рано?')">Приключи сега</button>
                                </form>
                            <?php elseif ($meetingStarted && !$meetingEnded): ?>
                                <form action="update_meeting_time.php" method="POST">
                                    <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                                    <input type="hidden" name="action_type" value="extend">
                                    <input type="hidden" name="extend_minutes" value="15">
                                    <button type="submit" class="status-action-btn extend">Удължи +15 мин</button>
                                </form>
                            <?php elseif (!$meetingStarted): ?>
                                <form action="update_meeting_time.php" method="POST">
                                    <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                                    <input type="hidden" name="action_type" value="start_early">
                                    <button type="submit" class="status-action-btn extend" onclick="return confirm('Да започнем заседанието сега?')">Започни сега</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="section">
                <h2>Коментари / Протокол</h2>
                <?php if (!empty($meeting['comments'])): ?>
                    <p class="question-desc"><?php echo htmlspecialchars($meeting['comments']); ?></p>
                <?php else: ?>
                    <p class="empty-message">Все още няма добавени коментари.</p>
                <?php endif; ?>

                <?php if ($canManageQuestions && $meetingEnded): ?>
                    <form action="save_meeting_comments.php" method="POST" style="margin-top: 12px;">
                        <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                        <div class="form-group">
                            <label for="meeting_comments">Добави / Обнови коментари</label>
                            <textarea id="meeting_comments" name="meeting_comments" placeholder="Обобщение на дискусии, решения и последващи действия."><?php echo htmlspecialchars($meeting['comments'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="submit-btn">Запази коментарите</button>
                    </form>
                <?php elseif ($canManageQuestions && !$meetingEnded): ?>
                    <p class="status-meta">Коментарите могат да се добавят след края на заседанието.</p>
                <?php endif; ?>
            </div>

            <?php if ($canManageQuestions): ?>
                <div class="section">
                    <h2>Добави точка от дневния ред</h2>
                    <form action="add_question.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                        <div class="form-group">
                            <label for="question_text">Точка / Въпрос</label>
                            <input type="text" id="question_text" name="question_text" required placeholder="напр. Приемане на бюджет 2026">
                        </div>
                        <div class="form-group">
                            <label for="question_details">Детайли (по желание)</label>
                            <textarea id="question_details" name="question_details" placeholder="Добавете контекст или предложение."></textarea>
                        </div>
                        <div class="form-group">
                            <label for="attachments">Прикачени файлове (изображения, PDF, Office документи)</label>
                            <input type="file" id="attachments" name="attachments[]" multiple>
                        </div>
                        <button type="submit" class="submit-btn">Добави точка</button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="section">
                <h2>Дневен ред и гласуване</h2>
                <?php if (empty($questions)): ?>
                    <p class="empty-message">Няма добавени точки.</p>
                <?php else: ?>
                    <div class="agenda-group">
                        <div class="agenda-title">Сегашна точка</div>
                        <?php if ($currentQuestion): ?>
                            <?php
                            $question = $currentQuestion;
                            $votes = isset($question['votes']) && is_array($question['votes']) ? $question['votes'] : [];
                            $statements = isset($question['statements']) && is_array($question['statements']) ? $question['statements'] : [];
                            list($counts, $userVote) = summarizeVotes($votes, $_SESSION['user']);
                            $userVoteLabel = $userVote !== null && isset($voteLabels[$userVote]) ? $voteLabels[$userVote] : null;
                            $questionNumber = $questionNumbers[$question['id']] ?? '';
                            $statementItems = [];
                            foreach ($statements as $statementUser => $statementText) {
                                if (trim((string)$statementText) === '') {
                                    continue;
                                }
                                $statementItems[$statementUser] = $statementText;
                            }
                            ?>
                            <div class="question-card current">
                                <div class="question-header">
                                    <div class="question-title"><?php echo ($questionNumber !== '' ? $questionNumber . '. ' : '') . htmlspecialchars($question['text'] ?? 'Untitled question'); ?></div>
                                    <span class="question-status current">Сегашна</span>
                                    <div class="question-meta">
                                        Добавено от <?php echo htmlspecialchars($question['created_by'] ?? 'Непознат'); ?>
                                        <?php if (!empty($question['created_at'])): ?>
                                            &middot; <?php echo htmlspecialchars($question['created_at']); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if (!empty($question['details'])): ?>
                                    <p class="question-desc"><?php echo htmlspecialchars($question['details']); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($question['attachments']) && is_array($question['attachments'])): ?>
                                    <div class="attachments">
                                        <div class="attachment-grid">
                                            <?php foreach ($question['attachments'] as $attachment): ?>
                                                <?php
                                                $attachmentPath = $attachment['path'] ?? '';
                                                $attachmentUrl = $attachmentPath ? $attachmentPath : '#';
                                                $attachmentName = $attachment['original_name'] ?? basename($attachmentPath);
                                                $attachmentType = $attachment['type'] ?? '';
                                                $isImage = strpos($attachmentType, 'image/') === 0;
                                                ?>
                                                <div class="attachment-item">
                                                    <?php if ($isImage && $attachmentPath): ?>
                                                        <img src="<?php echo htmlspecialchars($attachmentUrl); ?>" alt="<?php echo htmlspecialchars($attachmentName); ?>">
                                                    <?php endif; ?>
                                                    <a href="<?php echo htmlspecialchars($attachmentUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                        <?php echo htmlspecialchars($attachmentName); ?>
                                                    </a>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="vote-area">
                                    <div class="vote-counts">
                                        Да: <?php echo $counts['yes']; ?> · Не: <?php echo $counts['no']; ?> · Въздържал се: <?php echo $counts['abstain']; ?>
                                        <?php if ($userVoteLabel): ?>
                                            · Вашият вот: <?php echo htmlspecialchars($userVoteLabel); ?>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($meetingActive): ?>
                                        <?php if ($canManageQuestions): ?>
                                            <table class="vote-table">
                                                <thead>
                                                    <tr>
                                                        <th>Участник</th>
                                                        <th>Записан вот</th>
                                                        <th>Запиши</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php if (!empty($participants)): ?>
                                                        <?php foreach ($participants as $participant): ?>
                                                            <?php
                                                            $participantName = $participant['username'] ?? '';
                                                            if ($participantName === '') {
                                                                continue;
                                                            }
                                                            $rawVoteValue = $votes[$participantName] ?? null;
                                                            $voteValue = $rawVoteValue !== null && isset($voteLabels[$rawVoteValue]) ? $voteLabels[$rawVoteValue] : 'няма вот';
                                                            $statementValue = $statements[$participantName] ?? '';
                                                            ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($participantName); ?></td>
                                                                <td><?php echo htmlspecialchars($voteValue); ?></td>
                                                                <td>
                                                                    <form action="submit_vote.php" method="POST">
                                                                        <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                                                                        <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($question['id'] ?? ''); ?>">
                                                                        <input type="hidden" name="vote_for" value="<?php echo htmlspecialchars($participantName); ?>">
                                                                        <textarea name="statement" class="statement-input" placeholder="Изказване (по избор)"><?php echo htmlspecialchars($statementValue); ?></textarea>
                                                                        <div class="vote-actions">
                                                                            <button type="submit" name="vote" value="yes" class="vote-btn yes small <?php echo $rawVoteValue === 'yes' ? 'active' : ''; ?>">Да</button>
                                                                            <button type="submit" name="vote" value="no" class="vote-btn no small <?php echo $rawVoteValue === 'no' ? 'active' : ''; ?>">Не</button>
                                                                            <button type="submit" name="vote" value="abstain" class="vote-btn abstain small <?php echo $rawVoteValue === 'abstain' ? 'active' : ''; ?>">Въздържал се</button>
                                                                        </div>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="3" class="vote-disabled">Няма участници за този орган.</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        <?php else: ?>
                                            <div class="vote-disabled">Гласовете се въвеждат от секретаря.</div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="vote-disabled">Гласуването е възможно само по време на заседанието.</div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($statementItems)): ?>
                                    <div class="statement-list">
                                        <div class="statement-title">Изказвания</div>
                                        <?php foreach ($statementItems as $statementUser => $statementText): ?>
                                            <div class="statement-item">
                                                <span class="statement-name"><?php echo htmlspecialchars($statementUser); ?>:</span>
                                                <span><?php echo nl2br(htmlspecialchars($statementText)); ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($canManageQuestions): ?>
                                    <div class="question-actions">
                                        <form action="advance_question.php" method="POST">
                                            <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                                            <button type="submit" class="action-btn primary">Приключи и премини към следваща</button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <p class="empty-message">Няма избрана сегашна точка.</p>
                        <?php endif; ?>
                    </div>

                    <div class="agenda-group">
                        <div class="agenda-title">Бъдещи точки</div>
                        <?php if (empty($futureQuestions)): ?>
                            <p class="empty-message">Няма бъдещи точки.</p>
                        <?php else: ?>
                            <div class="questions-list">
                                <?php foreach ($futureQuestions as $question): ?>
                                    <?php
                                    $votes = isset($question['votes']) && is_array($question['votes']) ? $question['votes'] : [];
                                    $statements = isset($question['statements']) && is_array($question['statements']) ? $question['statements'] : [];
                                    list($counts, $userVote) = summarizeVotes($votes, $_SESSION['user']);
                                    $userVoteLabel = $userVote !== null && isset($voteLabels[$userVote]) ? $voteLabels[$userVote] : null;
                                    $questionNumber = $questionNumbers[$question['id']] ?? '';
                                    $statementItems = [];
                                    foreach ($statements as $statementUser => $statementText) {
                                        if (trim((string)$statementText) === '') {
                                            continue;
                                        }
                                        $statementItems[$statementUser] = $statementText;
                                    }
                                    ?>
                                    <div class="question-card">
                                        <div class="question-header">
                                            <div class="question-title"><?php echo ($questionNumber !== '' ? $questionNumber . '. ' : '') . htmlspecialchars($question['text'] ?? 'Untitled question'); ?></div>
                                            <span class="question-status future">Бъдеща</span>
                                            <div class="question-meta">
                                                Добавено от <?php echo htmlspecialchars($question['created_by'] ?? 'Непознат'); ?>
                                                <?php if (!empty($question['created_at'])): ?>
                                                    &middot; <?php echo htmlspecialchars($question['created_at']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($question['details'])): ?>
                                            <p class="question-desc"><?php echo htmlspecialchars($question['details']); ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($question['attachments']) && is_array($question['attachments'])): ?>
                                            <div class="attachments">
                                                <div class="attachment-grid">
                                                    <?php foreach ($question['attachments'] as $attachment): ?>
                                                        <?php
                                                        $attachmentPath = $attachment['path'] ?? '';
                                                        $attachmentUrl = $attachmentPath ? $attachmentPath : '#';
                                                        $attachmentName = $attachment['original_name'] ?? basename($attachmentPath);
                                                        $attachmentType = $attachment['type'] ?? '';
                                                        $isImage = strpos($attachmentType, 'image/') === 0;
                                                        ?>
                                                        <div class="attachment-item">
                                                            <?php if ($isImage && $attachmentPath): ?>
                                                                <img src="<?php echo htmlspecialchars($attachmentUrl); ?>" alt="<?php echo htmlspecialchars($attachmentName); ?>">
                                                            <?php endif; ?>
                                                            <a href="<?php echo htmlspecialchars($attachmentUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                                <?php echo htmlspecialchars($attachmentName); ?>
                                                            </a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="vote-area">
                                            <div class="vote-disabled">Точката предстои.</div>
                                        </div>

                                        <?php if ($canManageQuestions): ?>
                                            <div class="question-actions">
                                                <form action="set_current_question.php" method="POST">
                                                    <input type="hidden" name="meeting_id" value="<?php echo htmlspecialchars($meeting['id']); ?>">
                                                    <input type="hidden" name="question_id" value="<?php echo htmlspecialchars($question['id'] ?? ''); ?>">
                                                    <button type="submit" class="action-btn ghost">Избери за сегашна</button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="agenda-group">
                        <div class="agenda-title">Минали точки</div>
                        <?php if (empty($pastQuestions)): ?>
                            <p class="empty-message">Няма приключени точки.</p>
                        <?php else: ?>
                            <div class="questions-list">
                                <?php foreach ($pastQuestions as $question): ?>
                                    <?php
                                    $votes = isset($question['votes']) && is_array($question['votes']) ? $question['votes'] : [];
                                    list($counts, $userVote) = summarizeVotes($votes, $_SESSION['user']);
                                    $userVoteLabel = $userVote !== null && isset($voteLabels[$userVote]) ? $voteLabels[$userVote] : null;
                                    $questionNumber = $questionNumbers[$question['id']] ?? '';
                                    ?>
                                    <div class="question-card">
                                        <div class="question-header">
                                            <div class="question-title"><?php echo ($questionNumber !== '' ? $questionNumber . '. ' : '') . htmlspecialchars($question['text'] ?? 'Untitled question'); ?></div>
                                            <span class="question-status past">Минала</span>
                                            <div class="question-meta">
                                                Добавено от <?php echo htmlspecialchars($question['created_by'] ?? 'Непознат'); ?>
                                                <?php if (!empty($question['created_at'])): ?>
                                                    &middot; <?php echo htmlspecialchars($question['created_at']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!empty($question['details'])): ?>
                                            <p class="question-desc"><?php echo htmlspecialchars($question['details']); ?></p>
                                        <?php endif; ?>

                                        <?php if (!empty($question['attachments']) && is_array($question['attachments'])): ?>
                                            <div class="attachments">
                                                <div class="attachment-grid">
                                                    <?php foreach ($question['attachments'] as $attachment): ?>
                                                        <?php
                                                        $attachmentPath = $attachment['path'] ?? '';
                                                        $attachmentUrl = $attachmentPath ? $attachmentPath : '#';
                                                        $attachmentName = $attachment['original_name'] ?? basename($attachmentPath);
                                                        $attachmentType = $attachment['type'] ?? '';
                                                        $isImage = strpos($attachmentType, 'image/') === 0;
                                                        ?>
                                                        <div class="attachment-item">
                                                            <?php if ($isImage && $attachmentPath): ?>
                                                                <img src="<?php echo htmlspecialchars($attachmentUrl); ?>" alt="<?php echo htmlspecialchars($attachmentName); ?>">
                                                            <?php endif; ?>
                                                            <a href="<?php echo htmlspecialchars($attachmentUrl); ?>" target="_blank" rel="noopener noreferrer">
                                                                <?php echo htmlspecialchars($attachmentName); ?>
                                                            </a>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="vote-area">
                                            <div class="vote-counts">
                                                Да: <?php echo $counts['yes']; ?> · Не: <?php echo $counts['no']; ?> · Въздържал се: <?php echo $counts['abstain']; ?>
                                                <?php if ($userVoteLabel): ?>
                                                    · Вашият вот: <?php echo htmlspecialchars($userVoteLabel); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="vote-disabled">Точката е приключена.</div>
                                        </div>

                                        <?php if (!empty($statementItems)): ?>
                                            <div class="statement-list">
                                                <div class="statement-title">Изказвания</div>
                                                <?php foreach ($statementItems as $statementUser => $statementText): ?>
                                                    <div class="statement-item">
                                                        <span class="statement-name"><?php echo htmlspecialchars($statementUser); ?>:</span>
                                                        <span><?php echo nl2br(htmlspecialchars($statementText)); ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
