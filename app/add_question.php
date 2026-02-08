<?php
session_start();

date_default_timezone_set('Europe/Sofia');

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$meeting_id = trim($_POST['meeting_id'] ?? '');
$question_text = trim($_POST['question_text'] ?? '');
$question_details = trim($_POST['question_details'] ?? '');

if ($meeting_id === '' || $question_text === '') {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Текстът на точката е задължителен');
    exit();
}

require_once __DIR__ . '/db.php';
$pdo = getDb();

$meetingStmt = $pdo->prepare('SELECT * FROM meetings WHERE id = :id');
$meetingStmt->execute([':id' => $meeting_id]);
$meeting = $meetingStmt->fetch();

if ($meeting === null) {
    header('Location: dashboard.php?error=Заседанието не е намерено');
    exit();
}

// Verify secretary/admin access
$canManageQuestions = false;
if ($_SESSION['role'] === 'Admin') {
    $canManageQuestions = true;
} else {
    $participantStmt = $pdo->prepare('SELECT role FROM agency_participants WHERE agency_id = :agency_id AND username = :username LIMIT 1');
    $participantStmt->execute([
        ':agency_id' => $meeting['agency_id'],
        ':username' => $_SESSION['user']
    ]);
    $participant = $participantStmt->fetch();
    if ($participant && hasRole($participant['role'], 'secretary')) {
        $canManageQuestions = true;
    }
}

if (!$canManageQuestions) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Само секретари могат да добавят точки');
    exit();
}

$attachments = [];
$errors = [];
$storedFiles = [];
$allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'txt'];
$maxFileSize = 10 * 1024 * 1024;

if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'])) {
    $uploadDir = __DIR__ . '/uploads/meeting_attachments/' . $meeting_id;
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
        $errors[] = 'Неуспешно създаване на папка за прикачени файлове.';
    }

    if (empty($errors)) {
        $totalFiles = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $totalFiles; $i++) {
            $error = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = 'Един от файловете не може да бъде качен.';
                continue;
            }

            $size = intval($_FILES['attachments']['size'][$i] ?? 0);
            if ($size > $maxFileSize) {
                $errors[] = 'Файлът надвишава ограничението от 10MB.';
                continue;
            }

            $originalName = $_FILES['attachments']['name'][$i] ?? 'attachment';
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
                $errors[] = 'Неподдържан тип файл: ' . htmlspecialchars($originalName);
                continue;
            }

            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
            $storedName = uniqid('att_', true) . '.' . $extension;
            $targetPath = $uploadDir . '/' . $storedName;

            if (!move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $targetPath)) {
                $errors[] = 'Неуспешно записване на файла: ' . htmlspecialchars($originalName);
                continue;
            }

            $storedFiles[] = $targetPath;
            $attachments[] = [
                'original_name' => $safeName,
                'stored_name' => $storedName,
                'path' => 'uploads/meeting_attachments/' . $meeting_id . '/' . $storedName,
                'type' => mime_content_type($targetPath) ?: 'application/octet-stream',
                'size' => $size
            ];
        }
    }
}

if (!empty($errors)) {
    foreach ($storedFiles as $storedFile) {
        if (is_file($storedFile)) {
            unlink($storedFile);
        }
    }
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=' . urlencode($errors[0]));
    exit();
}

$question = [
    'id' => uniqid('question_', true),
    'text' => $question_text,
    'details' => $question_details,
    'attachments' => $attachments,
    'votes' => [],
    'status' => 'future',
    'created_by' => $_SESSION['user'],
    'created_at' => date('Y-m-d H:i:s')
];

$insertQuestion = $pdo->prepare(
    'INSERT INTO questions (id, meeting_id, text, details, status, created_by, created_at)
     VALUES (:id, :meeting_id, :text, :details, :status, :created_by, :created_at)'
);
$insertQuestion->execute([
    ':id' => $question['id'],
    ':meeting_id' => $meeting_id,
    ':text' => $question['text'],
    ':details' => $question['details'],
    ':status' => $question['status'],
    ':created_by' => $question['created_by'],
    ':created_at' => $question['created_at']
]);

if (!empty($attachments)) {
    $insertAttachment = $pdo->prepare(
        'INSERT INTO attachments (question_id, original_name, stored_name, path, type, size)
         VALUES (:question_id, :original_name, :stored_name, :path, :type, :size)'
    );
    foreach ($attachments as $attachment) {
        $insertAttachment->execute([
            ':question_id' => $question['id'],
            ':original_name' => $attachment['original_name'],
            ':stored_name' => $attachment['stored_name'],
            ':path' => $attachment['path'],
            ':type' => $attachment['type'],
            ':size' => $attachment['size']
        ]);
    }
}

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Точката е добавена');
exit();
?>
