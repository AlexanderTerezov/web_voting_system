<?php
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: index.php');
    exit();
}

$username = $_SESSION['user'];
$email = $_SESSION['email'];
$role = $_SESSION['role'];
$loginTime = $_SESSION['login_time'];

// Load agencies
$agencies_file = '../db/agencies.json';
$agencies = [];
if (file_exists($agencies_file)) {
    $agencies = json_decode(file_get_contents($agencies_file), true);
}

// Load users for admin
$users = [];
if ($role === 'Admin') {
    $users_file = '../db/users.json';
    if (file_exists($users_file)) {
        $users = json_decode(file_get_contents($users_file), true);
    }
}

// Filter agencies for normal users
$userAgencies = [];
if ($role !== 'Admin') {
    foreach ($agencies as $agency) {
        foreach ($agency['participants'] as $participant) {
            if ($participant['username'] === $username) {
                $userAgencies[] = [
                    'agency' => $agency,
                    'role' => $participant['role']
                ];
                break;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .header {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        h1 { color: #333; margin-bottom: 1rem; }
        .user-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
        }
        .user-info p { margin: 0.5rem 0; color: #555; }
        .user-role {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .admin-role { background: #667eea; color: white; }
        .user-role-badge { background: #3498db; color: white; }
        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.6rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 1rem;
        }
        .logout-btn:hover { background: #c0392b; }
        
        /* Admin Panel */
        .admin-panel {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .admin-panel h2 { color: #333; margin-bottom: 1.5rem; }
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
        .participant-item select { width: 200px; }
        .participant-item input { width: 150px; }
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
        
        /* Agencies List */
        .agencies-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .agencies-section h2 { color: #333; margin-bottom: 1.5rem; }
        .agency-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
        }
        .agency-card h3 { color: #333; margin-bottom: 1rem; }
        .agency-info { margin-bottom: 0.5rem; color: #555; }
        .participant-list {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
        }
        .participant {
            display: inline-block;
            padding: 0.3rem 0.8rem;
            background: #e3f2fd;
            border-radius: 15px;
            margin: 0.3rem;
            font-size: 0.9rem;
        }
        .participant.secretary {
            background: #fff3cd;
            font-weight: 600;
        }
        .delete-agency-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 1rem;
            margin-right: 0.5rem;
        }
        .delete-agency-btn:hover { background: #c0392b; }
        .edit-agency-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 1rem;
        }
        .edit-agency-btn:hover { background: #2980b9; }
        .message {
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
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
        <div class="header">
            <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
            <div class="user-info">
                <p><strong>Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                <p><strong>Role:</strong> <span class="user-role <?php echo $role === 'Admin' ? 'admin-role' : 'user-role-badge'; ?>"><?php echo htmlspecialchars($role); ?></span></p>
                <p><strong>Login Time:</strong> <?php echo htmlspecialchars($loginTime); ?></p>
            </div>
            <form action="logout.php" method="POST">
                <button type="submit" class="logout-btn">Logout</button>
            </form>
        </div>

        <?php if ($role === 'Admin'): ?>
            <!-- Admin Panel: Create Agency -->
            <div class="admin-panel">
                <h2>Create New Agency</h2>
                <?php if (isset($_GET['success'])): ?>
                    <div class="message success"><?php echo htmlspecialchars($_GET['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['error'])): ?>
                    <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
                <?php endif; ?>
                
                <form action="create_agency.php" method="POST" id="agencyForm">
                    <div class="form-group">
                        <label for="agency_name">Agency Name</label>
                        <input type="text" id="agency_name" name="agency_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="quorum">Quorum</label>
                        <input type="number" id="quorum" name="quorum" min="1" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Participants</label>
                        <div id="participantsContainer">
                            <div class="participant-item">
                                <select name="participants[]" required>
                                    <option value="">Select User</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?php echo htmlspecialchars($user['username']); ?>">
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="roles[]" required>
                                    <option value="member">Member</option>
                                    <option value="secretary">Secretary</option>
                                </select>
                                <button type="button" class="remove-participant-btn" onclick="removeParticipant(this)">Remove</button>
                            </div>
                        </div>
                        <button type="button" class="add-participant-btn" onclick="addParticipant()">+ Add Participant</button>
                    </div>
                    
                    <button type="submit" class="submit-btn">Create Agency</button>
                </form>
            </div>

            <!-- Admin View: All Agencies -->
            <div class="agencies-section">
                <h2>All Agencies</h2>
                <?php if (empty($agencies)): ?>
                    <p style="color: #999; text-align: center;">No agencies created yet.</p>
                <?php else: ?>
                    <?php foreach ($agencies as $index => $agency): ?>
                        <div class="agency-card">
                            <h3><?php echo htmlspecialchars($agency['name']); ?></h3>
                            <p class="agency-info"><strong>Quorum:</strong> <?php echo htmlspecialchars($agency['quorum']); ?></p>
                            <p class="agency-info"><strong>Total Participants:</strong> <?php echo count($agency['participants']); ?></p>
                            <p class="agency-info"><strong>Created:</strong> <?php echo htmlspecialchars($agency['created_at']); ?></p>
                            
                            <div class="participant-list">
                                <strong>Participants:</strong><br>
                                <?php foreach ($agency['participants'] as $participant): ?>
                                    <span class="participant <?php echo $participant['role'] === 'secretary' ? 'secretary' : ''; ?>">
                                        <?php echo htmlspecialchars($participant['username']); ?>
                                        <?php echo $participant['role'] === 'secretary' ? '(Secretary)' : ''; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                            
                            <form action="delete_agency.php" method="POST" style="display: inline;">
                                <input type="hidden" name="agency_index" value="<?php echo $index; ?>">
                                <button type="submit" class="delete-agency-btn" onclick="return confirm('Are you sure you want to delete this agency?')">Delete Agency</button>
                            </form>
                            <a href="edit_agency.php?index=<?php echo $index; ?>">
                                <button type="button" class="edit-agency-btn">Edit Agency</button>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- User View: Their Agencies Only -->
            <div class="agencies-section">
                <h2>My Agencies</h2>
                <?php if (empty($userAgencies)): ?>
                    <p style="color: #999; text-align: center;">You are not a member of any agency yet.</p>
                <?php else: ?>
                    <?php foreach ($userAgencies as $item): ?>
                        <?php $agency = $item['agency']; $userRole = $item['role']; ?>
                        <div class="agency-card">
                            <h3><?php echo htmlspecialchars($agency['name']); ?></h3>
                            <p class="agency-info"><strong>Your Role:</strong> 
                                <span class="participant <?php echo $userRole === 'secretary' ? 'secretary' : ''; ?>">
                                    <?php echo $userRole === 'secretary' ? 'Secretary' : 'Member'; ?>
                                </span>
                            </p>
                            <p class="agency-info"><strong>Quorum:</strong> <?php echo htmlspecialchars($agency['quorum']); ?></p>
                            <p class="agency-info"><strong>Total Participants:</strong> <?php echo count($agency['participants']); ?></p>
                            
                            <div class="participant-list">
                                <strong>All Participants:</strong><br>
                                <?php foreach ($agency['participants'] as $participant): ?>
                                    <span class="participant <?php echo $participant['role'] === 'secretary' ? 'secretary' : ''; ?>">
                                        <?php echo htmlspecialchars($participant['username']); ?>
                                        <?php echo $participant['role'] === 'secretary' ? '(Secretary)' : ''; ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
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