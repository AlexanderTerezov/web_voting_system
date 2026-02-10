<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$agency_id = intval($_POST['agency_id']);
$meeting_name = trim($_POST['meeting_name']);
$meeting_reason = trim($_POST['meeting_reason'] ?? '');
$meeting_date = $_POST['meeting_date'];
$meeting_time = $_POST['meeting_time'];
$duration = intval($_POST['duration']);
$recurring = $_POST['recurring'];

if (empty($meeting_name) || empty($meeting_date) || empty($meeting_time)) {
    header('Location: create_meeting.php?agency_id=' . $agency_id . '&error=Моля попълнете задължителните полета');
    exit();
}

require_once __DIR__ . '/db.php';
$pdo = getDb();

// Извличане на информация за органа
$agencyStmt = $pdo->prepare('SELECT id, default_questions FROM agencies WHERE id = :id');
$agencyStmt->execute([':id' => $agency_id]);
$agencyData = $agencyStmt->fetch();

if (!$agencyData) {
    header('Location: dashboard.php?error=Органът не е намерен');
    exit();
}

$defaultQuestionsText = $agencyData['default_questions'] ?? '';

// Проверка за права
$participantStmt = $pdo->prepare('SELECT role FROM agency_participants WHERE agency_id = :agency_id AND username = :username LIMIT 1');
$participantStmt->execute([':agency_id' => $agency_id, ':username' => $_SESSION['user']]);
$participant = $participantStmt->fetch();

if (!$participant || !hasRole($participant['role'], 'secretary')) {
    header('Location: dashboard.php?error=Нямате права');
    exit();
}

// Създаване на заседанието
$meeting_id = uniqid('meeting_', true);
$series_id = $meeting_id;

$insertMeeting = $pdo->prepare(
    'INSERT INTO meetings (id, series_id, name, reason, comments, agency_id, date, time, duration, recurring, created_by, created_at)
     VALUES (:id, :series_id, :name, :reason, "", :agency_id, :date, :time, :duration, :recurring, :created_by, NOW())'
);

$insertMeeting->execute([
    ':id' => $meeting_id,
    ':series_id' => $series_id,
    ':name' => $meeting_name,
    ':reason' => $meeting_reason,
    ':agency_id' => $agency_id,
    ':date' => $meeting_date,
    ':time' => $meeting_time,
    ':duration' => $duration,
    ':recurring' => $recurring,
    ':created_by' => $_SESSION['user']
]);

// АВТОМАТИЧНО ДОБАВЯНЕ 
if (!empty($defaultQuestionsText)) {
    $lines = explode("\n", $defaultQuestionsText);
    $questionsToAdd = [];
    $currentQ = null;
    
    $pushCurrent = function(&$batch, &$current) {
        if ($current) {
            $batch[] = $current;
            $current = null;
        }
    };

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        if (str_starts_with($line, '*') && !str_starts_with($line, '**')) {
            $pushCurrent($questionsToAdd, $currentQ);
            
            $title = trim(substr($line, 1));
            $currentQ = [
                'text' => $title,
                'details' => ''
            ];
        } 
        else {
            if ($currentQ) {
                $cleanLine = $line;
                if (str_starts_with($line, '**')) $cleanLine = trim(substr($line, 2));
                elseif (str_starts_with($line, '-')) $cleanLine = trim(substr($line, 1));
                elseif (str_starts_with($line, '#')) $cleanLine = "(Файл: " . trim(substr($line, 1)) . ")";

                $currentQ['details'] .= $cleanLine . "\n";
            }
        }
    }
    $pushCurrent($questionsToAdd, $currentQ);

    if (!empty($questionsToAdd)) {
        $insertQ = $pdo->prepare(
            'INSERT INTO questions (id, meeting_id, text, details, status, sort_order, created_by, created_at)
             VALUES (:id, :meeting_id, :text, :details, "future", :sort_order, :created_by, NOW())'
        );

        $sortOrder = 0;
        foreach ($questionsToAdd as $q) {
            $sortOrder++;
            $qId = uniqid('q_', true);
            
            $insertQ->execute([
                ':id' => $qId,
                ':meeting_id' => $meeting_id,
                ':text' => $q['text'],
                ':details' => trim($q['details']),
                ':sort_order' => $sortOrder,
                ':created_by' => 'System'
            ]);
        }
    }
}

header('Location: view_meeting.php?id=' . $meeting_id . '&success=Заседанието е създадено успешно');
exit();
?>