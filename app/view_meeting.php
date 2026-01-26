<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$meeting_id = isset($_GET['id']) ? $_GET['id'] : '';

// Load meetings
$meetings_file = '../db/meetings.json';
if (!file_exists($meetings_file)) {
    header('Location: dashboard.php?error=Meeting not found');
    exit();
}

$meetings = json_decode(file_get_contents($meetings_file), true);

// Find the meeting
$meeting = null;
foreach ($meetings as $m) {
    if ($m['id'] === $meeting_id) {
        $meeting = $m;
        break;
    }
}

if (!$meeting) {
    header('Location: dashboard.php?error=Meeting not found');
    exit();
}

// Load agencies to verify access
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

// Check if user has access to this meeting
$hasAccess = false;
if ($_SESSION['role'] === 'Admin') {
    $hasAccess = true;
} else {
    foreach ($agencies as $agency) {
        if ($agency['name'] === $meeting['agency_name']) {
            foreach ($agency['participants'] as $participant) {
                if ($participant['username'] === $_SESSION['user']) {
                    $hasAccess = true;
                    break 2;
                }
            }
        }
    }
}

if (!$hasAccess) {
    header('Location: dashboard.php?error=Access denied');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meeting</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .meeting-header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .meeting-content {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        h1 { color: #333; margin-bottom: 1rem; }
h2 { color: #666; }
.meeting-info {
background: #f8f9fa;
padding: 1rem;
border-radius: 5px;
margin-top: 1rem;
}
.meeting-info p {
margin: 0.5rem 0;
color: #555;
}
.back-btn {
display: inline-block;
padding: 0.6rem 1.5rem;
background: #667eea;
color: white;
text-decoration: none;
border-radius: 5px;
margin-top: 1rem;
}
.back-btn:hover { background: #5568d3; }
.empty-message {
color: #999;
font-size: 1.1rem;
}
</style>
</head>
<body>
    <div class="container">
        <div class="meeting-header">
            <h1><?php echo htmlspecialchars($meeting['agency_name']); ?> - Meeting</h1>
            <div class="meeting-info">
                <p><strong>Date:</strong> <?php echo htmlspecialchars($meeting['date']); ?></p>
                <p><strong>Time:</strong> <?php echo htmlspecialchars($meeting['time']); ?></p>
                <p><strong>Recurring:</strong> <?php echo htmlspecialchars($meeting['recurring']); ?></p>
                <p><strong>Created by:</strong> <?php echo htmlspecialchars($meeting['created_by']); ?></p>
            </div>
            <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
    <div class="meeting-content">
        <p class="empty-message">Meeting content will be added here</p>
    </div>
</div>
</body>
</html>