<?php
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$agency_index = isset($_GET['agency_index']) ? intval($_GET['agency_index']) : -1;

// Load agencies
$agencies_file = '../db/agencies.json';
if (!file_exists($agencies_file)) {
    header('Location: dashboard.php?error=No agencies found');
    exit();
}

$agencies = json_decode(file_get_contents($agencies_file), true);

if (!isset($agencies[$agency_index])) {
    header('Location: dashboard.php?error=Agency not found');
    exit();
}

$agency = $agencies[$agency_index];

// Check if user is secretary in this agency
$isSecretary = false;
foreach ($agency['participants'] as $participant) {
    if ($participant['username'] === $_SESSION['user'] && $participant['role'] === 'secretary') {
        $isSecretary = true;
        break;
    }
}

if (!$isSecretary && $_SESSION['role'] !== 'Admin') {
    header('Location: dashboard.php?error=Only secretaries can create meetings');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Meeting</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .meeting-panel {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        h2 { color: #333; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: 500;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        .submit-btn {
            width: 100%;
            padding: 0.75rem;
            background: #27ae60;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .submit-btn:hover { background: #229954; }
        .back-btn {
            display: inline-block;
            padding: 0.5rem 1rem;
            background: #95a5a6;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .back-btn:hover { background: #7f8c8d; }
        .message {
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .error {
            background: #ffe5e5;
            color: #e74c3c;
            border: 1px solid #e74c3c;
        }
        .agency-info {
            background: #e8f5e9;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="meeting-panel">
            <h2>Create Meeting</h2>
            
            <div class="agency-info">
                <strong>Agency:</strong> <?php echo htmlspecialchars($agency['name']); ?>
            </div>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <form action="save_meeting.php" method="POST">
                <input type="hidden" name="agency_index" value="<?php echo $agency_index; ?>">
                <input type="hidden" name="agency_name" value="<?php echo htmlspecialchars($agency['name']); ?>">
                
                <div class="form-group">
                    <label for="meeting_date">Meeting Date</label>
                    <input type="date" id="meeting_date" name="meeting_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label for="meeting_time">Meeting Time</label>
                    <input type="time" id="meeting_time" name="meeting_time" required>
                </div>
                
                <div class="form-group">
                    <label for="recurring">Recurring</label>
                    <select id="recurring" name="recurring" required>
                        <option value="Once">Once</option>
                        <option value="Daily">Daily</option>
                        <option value="Weekly">Weekly</option>
                        <option value="Monthly">Monthly</option>
                    </select>
                </div>
                
                <button type="submit" class="submit-btn">Create Meeting</button>
            </form>
        </div>
    </div>
</body>
</html>