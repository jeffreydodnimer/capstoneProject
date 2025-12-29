<?php
// FacAttRecord.php
session_start();
require 'conn.php';

// 1. Block unauthorized access
if (!isset($_SESSION['faculty_logged_in'])) {
    header("Location: faculty_login.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// 2. Get faculty info + handled section(s)
$sql = "
    SELECT 
        a.firstname,
        a.lastname,
        a.employee_id,
        a.photo,
        GROUP_CONCAT(CONCAT(s.grade_level, ' - ', s.section_name)
                     ORDER BY s.grade_level, s.section_name SEPARATOR ', ') AS handled_sections
    FROM faculty_login f
    INNER JOIN advisers a 
        ON a.employee_id = f.employee_id
    LEFT JOIN sections s
        ON s.employee_id = a.employee_id
    WHERE f.faculty_id = ?
    GROUP BY a.firstname, a.lastname, a.employee_id, a.photo
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['faculty_id']);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$faculty) {
    die("Faculty record not found.");
}

$handledSectionText = !empty($faculty['handled_sections'])
    ? $faculty['handled_sections']
    : 'No section assigned';

$employee_id = (string)$faculty['employee_id'];

date_default_timezone_set('Asia/Manila');

// --- Fetch Time Settings for Halfday/Absent Logic ---
$pdo = new PDO("mysql:host=localhost;dbname=rfid_capstone;charset=utf8", 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

try {
    $pdo->query("SELECT 1 FROM time_settings LIMIT 1");
} catch (PDOException $e) {
    // Create table if not exists (simplified for safety)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS time_settings (
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
        SELECT '06:00:00', '09:00:00', '08:30:00', '16:00:00', '16:30:00', 1, 1, 1, 1, 1, 0, 0
        WHERE NOT EXISTS (SELECT 1 FROM time_settings)
    ");
    $stmt->execute();
}

$stmt_settings = $pdo->query("SELECT morning_start, morning_end, morning_late_threshold, afternoon_end FROM time_settings WHERE id = 1");
$time_settings = $stmt_settings->fetch();

$morning_start_time_limit = $time_settings['morning_start'] ?? '06:00:00';
$morning_end_time_limit   = $time_settings['morning_end'] ?? '09:00:00';
$morning_late_threshold   = $time_settings['morning_late_threshold'] ?? '08:30:00';
$afternoon_end_time_limit = $time_settings['afternoon_end'] ?? '16:30:00';

$current_date = date('Y-m-d');
$current_time = date('H:i:s');

// Update absent records
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

// --- FETCH ATTENDANCE RECORDS FOR FACULTY'S STUDENTS ONLY ---
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
    INNER JOIN sections sec 
        ON e.grade_level = sec.grade_level 
        AND e.section_name = sec.section_name
    LEFT JOIN advisers adv ON sec.employee_id = adv.employee_id
    WHERE sec.employee_id = :employee_id
    ORDER BY a.date DESC, a.time_in DESC
");
$stmt->execute(['employee_id' => $employee_id]);
$attendanceRecords = $stmt->fetchAll();

// --- Process records to determine display status ---
foreach ($attendanceRecords as &$record) {
    if ($record['date'] === $current_date && $current_time <= $afternoon_end_time_limit && $record['time_out'] === null && $record['time_in'] !== null) {
        $time_in_only = date('H:i:s', strtotime($record['time_in']));
        if ($time_in_only >= $morning_start_time_limit && $time_in_only <= $morning_end_time_limit) {
            $record['display_status'] = 'halfday';
        } else {
            $record['display_status'] = $record['status'];
        }
    } else {
        $record['display_status'] = $record['status'];
    }

    if ($record['time_out'] !== null && $record['time_in'] !== null) {
        $time_in_only = date('H:i:s', strtotime($record['time_in']));
        if ($time_in_only > $morning_late_threshold) {
            $record['display_status'] = 'late';
        } else {
            $record['display_status'] = 'present';
        }
    }
}
unset($record);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Attendance Records - Faculty Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        #accordionSidebar { background-color: #800000 !important; }
        .btn-maroon { background-color: #800000; color: white; }
        
        .img-profile-custom {
            width: 40px; 
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #e3e6f0;
        }
        @media (max-width: 576px) {
            .img-profile-custom { width: 35px; height: 35px; }
        }
        .text-custom-gray {
            color: #5a5c69 !important;
            font-size: 0.9rem;
            font-weight: 400;
        }
        
        /* UPDATED LOGOUT LINK STYLE */
        .logout-link {
            color: #858796; /* Default gray */
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s;
        }
        .logout-link:hover {
            color: #800000; /* Turns Maroon on Hover */
            text-decoration: none;
        }

        .topbar-divider-custom {
            width: 0;
            border-right: 1px solid #e3e6f0;
            height: 2.5rem;
            margin: auto 1.5rem;
            display: block;
        }
        .handled-pill{
            display:inline-block;
            padding:.35rem .6rem;
            border-radius:999px;
            background:#f8f9fc;
            border:1px solid #e3e6f0;
            color:#5a5c69;
            font-size:.9rem;
        }
        .table-card {
            background: #fff;
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0,0,0,.08);
        }
        .status-present { color: #28a745; font-weight: bold; }
        .status-absent { color: #dc3545; font-weight: bold; }
        .status-late { color: #ffc107; font-weight: bold; }
        .status-halfday { color: #3b82f6; font-weight: bold; }

        /* Maroon Badge for Total Records */
        .badge-maroon {
            background-color: #800000;
            color: white;
            font-size: 1rem;
        }

        /* Search Bar Styling */
        .search-container {
            position: relative;
            max-width: 300px;
        }
        .search-container input {
            border-radius: 20px;
            padding-left: 35px;
            border: 1px solid #d1d3e2;
        }
        .search-container i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #d1d3e2;
        }

        /* Social Media Styles */
        .sidebar-social-heading {
            color: rgba(255, 255, 255, 0.4);
            font-weight: 800;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .05rem;
            padding: 0 1rem;
            margin-top: 1rem;
        }
        .nav-social-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8) !important;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .nav-social-link:hover {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.1);
        }
        .nav-social-link i {
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
        }
    </style>
</head>

<body id="page-top">
<div id="wrapper">
    <!-- Sidebar -->
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
        <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center"
           href="faculty_dashboard.php" style="padding: 1.5rem 1rem; height: auto;">
            <img src="img/logo.jpg" alt="Logo" class="rounded-circle mb-2"
                 style="width: 70px; height: 70px; object-fit: cover;">
            <div class="sidebar-brand-text">FACULTY</div>
        </a>

        <hr class="sidebar-divider my-0">
        <li class="nav-item">
            <a class="nav-link" href="faculty_dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
        </li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">My Class</div>

        <li class="nav-item">
            <a class="nav-link" href="adviserHandle.php">
                <i class="fas fa-users"></i><span>My Students</span>
            </a>
        </li>

        <li class="nav-item active">
            <a class="nav-link" href="FacAttRecord.php">
                <i class="fas fa-calendar-check"></i><span>Attendance Records</span>
            </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="section_student.php">
                <i class="fas fa-file-export"></i><span>Generate SF2</span>
            </a>
        </li>

        <hr class="sidebar-divider">
        <div class="sidebar-social-heading">Social Media</div>
        <li class="nav-item">
            <a class="nav-social-link" href="https://web.facebook.com/DepEdTayoSINHS301394.official/?_rdc=1&_rdr#" target="_blank">
                <i class="fab fa-facebook-f"></i><span>Facebook</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-social-link" href="https://www.youtube.com/channel/UCDw3mhzSTm_NFk_2dFbhBKg" target="_blank">
                <i class="fab fa-youtube"></i><span>YouTube</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-social-link" href="https://ph.search.yahoo.com/search" target="_blank">
                <i class="fab fa-google"></i><span>Google</span>
            </a>
        </li>
        <hr class="sidebar-divider d-none d-md-block">
    </ul>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                    <i class="fa fa-bars"></i>
                </button>

                <ul class="navbar-nav ml-auto align-items-center">
                    <div class="topbar-divider-custom d-none d-sm-block"></div>

                    <?php
                    $photoName = basename((string)($faculty['photo'] ?? ''));
                    $photoDiskPath = __DIR__ . '/uploads/advisers/' . $photoName;
                    $profilePhoto = (!empty($photoName) && is_file($photoDiskPath))
                        ? 'uploads/advisers/' . rawurlencode($photoName)
                        : 'img/profile.svg';
                    ?>
                    <li class="nav-item d-flex align-items-center">
                        <span class="mr-2 d-none d-lg-inline text-custom-gray">
                            Teacher <?= htmlspecialchars($faculty['firstname'] . ' ' . $faculty['lastname']) ?>
                        </span>
                        <img class="img-profile-custom"
                             src="<?= htmlspecialchars($profilePhoto) ?>"
                             alt="Profile"
                             onerror="this.onerror=null;this.src='img/profile.svg';">
                    </li>

                    <!-- UPDATED LOGOUT BUTTON -->
                    <li class="nav-item ml-3">
                        <a class="logout-link" href="#" data-toggle="modal" data-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid">
                <!-- PAGE HEADER -->
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-3">
                    <div>
                        <h1 class="h3 mb-1 text-gray-800">
                            <i class="fas fa-clipboard-list"></i> Attendance Records
                        </h1>
                        <div class="mb-2">
                            <span class="handled-pill">
                                Sections Handled:
                                <strong><?= htmlspecialchars($handledSectionText) ?></strong>
                            </span>
                        </div>
                    </div>

                    <!-- SEARCH BAR AND TOTAL BADGE -->
                    <div class="d-flex align-items-center mt-2 mt-md-0">
                        <div class="search-container mr-3">
                            <i class="fas fa-search"></i>
                            <input type="text" id="attendanceSearch" class="form-control form-control-sm" placeholder="Search records...">
                        </div>
                        <span class="badge badge-maroon p-2">
                            Total Records: <?= count($attendanceRecords) ?>
                        </span>
                    </div>
                </div>

                <!-- ATTENDANCE TABLE -->
                <div class="table-card">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle" id="attendanceTable">
                            <thead class="table-light text-uppercase small">
                                <tr>
                                    <th>#</th>
                                    <th>Date</th>
                                    <th>Student Name</th>
                                    <th>Grade Level</th>
                                    <th>Section</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($attendanceRecords)): ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 20px;">
                                            No attendance records found for your sections.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $i = 1;
                                    foreach ($attendanceRecords as $record): 
                                    ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td><?= htmlspecialchars(date('M j, Y', strtotime($record['date']))) ?></td>
                                            <td><?= htmlspecialchars($record['firstname'] . ' ' . $record['lastname']) ?></td>
                                            <td><?= htmlspecialchars($record['grade_level'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($record['section_name'] ?? 'N/A') ?></td>
                                            <td><?= $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : 'N/A' ?></td>
                                            <td><?= $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : 'N/A' ?></td>
                                            <td class="status-<?= strtolower($record['display_status']) ?>">
                                                <?= htmlspecialchars(ucfirst($record['display_status'])) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="logoutModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoutModalLabel">Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger" href="faculty_logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- SEARCH SCRIPT -->
<script>
    $(document).ready(function(){
        $("#attendanceSearch").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#attendanceTable tbody tr").filter(function() {
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
    });
</script>

</body>
</html>
<?php include 'footer.php'; ?>