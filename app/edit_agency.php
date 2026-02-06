<?php
session_start();

if (!isset($_SESSION['user']) || $_SESSION['role'] !== 'Admin') {
    header('Location: index.php');
    exit();
}

require_once __DIR__ . '/db.php';
$pdo = getDb();

$agency_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

$agencyStmt = $pdo->prepare('SELECT * FROM agencies WHERE id = :id');
$agencyStmt->execute([':id' => $agency_id]);
$agency = $agencyStmt->fetch();

if (!$agency) {
    header('Location: dashboard.php?error=Органът не е намерен');
    exit();
}

$participantsStmt = $pdo->prepare('SELECT ap.username, ap.role, u.email FROM agency_participants ap LEFT JOIN users u ON u.username = ap.username WHERE ap.agency_id = :id');
$participantsStmt->execute([':id' => $agency_id]);
$agency['participants'] = $participantsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирай орган</title>
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
        .form-group input:not([type="checkbox"]), .form-group select{
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            outline: none;
            background: #fff;
            transition: border-color 120ms ease, box-shadow 120ms ease;
        }
        .form-group input:not([type="checkbox"]):focus, .form-group select:focus{
            border-color: rgba(31,75,153,0.55);
            box-shadow: 0 0 0 4px rgba(31,75,153,0.12);
        }
        .participant-item{
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            align-items: center;
        }
        .participant-item input[type="email"]{ flex: 1.2; }
        .participant-roles{
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1;
        }
        .participant-roles label{
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--muted);
            padding: 6px 8px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: #fff;
            margin: 0;
        }
        .participant-roles input{ margin: 0; }
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
        <a href="dashboard.php" class="back-btn">← Назад към таблото</a>
        
        <div class="edit-panel">
            <h2>Редактирай орган</h2>
            <?php if (isset($_GET['error'])): ?>
                <div class="message error"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <form action="update_agency.php" method="POST">
                <input type="hidden" name="agency_id" value="<?php echo $agency_id; ?>">
                
                <div class="form-group">
                    <label for="agency_name">Име на орган</label>
                    <input type="text" id="agency_name" name="agency_name" value="<?php echo htmlspecialchars($agency['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="quorum">Кворум</label>
                    <input type="number" id="quorum" name="quorum" min="1" value="<?php echo htmlspecialchars($agency['quorum']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Участници</label>
                                        <?php
                    $participantsForForm = $agency['participants'];
                    if (empty($participantsForForm)) {
                        $participantsForForm = [['email' => '', 'role' => 'member']];
                    }
                    ?>
                    <div id="participantsContainer">
                        <?php foreach ($participantsForForm as $index => $participant): ?>
                            <?php $roles = normalizeRoles($participant['role'] ?? 'member'); ?>
                            <div class="participant-item" data-index="<?php echo $index; ?>">
                                <input type="email" class="participant-email" name="participants[<?php echo $index; ?>][email]" value="<?php echo htmlspecialchars($participant['email'] ?? ''); ?>" required placeholder="email@example.com">
                                <div class="participant-roles">
                                    <label><input type="checkbox" class="participant-role" name="participants[<?php echo $index; ?>][roles][]" value="member" <?php echo in_array('member', $roles, true) ? 'checked' : ''; ?>>Член</label>
                                    <label><input type="checkbox" class="participant-role" name="participants[<?php echo $index; ?>][roles][]" value="secretary" <?php echo in_array('secretary', $roles, true) ? 'checked' : ''; ?>>Секретар</label>
                                </div>
                                <button type="button" class="remove-participant-btn" onclick="removeParticipant(this)">Премахни</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-participant-btn" onclick="addParticipant()">+ Добави участник</button>
                </div>
                
                <button type="submit" class="submit-btn">Запази промените</button>
            </form>
        </div>
    </div>

    <script>
        function assignParticipantIndex(item, index) {
            item.dataset.index = index;
            const emailInput = item.querySelector('.participant-email');
            if (emailInput) {
                emailInput.name = `participants[${index}][email]`;
            }
            const roleInputs = item.querySelectorAll('.participant-role');
            roleInputs.forEach((input) => {
                input.name = `participants[${index}][roles][]`;
            });
        }

        function resetParticipantItem(item) {
            const emailInput = item.querySelector('.participant-email');
            if (emailInput) {
                emailInput.value = '';
            }
            const roleInputs = item.querySelectorAll('.participant-role');
            roleInputs.forEach((input) => {
                input.checked = input.value === 'member';
            });
        }

        function reindexParticipants() {
            const container = document.getElementById('participantsContainer');
            Array.from(container.children).forEach((item, index) => {
                assignParticipantIndex(item, index);
            });
        }

        function addParticipant() {
            const container = document.getElementById('participantsContainer');
            const newItem = container.firstElementChild.cloneNode(true);
            resetParticipantItem(newItem);
            container.appendChild(newItem);
            reindexParticipants();
        }

        function removeParticipant(btn) {
            const container = document.getElementById('participantsContainer');
            if (container.children.length > 1) {
                btn.parentElement.remove();
                reindexParticipants();
            } else {
                alert('\u041d\u0443\u0436\u0435\u043d \u0435 \u043f\u043e\u043d\u0435 \u0435\u0434\u0438\u043d \u0443\u0447\u0430\u0441\u0442\u043d\u0438\u043a');
            }
        }
    </script>
</body>
</html>
