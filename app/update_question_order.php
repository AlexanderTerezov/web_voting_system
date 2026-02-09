<?php
session_start();

date_default_timezone_set('Europe/Sofia');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);
if (!is_array($data)) {
    $data = $_POST;
}

$meeting_id = trim($data['meeting_id'] ?? '');
$order = $data['order'] ?? [];
if (!is_array($order)) {
    $order = [];
}
$order = array_values(array_unique(array_filter(array_map('trim', $order))));

if ($meeting_id === '' || empty($order)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid payload']);
    exit();
}

require_once __DIR__ . '/db.php';
$pdo = getDb();

$meetingStmt = $pdo->prepare('SELECT * FROM meetings WHERE id = :id');
$meetingStmt->execute([':id' => $meeting_id]);
$meeting = $meetingStmt->fetch();

if ($meeting === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Meeting not found']);
    exit();
}

// Access check (admin or secretary)
$canManage = false;
if ($_SESSION['role'] === 'Admin') {
    $canManage = true;
} else {
    $participantStmt = $pdo->prepare('SELECT role FROM agency_participants WHERE agency_id = :agency_id AND username = :username LIMIT 1');
    $participantStmt->execute([
        ':agency_id' => $meeting['agency_id'],
        ':username' => $_SESSION['user']
    ]);
    $participant = $participantStmt->fetch();
    if ($participant && hasRole($participant['role'], 'secretary')) {
        $canManage = true;
    }
}

if (!$canManage) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit();
}

$futureStmt = $pdo->prepare(
    'SELECT id, sort_order
     FROM questions
     WHERE meeting_id = :meeting_id AND status = :status
     ORDER BY sort_order ASC, created_at ASC, id ASC'
);
$futureStmt->execute([
    ':meeting_id' => $meeting_id,
    ':status' => 'future'
]);
$futureQuestions = $futureStmt->fetchAll();

if (empty($futureQuestions)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No future questions']);
    exit();
}

$existingIds = array_map(function($row) {
    return $row['id'];
}, $futureQuestions);

if (count($existingIds) !== count($order) ||
    array_diff($order, $existingIds) ||
    array_diff($existingIds, $order)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Order mismatch']);
    exit();
}

$sortOrders = array_map(function($row) {
    return (int)$row['sort_order'];
}, $futureQuestions);

$needsSequential = false;
foreach ($sortOrders as $value) {
    if ($value <= 0) {
        $needsSequential = true;
        break;
    }
}

if ($needsSequential) {
    $sortOrders = range(1, count($futureQuestions));
} else {
    sort($sortOrders, SORT_NUMERIC);
}

$pdo->beginTransaction();
try {
    $updateStmt = $pdo->prepare('UPDATE questions SET sort_order = :sort_order WHERE id = :id');
    foreach ($order as $index => $questionId) {
        $updateStmt->execute([
            ':sort_order' => $sortOrders[$index],
            ':id' => $questionId
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to update order']);
    exit();
}

echo json_encode(['success' => true]);
exit();
?>
