<?php
session_start();

// Database Configuration
$host       = 'localhost';
$db_name    = 'rfid_capstone';
$username   = 'root';
$password   = '';

// Establish Database Connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Check if user is logged in (Auth Check)
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

date_default_timezone_set('Asia/Manila');

// Initialize message variable
$message = '';

// Ensure time_settings table exists
try {
    $pdo->query("SELECT 1 FROM time_settings LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("
        CREATE TABLE time_settings (
            id INT PRIMARY KEY AUTO_INCREMENT,
            morning_start TIME NOT NULL,
            morning_end TIME NOT NULL,
            morning_late_threshold TIME NOT NULL,
            afternoon_start TIME NOT NULL,
            afternoon_end TIME NOT NULL,
            allow_mon TINYINT(1) NOT NULL DEFAULT 1,
            allow_tue TINYINT(1) NOT NULL DEFAULT 1,
            allow_wed TINYINT(1) NOT NULL DEFAULT 1,
            allow_thu TINYINT(1) NOT NULL DEFAULT 1,
            allow_fri TINYINT(1) NOT NULL DEFAULT 1,
            allow_sat TINYINT(1) NOT NULL DEFAULT 0,
            allow_sun TINYINT(1) NOT NULL DEFAULT 0,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    $stmt = $pdo->prepare("
        INSERT INTO time_settings
        (morning_start, morning_end, morning_late_threshold, afternoon_start, afternoon_end,
         allow_mon, allow_tue, allow_wed, allow_thu, allow_fri, allow_sat, allow_sun)
        VALUES (?, ?, ?, ?, ?, 1, 1, 1, 1, 1, 0, 0)
    ");
    $stmt->execute(['06:00:00', '09:00:00', '08:30:00', '16:00:00', '16:30:00']);
}

// Ensure days columns exist
function ensureDayColumns(PDO $pdo) {
    $cols = [
        'allow_mon' => 1, 'allow_tue' => 1, 'allow_wed' => 1,
        'allow_thu' => 1, 'allow_fri' => 1, 'allow_sat' => 0, 'allow_sun' => 0
    ];
    foreach ($cols as $col => $default) {
        $check = $pdo->prepare("SHOW COLUMNS FROM time_settings LIKE :col");
        $check->execute([':col' => $col]);
        if (!$check->fetch()) {
            $pdo->exec("ALTER TABLE time_settings ADD COLUMN $col TINYINT(1) NOT NULL DEFAULT $default");
        }
    }
}
ensureDayColumns($pdo);

function getTimeSettings(PDO $pdo) {
    $defaultSettings = [
        'id' => 1,
        'morning_start' => '06:00:00',
        'morning_end' => '09:00:00',
        'morning_late_threshold' => '08:30:00',
        'afternoon_start' => '16:00:00',
        'afternoon_end' => '16:30:00',
        'allow_mon' => 1, 'allow_tue' => 1, 'allow_wed' => 1, 'allow_thu' => 1, 'allow_fri' => 1,
        'allow_sat' => 0, 'allow_sun' => 0,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    $stmt = $pdo->query("SELECT * FROM time_settings WHERE id = 1");
    $row = $stmt->fetch();
    if (!$row) {
        $stmt = $pdo->query("SELECT * FROM time_settings ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
    }
    return $row ?: $defaultSettings;
}

$time_settings = getTimeSettings($pdo);

function validateTimeFormat($time) {
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) return false;
    list($h,$m) = explode(':', $time);
    return is_numeric($h) && is_numeric($m) && $h >= 0 && $h <= 23 && $m >= 0 && $m <= 59;
}

function isAttendanceAllowedToday(array $settings): bool {
    $dow = (int)date('N');
    $map = [1 => 'allow_mon', 2 => 'allow_tue', 3 => 'allow_wed', 4 => 'allow_thu', 5 => 'allow_fri', 6 => 'allow_sat', 7 => 'allow_sun'];
    $col = $map[$dow];
    return !empty($settings[$col]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_settings'])) {
    $morning_start = trim((string)filter_input(INPUT_POST, 'morning_start', FILTER_UNSAFE_RAW));
    $morning_end = trim((string)filter_input(INPUT_POST, 'morning_end', FILTER_UNSAFE_RAW));
    $morning_late_threshold = trim((string)filter_input(INPUT_POST, 'morning_late_threshold', FILTER_UNSAFE_RAW));
    $afternoon_start = trim((string)filter_input(INPUT_POST, 'afternoon_start', FILTER_UNSAFE_RAW));
    $afternoon_end = trim((string)filter_input(INPUT_POST, 'afternoon_end', FILTER_UNSAFE_RAW));

    $is_valid = true;
    $error_fields = [];

    foreach (['morning_start' => $morning_start, 'morning_end' => $morning_end, 'morning_late_threshold' => $morning_late_threshold, 'afternoon_start' => $afternoon_start, 'afternoon_end' => $afternoon_end] as $field => $value) {
        if (!validateTimeFormat($value)) {
            $is_valid = false;
            $error_fields[] = $field;
        }
    }

    if (!$is_valid) {
        $message = '<div class="alert error"><i class="fas fa-exclamation-triangle"></i>&nbsp; Please enter valid time values in 24-hour format (HH:MM)</div>';
    } else {
        $ms = strtotime($morning_start . ':00');
        $mt = strtotime($morning_late_threshold . ':00');
        $me = strtotime($morning_end . ':00');
        $as = strtotime($afternoon_start . ':00');
        $ae = strtotime($afternoon_end . ':00');

        if ($ms >= $me) { $is_valid = false; $message = '<div class="alert error"><i class="fas fa-times-circle"></i>&nbsp; Morning start time must be before morning end time.</div>'; }
        elseif ($mt >= $me) { $is_valid = false; $message = '<div class="alert error"><i class="fas fa-times-circle"></i>&nbsp; Late threshold must be before morning end time.</div>'; }
        elseif ($ms >= $mt) { $is_valid = false; $message = '<div class="alert error"><i class="fas fa-times-circle"></i>&nbsp; Morning start time must be before late threshold.</div>'; }
        elseif ($as >= $ae) { $is_valid = false; $message = '<div class="alert error"><i class="fas fa-times-circle"></i>&nbsp; Afternoon start time must be before afternoon end time.</div>'; }
    }

    $allow_days = ['allow_mon' => isset($_POST['allow_mon']) ? 1 : 0, 'allow_tue' => isset($_POST['allow_tue']) ? 1 : 0, 'allow_wed' => isset($_POST['allow_wed']) ? 1 : 0, 'allow_thu' => isset($_POST['allow_thu']) ? 1 : 0, 'allow_fri' => isset($_POST['allow_fri']) ? 1 : 0, 'allow_sat' => isset($_POST['allow_sat']) ? 1 : 0, 'allow_sun' => isset($_POST['allow_sun']) ? 1 : 0];

    if ($is_valid) {
        $morning_start .= ':00'; $morning_end .= ':00'; $morning_late_threshold .= ':00'; $afternoon_start .= ':00'; $afternoon_end .= ':00';
        
        $exists = $pdo->query("SELECT COUNT(*) c FROM time_settings WHERE id=1")->fetch()['c'] ?? 0;
        if (!$exists) {
            $pdo->exec("INSERT INTO time_settings (id, morning_start, morning_end, morning_late_threshold, afternoon_start, afternoon_end, allow_mon, allow_tue, allow_wed, allow_thu, allow_fri, allow_sat, allow_sun) VALUES (1, '06:00:00','09:00:00','08:30:00','16:00:00','16:30:00',1,1,1,1,1,0,0)");
        }

        $stmt = $pdo->prepare("UPDATE time_settings SET morning_start = :morning_start, morning_end = :morning_end, morning_late_threshold = :morning_late_threshold, afternoon_start = :afternoon_start, afternoon_end = :afternoon_end, allow_mon = :allow_mon, allow_tue = :allow_tue, allow_wed = :allow_wed, allow_thu = :allow_thu, allow_fri = :allow_fri, allow_sat = :allow_sat, allow_sun = :allow_sun WHERE id = 1");
        
        $ok = $stmt->execute(['morning_start' => $morning_start, 'morning_end' => $morning_end, 'morning_late_threshold' => $morning_late_threshold, 'afternoon_start' => $afternoon_start, 'afternoon_end' => $afternoon_end, 'allow_mon' => $allow_days['allow_mon'], 'allow_tue' => $allow_days['allow_tue'], 'allow_wed' => $allow_days['allow_wed'], 'allow_thu' => $allow_days['allow_thu'], 'allow_fri' => $allow_days['allow_fri'], 'allow_sat' => $allow_days['allow_sat'], 'allow_sun' => $allow_days['allow_sun']]);

        if ($ok) {
            $message = '<div class="alert success"><i class="fas fa-check-circle"></i>&nbsp; Time settings updated successfully!</div>';
            $time_settings = getTimeSettings($pdo);
        } else {
            $message = '<div class="alert error"><i class="fas fa-times-circle"></i>&nbsp; Error updating time settings.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Time Settings</title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Material Symbols -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@400;500&display=swap">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <style>
        .container-fluid { width: 100%; padding: 0; margin: 0; }
        body {
            margin: 0;
            padding: 0;
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: #f6f8ff;
            color: #1a1a1a;
        }
        
        /* Card Style */
        .table-card {
            background: #fff;
            border-radius: 16px;
            padding: 1.5rem;
            width: min(100%, 1200px);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            margin: 40px auto; /* Added margin-top since header is gone */
        }

        /* Time Settings Specific Variables */
        :root {
            --primary-blue: #3b82f6; --light-blue: #dbeafe; --dark-blue: #1d4ed8; 
            --text-dark: #1f2937; --text-medium: #6b7280; --text-light: #9ca3af; 
            --success-green: #10b981; --error-red: #ef4444; --border-color: #e5e7eb;
            --input-bg: #fff;
        }

        /* Time Settings Header (Inside Card) */
        .settings-header {
            background: linear-gradient(135deg, var(--primary-blue), var(--dark-blue));
            color: #fff;
            padding: 25px 20px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        .settings-header h1 { font-size: 2em; font-weight: 700; margin: 0; }
        .settings-header .subtitle { font-size: 0.95em; opacity: 0.9; margin-top: 5px; }

        /* Scrollable Area */
        .scrollable-panel {
            max-height: 60vh;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 10px;
        }
        .scrollable-panel::-webkit-scrollbar { width: 8px; }
        .scrollable-panel::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .scrollable-panel::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }

        /* Alerts */
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 10px; font-weight: 500; display: flex; align-items: center; }
        .alert.success { background: #ecfdf5; color: var(--success-green); border-left: 4px solid var(--success-green); }
        .alert.error { background: #fef2f2; color: var(--error-red); border-left: 4px solid var(--error-red); }

        /* Action Bar */
        .action-bar { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 20px; }
        
        /* Current Configuration Card */
        .current-settings { background: #f8fafc; border-radius: 14px; padding: 20px; margin-bottom: 25px; border: 2px solid var(--border-color); }
        .current-settings h3 { color: var(--text-dark); font-size: 1.2em; font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        
        .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; }
        .setting-card { background: #fff; padding: 15px; border-radius: 10px; border: 1px solid var(--border-color); text-align: center; }
        .setting-icon { font-size: 1.5em; margin-bottom: 5px; }
        .setting-label { font-size: 0.75em; color: var(--text-light); text-transform: uppercase; font-weight: 600; }
        .setting-value { font-size: 1.1em; color: var(--text-dark); font-weight: 700; }

        /* Form Styling */
        .form-section { margin-bottom: 30px; }
        .section-header {
            display: flex; align-items: center; gap: 10px;
            margin-bottom: 20px; padding-bottom: 10px;
            border-bottom: 2px solid var(--border-color);
            position: sticky; top: 0; background: #fff; z-index: 10;
        }
        .section-title { font-size: 1.1em; font-weight: 600; color: var(--text-dark); }
        
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .form-group label { display: block; margin-bottom: 6px; font-weight: 600; font-size: 0.9em; }
        .input-wrapper { position: relative; }
        .input-wrapper input {
            width: 100%; padding: 10px 10px 10px 40px;
            border: 2px solid var(--border-color); border-radius: 8px;
            font-size: 0.95em;
        }
        .input-wrapper::before {
            content: "🕐"; position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%); color: var(--text-light); z-index: 1;
        }
        .input-wrapper input:focus { border-color: var(--primary-blue); outline: none; }
        .input-wrapper input.error { border-color: var(--error-red); }
        .help-text { color: var(--text-medium); font-size: 0.8em; margin-top: 5px; font-style: italic; }

        /* Days Grid */
        .days-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(80px, 1fr)); gap: 10px; }
        .day-pill {
            display: flex; align-items: center; gap: 8px;
            background: #fff; border: 1px solid var(--border-color);
            border-radius: 50px; padding: 8px 12px; justify-content: center;
            cursor: pointer; transition: all 0.2s;
        }
        .day-pill:hover { background: #f1f5f9; }

        /* Status Chip */
        .status-chip { display: inline-block; padding: 4px 10px; border-radius: 50px; font-size: 0.8em; font-weight: 700; }
        .status-on { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .status-off { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        @media (max-width: 768px) {
            .action-bar { flex-direction: column; }
            .action-bar .btn { width: 100%; }
        }
    </style>
</head>

<body>
<!-- NAV BAR INCLUDED HERE -->
<?php include 'nav.php'; ?>

<div class="container-fluid mt-4 mb-5">
    
    <!-- Main Content Card -->
    <div class="table-card">
        
        <!-- Inner Header with Gradient -->
        <div class="settings-header">
            <div style="font-size: 2em; margin-bottom: 5px;">⏰</div>
            <h1>Attendance Time Settings</h1>
            <div class="subtitle">Configure time windows and allowed school days</div>
            <div style="margin-top:10px;">
                <?php
                    $todayName = date('l');
                    $allowed = isAttendanceAllowedToday($time_settings);
                    $chipClass = $allowed ? 'status-on' : 'status-off';
                    $chipText = $allowed ? 'Attendance allowed today' : 'Attendance disabled today';
                    echo "<span class='status-chip $chipClass'>$chipText ($todayName)</span>";
                ?>
            </div>
        </div>

        <div class="content">
            <?php echo $message; ?>

            <!-- START SCROLLABLE CONTENT WRAPPER -->
            <div class="scrollable-panel">
                
                <!-- ACTION BAR -->
                <div class="action-bar">
                    <a href="admin_dashboard.php" class="btn btn-secondary text-white">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <button type="submit" form="settingsForm" name="update_settings" class="btn btn-primary" id="saveBtnTop">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>

                <!-- CURRENT SETTINGS DISPLAY -->
                <div class="current-settings">
                    <h3><i class="fas fa-cog"></i> Current Configuration</h3>
                    <div class="settings-grid">
                        <div class="setting-card">
                            <div class="setting-icon">🌅</div>
                            <div class="setting-label">Morning Start</div>
                            <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['morning_start'])); ?></div>
                        </div>
                        <div class="setting-card">
                            <div class="setting-icon">⏰</div>
                            <div class="setting-label">Late Threshold</div>
                            <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['morning_late_threshold'])); ?></div>
                        </div>
                        <div class="setting-card">
                            <div class="setting-icon">🌄</div>
                            <div class="setting-label">Morning End</div>
                            <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['morning_end'])); ?></div>
                        </div>
                        <div class="setting-card">
                            <div class="setting-icon">🌇</div>
                            <div class="setting-label">Afternoon Start</div>
                            <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['afternoon_start'])); ?></div>
                        </div>
                        <div class="setting-card">
                            <div class="setting-icon">🌃</div>
                            <div class="setting-label">Afternoon End</div>
                            <div class="setting-value"><?php echo date('h:i A', strtotime($time_settings['afternoon_end'])); ?></div>
                        </div>
                        <div class="setting-card">
                            <div class="setting-icon">📅</div>
                            <div class="setting-label">Allowed Days</div>
                            <div class="setting-value">
                                <?php
                                    $days = ['Mon'=>'allow_mon','Tue'=>'allow_tue','Wed'=>'allow_wed','Thu'=>'allow_thu','Fri'=>'allow_fri','Sat'=>'allow_sat','Sun'=>'allow_sun'];
                                    $out = [];
                                    foreach ($days as $label=>$col) {
                                        $out[] = $time_settings[$col] ? $label : "<span style='color:#ccc;text-decoration:line-through'>$label</span>";
                                    }
                                    echo implode(' · ', $out);
                                ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SETTINGS FORM -->
                <form action="" method="post" id="settingsForm">
                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-title">🌅 Morning Time In Settings</div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="morning_start">Start Time</label>
                                <div class="input-wrapper">
                                    <input type="text" id="morning_start" name="morning_start" placeholder="06:00" value="<?php echo substr($time_settings['morning_start'], 0, 5); ?>" required>
                                </div>
                                <div class="help-text">System starts accepting morning time-ins</div>
                            </div>
                            <div class="form-group">
                                <label for="morning_late_threshold">Late Threshold</label>
                                <div class="input-wrapper">
                                    <input type="text" id="morning_late_threshold" name="morning_late_threshold" placeholder="08:30" value="<?php echo substr($time_settings['morning_late_threshold'], 0, 5); ?>" required>
                                </div>
                                <div class="help-text">Time marked as "late"</div>
                            </div>
                            <div class="form-group">
                                <label for="morning_end">End Time</label>
                                <div class="input-wrapper">
                                    <input type="text" id="morning_end" name="morning_end" placeholder="09:00" value="<?php echo substr($time_settings['morning_end'], 0, 5); ?>" required>
                                </div>
                                <div class="help-text">System stops accepting morning time-ins</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-title">🌇 Afternoon Time Out Settings</div>
                        </div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="afternoon_start">Start Time</label>
                                <div class="input-wrapper">
                                    <input type="text" id="afternoon_start" name="afternoon_start" placeholder="16:00" value="<?php echo substr($time_settings['afternoon_start'], 0, 5); ?>" required>
                                </div>
                                <div class="help-text">System starts accepting afternoon time-outs</div>
                            </div>
                            <div class="form-group">
                                <label for="afternoon_end">End Time</label>
                                <div class="input-wrapper">
                                    <input type="text" id="afternoon_end" name="afternoon_end" placeholder="16:30" value="<?php echo substr($time_settings['afternoon_end'], 0, 5); ?>" required>
                                </div>
                                <div class="help-text">System stops accepting afternoon time-outs</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-header">
                            <div class="section-title">📅 School Days (attendance allowed)</div>
                        </div>
                        <div class="days-grid">
                            <?php
                            $dayFields = [
                                'Mon' => 'allow_mon', 'Tue' => 'allow_tue', 'Wed' => 'allow_wed',
                                'Thu' => 'allow_thu', 'Fri' => 'allow_fri', 'Sat' => 'allow_sat', 'Sun' => 'allow_sun'
                            ];
                            foreach ($dayFields as $label => $name): ?>
                                <label class="day-pill">
                                    <input type="checkbox" name="<?= $name ?>" <?= !empty($time_settings[$name]) ? 'checked' : '' ?>>
                                    <span><?= $label ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="help-text" style="margin-top:8px;">
                            Uncheck Saturday/Sunday to prevent attendance on weekends.
                        </div>
                    </div>
                </form>
            </div> 
            <!-- END SCROLLABLE CONTENT WRAPPER -->
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script>
    // Add loading state to form submission
    document.getElementById('settingsForm').addEventListener('submit', function() {
        const saveBtnTop = document.getElementById('saveBtnTop');
        const form = document.getElementById('settingsForm');
        
        // Show loading on button
        const loadingHtml = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        if(saveBtnTop) saveBtnTop.innerHTML = loadingHtml;
        
        form.classList.add('loading');
    });

    // Real-time validation for HH:MM
    const timeInputs = document.querySelectorAll('input[type="text"]');
    timeInputs.forEach(input => {
        input.addEventListener('input', function() {
            const timePattern = /^([01]?[0-9]|2[0-3]):[0-5][0-9]$/;
            if (this.value && !timePattern.test(this.value)) {
                this.classList.add('error');
            } else {
                this.classList.remove('error');
            }
        });
    });
</script>

</body>
</html>