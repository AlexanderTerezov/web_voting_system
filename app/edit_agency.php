<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Admin') {
    header('Location: index.php');
    exit();
}

$agency_index = isset($_GET['index']) ? intval($_GET['index']) : -1;

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

// Load users
$users = [];
$users_file = '../db/users.json';
if (file_exists($users_file)) {
    $users = json_decode(file_get_contents($users_file), true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Agency</title>
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
            max-width: 860px;
            margin: 0 auto;
        }
        .edit-panel{
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
        .participant-item{
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }
        .participant-item select{ flex: 1; }
        .add-participant-btn, .remove-participant-btn{
            padding: 8px 12px;
            border: 1px solid rgba(17,24,39,0.12);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            background: #111827;
            color: #fff;
        }
        .add-participant-btn{
            margin-bottom: 10px;
        }
        .remove-participant-btn{
            background: #fff;
            color: var(--danger-text);
            border-color: rgba(153,27,27,0.25);
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
    </style>
</head>
<body>
    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        
        <div class="edit-panel">
            <h2>Edit Agency</h2>
            <?php if (isset($_GET['error'])): ?>
                <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <form action="update_agency.php" method="POST">
                <input type="hidden" name="agency_index" value="<?php echo $agency_index; ?>">
                
                <div class="form-group">
                    <label for="agency_name">Agency Name</label>
                    <input type="text" id="agency_name" name="agency_name" value="<?php echo htmlspecialchars($agency['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="quorum">Quorum</label>
                    <input type="number" id="quorum" name="quorum" min="1" value="<?php echo htmlspecialchars($agency['quorum']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Participants</label>
                    <div id="participantsContainer">
                        <?php foreach ($agency['participants'] as $participant): ?>
                            <div class="participant-item">
                                <select name="participants[]" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['username']); ?>" 
                                            <?php echo $user['username'] === $participant['username'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="roles[]" required>
                                    <option value="member" <?php echo $participant['role'] === 'member' ? 'selected' : ''; ?>>Member</option>
                                    <option value="secretary" <?php echo $participant['role'] === 'secretary' ? 'selected' : ''; ?>>Secretary</option>
                                </select>
                                <button type="button" class="remove-participant-btn" onclick="removeParticipant(this)">Remove</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-participant-btn" onclick="addParticipant()">+ Add Participant</button>
                </div>
                
                <button type="submit" class="submit-btn">Update Agency</button>
            </form>
        </div>
    </div>

    <script>
        function addParticipant() {
            const container = document.getElementById('participantsContainer');
            const newItem = container.firstElementChild.cloneNode(true);
            newItem.querySelector('select[name="participants[]"]').value = '';
            newItem.querySelector('select[name="roles[]"]').value = 'member';
            container.appendChild(newItem);
        }

        function removeParticipant(btn) {
            const container = document.getElementById('participantsContainer');
            if (container.children.length > 1) {
                btn.parentElement.remove();
            } else {
                alert('At least one participant is required');
            }
        }
    </script>
</body>
</html>
