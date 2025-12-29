<?php
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

date_default_timezone_set('Asia/Manila');

// --- Fetch Time Settings for Halfday/Absent Logic ---
// Ensure time_settings table exists (minimal check)
try {
    $pdo->query("SELECT 1 FROM time_settings LIMIT 1");
} catch (PDOException $e) {
    // Table doesn't exist, create it with default values
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

// Get the actual time settings for morning and afternoon session boundaries
$stmt_settings = $pdo->query("SELECT morning_start, morning_end, morning_late_threshold, afternoon_end FROM time_settings WHERE id = 1");
$time_settings = $stmt_settings->fetch();

// Set default values if no settings found (shouldn't happen if table is created)
$morning_start_time_limit = $time_settings['morning_start'] ?? '06:00:00';
$morning_end_time_limit   = $time_settings['morning_end'] ?? '09:00:00';
$morning_late_threshold   = $time_settings['morning_late_threshold'] ?? '08:30:00';
$afternoon_end_time_limit = $time_settings['afternoon_end'] ?? '16:30:00';

// --- Automated Update for Past Records and Today's Records if Past Afternoon End ---
// Get current date and time for comparison
$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Update all records (past dates and today if past afternoon end) where time_out is NULL to 'absent'
$stmt_update_absent = $pdo->prepare("
    UPDATE attendance
    SET status = 'absent'
    WHERE time_out IS NULL
      AND (
          date < :current_date
          OR (date = :current_date AND :current_time > :afternoon_end)
      )
");
$stmt_update_absent->execute([
    'current_date' => $current_date,
    'current_time' => $current_time,
    'afternoon_end' => $afternoon_end_time_limit
]);


// --- CORRECTED QUERY BLOCK ---
// The original query caused a "Column not found" error for 'e.section_id'.
// The fix involves correcting three joins:
// 1. Join `attendance` to `enrollments` using `lrn` (not `enrollment_id`).
// 2. Join `enrollments` to `sections` using `grade_level` and `section_name` (not `section_id`).
// 3. Join `sections` to `advisers` using `employee_id` (based on your other files).
$attendanceRecords = [];
$stmt = $pdo->prepare("
    SELECT 
        a.date,
        a.time_in,
        a.time_out,
        a.status,
        s.firstname, 
        s.lastname, 
        e.grade_level, 
        e.section_name,
        CONCAT_WS(' ', adv.firstname, adv.lastname) AS adviser_name
    FROM attendance a 
    INNER JOIN students s ON a.lrn = s.lrn 
    INNER JOIN enrollments e ON a.lrn = e.lrn
    LEFT JOIN sections sec ON e.grade_level = sec.grade_level AND e.section_name = sec.section_name
    LEFT JOIN advisers adv ON sec.employee_id = adv.employee_id
    ORDER BY a.date DESC, a.time_in DESC
");
$stmt->execute();
$attendanceRecords = $stmt->fetchAll();


// --- Process records to determine display status (for current day pending records) ---
foreach ($attendanceRecords as &$record) {
    // For display purposes, override status for today's pending records (before afternoon end)
    if ($record['date'] === $current_date && $current_time <= $afternoon_end_time_limit && $record['time_out'] === null && $record['time_in'] !== null) {
        $time_in_only = date('H:i:s', strtotime($record['time_in']));
        if ($time_in_only >= $morning_start_time_limit && $time_in_only <= $morning_end_time_limit) {
            // For today's records before afternoon end, show 'halfday' if timed in during morning but no time out yet
            $record['display_status'] = 'halfday';
        } else {
            $record['display_status'] = $record['status']; // Use DB status (likely 'absent' or other)
        }
    } else {
        // For all other records, use the DB status (which may have been updated to 'absent')
        $record['display_status'] = $record['status'];
    }

    // Additionally, for records with time_out, ensure status is 'present' or 'late' based on time_in
    if ($record['time_out'] !== null && $record['time_in'] !== null) {
        $time_in_only = date('H:i:s', strtotime($record['time_in']));
        if ($time_in_only > $morning_late_threshold) {
            $record['display_status'] = 'late';
        } else {
            $record['display_status'] = 'present';
        }
    }
}
unset($record); // Break the reference with the last element

// Convert records to JSON for JavaScript search
$jsonRecords = json_encode($attendanceRecords);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-image: url('img/pi.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            min-height: 100vh;
        }
        .container {
            background-color: rgba(255, 255, 255, 0.75); /* Less opaque for more transparency */
            padding: 30px;
            width: 100%;
            min-height: 100vh;
            box-sizing: border-box;
            margin: 0;
        }
        h1 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        
        /* Search Bar Styles */
        .search-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        .search-box {
            position: relative;
            width: 100%;
            max-width: 400px;
        }
        .search-input {
            width: 100%;
            padding: 12px 50px 12px 15px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 25px;
            outline: none;
            transition: all 0.3s ease;
            background-color: white;
        }
        .search-input:focus {
            border-color: #007bff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .search-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        .table-container {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1rem;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            color: #495057;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e2f2ff;
        }
        .status-present { color: #28a745; font-weight: bold; }
        .status-absent { color: #dc3545; font-weight: bold; }
        .status-late { color: #ffc107; font-weight: bold; }
        .status-halfday { color: #3b82f6; font-weight: bold; }
        .back-link-container {
            text-align: center;
            margin-bottom: 20px;
        }
        .back-link {
            display: inline-block;
            background-color: #007bff;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 5px;
            font-weight: bold;
        }
        .back-link:hover {
            background-color: #0056b3;
        }
        .record-count {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1rem;
            color: #555;
            background-color: rgba(255,255,255,0.9);
            padding: 10px;
            border-radius: 5px;
            display: inline-block;
            margin: 0 auto;
        }
        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .search-box {
                max-width: 100%;
            }
            th, td {
                padding: 8px 4px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-clipboard-list"></i> Attendance Records</h1>
        
        <!-- Search Section -->
        <div class="search-container">
            <div class="search-box">
                <input 
                    type="text" 
                    id="searchInput" 
                    class="search-input" 
                    placeholder="Search students, sections, advisers..."
                    autocomplete="off"
                >
                <i class="fas fa-search search-icon"></i>
            </div>
        </div>

        <div class="record-count" id="recordCount">
            <strong>Total Records: <?= count($attendanceRecords) ?></strong>
        </div>
        
        <div class="back-link-container">
            <a href="admin_dashboard.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
        
        <div class="table-container">
            <table id="attendanceTable">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Student Name</th>
                        <th>Grade Level</th>
                        <th>Section</th>
                        <th>Adviser</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attendanceRecords)): ?>
                        <tr>
                            <td colspan="8" class="no-results">
                                No attendance records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attendanceRecords as $record): ?>
                            <tr>
                                <td data-date="<?= htmlspecialchars($record['date']) ?>">
                                    <?= htmlspecialchars(date('M j, Y', strtotime($record['date']))) ?>
                                </td>
                                <td data-student="<?= htmlspecialchars(strtolower($record['firstname'] . ' ' . $record['lastname'])) ?>">
                                    <?= htmlspecialchars($record['firstname'] . ' ' . $record['lastname']) ?>
                                </td>
                                <td data-grade="<?= htmlspecialchars(strtolower($record['grade_level'] ?? '')) ?>">
                                    <?= htmlspecialchars($record['grade_level'] ?? 'N/A') ?>
                                </td>
                                <td data-section="<?= htmlspecialchars(strtolower($record['section_name'] ?? '')) ?>">
                                    <?= htmlspecialchars($record['section_name'] ?? 'N/A') ?>
                                </td>
                                <td data-adviser="<?= htmlspecialchars(strtolower($record['adviser_name'] ?? '')) ?>">
                                    <?= htmlspecialchars($record['adviser_name'] ?? 'N/A') ?>
                                </td>
                                <td data-timein="<?= htmlspecialchars($record['time_in']) ?>">
                                    <?= $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : 'N/A' ?>
                                </td>
                                <td data-timeout="<?= htmlspecialchars($record['time_out']) ?>">
                                    <?= $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : 'N/A' ?>
                                </td>
                                <td data-status="<?= htmlspecialchars(strtolower($record['display_status'])) ?>" 
                                    class="status-<?= strtolower($record['display_status']) ?>">
                                    <?= htmlspecialchars(ucfirst($record['display_status'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div id="noResults" class="no-results" style="display: none;">
                <i class="fas fa-search"></i><br>
                No records found matching your search criteria.
            </div>
        </div>
    </div>

    <script>
        // Store all records for searching
        const allRecords = <?= $jsonRecords ?>;
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.querySelector('#attendanceTable tbody');
        const recordCount = document.getElementById('recordCount');
        const noResults = document.getElementById('noResults');

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric', 
                year: 'numeric' 
            });
        }

        function normalizeStatus(status) {
            return (status || '').toLowerCase();
        }

        function clearTable() {
            tableBody.innerHTML = '';
        }

        function displayRecords(records) {
            clearTable();
            
            if (records.length === 0) {
                noResults.style.display = 'block';
                document.querySelector('.table-container table').style.display = 'none';
            } else {
                noResults.style.display = 'none';
                document.querySelector('.table-container table').style.display = 'table';
                
                records.forEach(record => {
                    const row = document.createElement('tr');
                    
                    const displayTimeIn = record.time_in ? 
                        new Date('1970-01-01T' + record.time_in + 'Z').toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        }) : 'N/A';
                    
                    const displayTimeOut = record.time_out ? 
                        new Date('1970-01-01T' + record.time_out + 'Z').toLocaleTimeString('en-US', {
                            hour: 'numeric',
                            minute: '2-digit',
                            hour12: true
                        }) : 'N/A';

                    row.innerHTML = `
                        <td data-date="${record.date}">
                            ${formatDate(record.date)}
                        </td>
                        <td data-student="${(record.firstname + ' ' + record.lastname).toLowerCase()}">
                            ${record.firstname} ${record.lastname}
                        </td>
                        <td data-grade="${(record.grade_level || '').toLowerCase()}">
                            ${record.grade_level || 'N/A'}
                        </td>
                        <td data-section="${(record.section_name || '').toLowerCase()}">
                            ${record.section_name || 'N/A'}
                        </td>
                        <td data-adviser="${(record.adviser_name || '').toLowerCase()}">
                            ${record.adviser_name || 'N/A'}
                        </td>
                        <td data-timein="${record.time_in}">
                            ${displayTimeIn}
                        </td>
                        <td data-timeout="${record.time_out}">
                            ${displayTimeOut}
                        </td>
                        <td data-status="${normalizeStatus(record.display_status)}" 
                            class="status-${normalizeStatus(record.display_status)}">
                            ${record.display_status ? record.display_status.charAt(0).toUpperCase() + record.display_status.slice(1) : 'N/A'}
                        </td>
                    `;
                    
                    tableBody.appendChild(row);
                });
            }
            
            // Update record count
            recordCount.innerHTML = `<strong>Total Records: ${records.length}</strong>`;
        }

        function filterRecords() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            
            // If search bar is empty, show all records
            if (!searchTerm) {
                displayRecords(allRecords);
                return;
            }
            
            const filteredRecords = allRecords.filter(record => {
                return (record.firstname + ' ' + record.lastname).toLowerCase().includes(searchTerm) ||
                       (record.grade_level || '').toLowerCase().includes(searchTerm) ||
                       (record.section_name || '').toLowerCase().includes(searchTerm) ||
                       (record.adviser_name || '').toLowerCase().includes(searchTerm) ||
                       (record.date || '').toLowerCase().includes(searchTerm) ||
                       (record.display_status || '').toLowerCase().includes(searchTerm);
            });
            
            displayRecords(filteredRecords);
        }

        // Event listener for search input
        searchInput.addEventListener('input', filterRecords);

        // Allow ESC key to clear search
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                searchInput.value = '';
                displayRecords(allRecords);
                searchInput.blur();
            }
        });

        // Display all records initially
        displayRecords(allRecords);
    </script>
</body>
</html>