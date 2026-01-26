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
        .edit-panel {
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
        .participant-item {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.5rem;
            align-items: center;
        }
        .participant-item select { flex: 1; }
        .add-participant-btn, .remove-participant-btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
        }
        .add-participant-btn {
            background: #27ae60;
            color: white;
            margin-bottom: 1rem;
        }
        .remove-participant-btn {
            background: #e74c3c;
            color: white;
        }
        .submit-btn {
            width: 100%;
            padding: 0.75rem;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
        }
        .submit-btn:hover { background: #5568d3; }
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