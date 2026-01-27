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
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Question text is required');
    exit();
}

$meetings_file = '../db/meetings.json';
if (!file_exists($meetings_file)) {
    header('Location: dashboard.php?error=Meetings file not found');
    exit();
}

$meetings = json_decode(file_get_contents($meetings_file), true);
$meetingIndex = null;
$meeting = null;
foreach ($meetings as $index => $m) {
    if (($m['id'] ?? '') === $meeting_id) {
        $meetingIndex = $index;
        $meeting = $m;
        break;
    }
}

if ($meeting === null) {
    header('Location: dashboard.php?error=Meeting not found');
    exit();
}

// Load agencies and verify secretary/admin access
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

$canManageQuestions = false;
if ($_SESSION['role'] === 'Admin') {
    $canManageQuestions = true;
} else {
    foreach ($agencies as $agency) {
        if (($agency['name'] ?? '') === ($meeting['agency_name'] ?? '')) {
            foreach ($agency['participants'] as $participant) {
                if (($participant['username'] ?? '') === $_SESSION['user'] && ($participant['role'] ?? '') === 'secretary') {
                    $canManageQuestions = true;
                    break 2;
                }
            }
        }
    }
}

if (!$canManageQuestions) {
    header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&error=Only secretaries can add questions');
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
        $errors[] = 'Could not create upload directory.';
    }

    if (empty($errors)) {
        $totalFiles = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $totalFiles; $i++) {
            $error = $_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if ($error !== UPLOAD_ERR_OK) {
                $errors[] = 'One of the attachments failed to upload.';
                continue;
            }

            $size = intval($_FILES['attachments']['size'][$i] ?? 0);
            if ($size > $maxFileSize) {
                $errors[] = 'Attachment exceeds the 10MB size limit.';
                continue;
            }

            $originalName = $_FILES['attachments']['name'][$i] ?? 'attachment';
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($extension === '' || !in_array($extension, $allowedExtensions, true)) {
                $errors[] = 'Unsupported attachment type: ' . htmlspecialchars($originalName);
                continue;
            }

            $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName);
            $storedName = uniqid('att_', true) . '.' . $extension;
            $targetPath = $uploadDir . '/' . $storedName;

            if (!move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $targetPath)) {
                $errors[] = 'Could not save attachment: ' . htmlspecialchars($originalName);
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
    'created_by' => $_SESSION['user'],
    'created_at' => date('Y-m-d H:i:s')
];

if (!isset($meetings[$meetingIndex]['questions']) || !is_array($meetings[$meetingIndex]['questions'])) {
    $meetings[$meetingIndex]['questions'] = [];
}

$meetings[$meetingIndex]['questions'][] = $question;

file_put_contents($meetings_file, json_encode($meetings, JSON_PRETTY_PRINT), LOCK_EX);

header('Location: view_meeting.php?id=' . urlencode($meeting_id) . '&success=Question added');
exit();
?>
