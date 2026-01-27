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
            text-align: center;
            min-height: 260px;
            display: flex;
            align-items: center;
            justify-content: center;
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
        .back-btn:hover{ text-decoration: none; box-shadow: 0 4px 12px rgba(17,24,39,0.08); }
        .empty-message{
            color: var(--muted);
            font-size: 15px;
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
