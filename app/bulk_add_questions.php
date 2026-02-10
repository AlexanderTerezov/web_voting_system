<?php
session_start();
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit();
}

$meeting_id = $_POST['meeting_id'] ?? '';
$bulk_content = $_POST['bulk_content'] ?? '';

if (empty($meeting_id) || empty($bulk_content)) {
    header("Location: view_meeting.php?id=$meeting_id&error=Няма въведено съдържание");
    exit();
}

$pdo = getDb();

// Подготовка на папката за качване
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}


$stmt = $pdo->prepare("SELECT MAX(sort_order) FROM questions WHERE meeting_id = ?");
$stmt->execute([$meeting_id]);
$maxOrder = (int)$stmt->fetchColumn();

// Парсване на текста
$lines = explode("\n", $bulk_content);
$questionsBatch = [];
$currentQuestion = null;

// Добавяне на текущия въпрос в масива
function pushCurrent(&$batch, &$current) {
    if ($current) {
        $batch[] = $current;
        $current = null;
    }
}

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) continue;

    // Нова точка 
    if (str_starts_with($line, '*') && !str_starts_with($line, '**')) {
        pushCurrent($questionsBatch, $currentQuestion);
        
        $title = trim(substr($line, 1));
        
        $currentQuestion = [
            'text' => $title,
            'details' => '',
            'files_referenced' => []
        ];
    } 
    // Детайли към текущата точка
    else {
        if ($currentQuestion) {
            $cleanLine = $line;

            // Почистване на символите:
            
            if (str_starts_with($line, '**')) {
                $cleanLine = trim(substr($line, 2));
                $currentQuestion['details'] .= $cleanLine . "\n";
            } 
            elseif (str_starts_with($line, '-')) {
                $cleanLine = trim(substr($line, 1));
                $currentQuestion['details'] .= $cleanLine . "\n";
            } 
            elseif (str_starts_with($line, '#')) {
                $fileName = trim(substr($line, 1));
                $currentQuestion['files_referenced'][] = $fileName;

            }

        }
    }
}
pushCurrent($questionsBatch, $currentQuestion); // Добавяме последната

// 4. Запис в базата и обработка на файлове
if (count($questionsBatch) > 0) {
    $sqlQuestion = "INSERT INTO questions (id, meeting_id, text, details, status, sort_order, created_by, created_at) 
                    VALUES (:id, :meeting_id, :text, :details, 'future', :sort_order, :created_by, NOW())";
    
    $sqlAttachment = "INSERT INTO attachments (question_id, original_name, stored_name, path, type, size) 
                      VALUES (:qid, :orig_name, :stored_name, :path, :type, :size)";
    
    $stmtQ = $pdo->prepare($sqlQuestion);
    $stmtA = $pdo->prepare($sqlAttachment);

    $files = $_FILES['bulk_attachments'] ?? [];
    $totalFilesUploaded = 0;

    foreach ($questionsBatch as $q) {
        $maxOrder++;
        // Генерираме ID
        $newQId = uniqid('q_', true); 

        // 4.1. Вмъкване на въпроса
        $stmtQ->execute([
            ':id' => $newQId,
            ':meeting_id' => $meeting_id,
            ':text' => $q['text'],
            ':details' => trim($q['details']),
            ':sort_order' => $maxOrder,
            ':created_by' => $_SESSION['user']
        ]);

        // 4.2. Обработка на файловете
        if (!empty($q['files_referenced']) && !empty($files['name'][0])) {
            
            foreach ($q['files_referenced'] as $refName) {
                // Търсим дали има качен файл с това име
                $key = array_search($refName, $files['name']);
                
                if ($key !== false && $files['error'][$key] === UPLOAD_ERR_OK) {
                    $tmpName = $files['tmp_name'][$key];
                    $originalName = $files['name'][$key];
                    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
                    $storedName = uniqid('att_') . '.' . $extension;
                    $destination = $uploadDir . $storedName;
                    
                    if (move_uploaded_file($tmpName, $destination)) {
                        $stmtA->execute([
                            ':qid' => $newQId,
                            ':orig_name' => $originalName,
                            ':stored_name' => $storedName,
                            ':path' => 'uploads/' . $storedName,
                            ':type' => $files['type'][$key],
                            ':size' => $files['size'][$key]
                        ]);
                        $totalFilesUploaded++;
                    }
                }
            }
        }
    }
    
    header("Location: view_meeting.php?id=$meeting_id&success=Добавени " . count($questionsBatch) . " точки и $totalFilesUploaded файла!");
} else {
    header("Location: view_meeting.php?id=$meeting_id&error=Няма намерени точки.");
}
exit();
?>