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

// Check if user is secretary in this agency (including admin)
$isSecretary = false;
foreach ($agency['participants'] as $participant) {
    if ($participant['username'] === $_SESSION['user'] && $participant['role'] === 'secretary') {
        $isSecretary = true;
        break;
    }
}

if (!$isSecretary) {
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
        :root{
            --bg: #1110;
            --card: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --shadow: 0 10px 24px rgba(17, 24, 39, 0.08);
            --radius: 14px;
            --accent: #1f4b99;
            --danger-bg: #fef2f2;
            --danger-text: #991b1b;
            --success-bg: #ecfdf5;
            --success-text: #065f46;
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
            max-width: 820px;
            margin: 0 auto;
        }
        .meeting-panel{
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 24px;
        }
        h2{
            font-size: 20px;
            line-height: 1.2;
            margin: 0 0 12px 0;
            letter-spacing: -0.01em;
        }
        .form-group{ margin-bottom: 14px; }
        .form-group label{
            display: block;
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .form-group input, .form-group select{
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            background: #fff;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }
        .form-group input:focus, .form-group select:focus{
            border-color: rgba(31,75,153,0.55);
            box-shadow: 0 0 0 4px rgba(31,75,153,0.12);
        }
        .submit-btn{
            width: 100%;
            padding: 11px 12px;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            background: #111827;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 80ms ease, opacity 120ms ease;
        }
        .submit-btn:hover{ opacity: 0.95; }
        .submit-btn:active{ transform: translateY(1px); }
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
            margin-bottom: 14px;
            font-weight: 600;
        }
        .back-btn:hover{ text-decoration: none; box-shadow: 0 4px 12px rgba(17,24,39,0.08); }
        .message{
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 10px 12px;
            margin: 14px 0;
            font-size: 14px;
        }
        .error{
            background: var(--danger-bg);
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
        }
        .agency-info{
            background: #f8fafc;
            color: var(--text);
            border: 1px solid var(--border);
            padding: 10px 12px;
            border-radius: 10px;
            margin-bottom: 14px;
            font-size: 14px;
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
