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
        GROUP_CONCAT(DISTINCT CONCAT(s.grade_level, ' - ', s.section_name)
                     ORDER BY s.grade_level, s.section_name SEPARATOR ', ') AS handled_sections
    FROM faculty_login f
    INNER JOIN advisers a 
        ON a.employee_id = f.employee_id
    LEFT JOIN sections s
        ON s.employee_id = a.employee_id
    WHERE f.faculty_id = ?
    GROUP BY a.employee_id
    LIMIT 1
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['faculty_id']);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$faculty) {
    // A fallback in case the faculty record is not found
    $_SESSION = [];
    session_destroy();
    header("Location: faculty_login.php?error=notfound");
    exit();
}

$handledSectionText = !empty($faculty['handled_sections'])
    ? $faculty['handled_sections']
    : 'No section assigned';

$employee_id = (string)$faculty['employee_id'];

date_default_timezone_set('Asia/Manila');

// --- Fetch Time Settings & Update Absences using PDO ---
try {
    $pdo = new PDO("mysql:host=localhost;dbname=rfid_capstone;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Fetch time settings
    $stmt_settings = $pdo->query("SELECT morning_start, morning_end, morning_late_threshold, afternoon_end FROM time_settings LIMIT 1");
    $time_settings = $stmt_settings->fetch();

    $morning_late_threshold   = $time_settings['morning_late_threshold'] ?? '08:30:00';
    $afternoon_end_time_limit = $time_settings['afternoon_end'] ?? '16:30:00';

    $current_date = date('Y-m-d');
    $current_time = date('H:i:s');

    // Update students who didn't time out to 'absent'
    $stmt_update_absent = $pdo->prepare("
        UPDATE attendance
        SET status = 'absent'
        WHERE status IS NULL 
          AND time_out IS NULL
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
    $stmt = $pdo->prepare("
        SELECT 
            a.date, a.time_in, a.time_out, a.status,
            s.firstname, s.lastname, 
            e.grade_level, e.section_name
        FROM attendance a 
        INNER JOIN students s ON a.lrn = s.lrn 
        INNER JOIN enrollments e ON a.lrn = e.lrn
        INNER JOIN sections sec ON e.grade_level = sec.grade_level AND e.section_name = sec.section_name
        WHERE sec.employee_id = :employee_id
        ORDER BY a.date DESC, s.lastname ASC, s.firstname ASC
    ");
    $stmt->execute(['employee_id' => $employee_id]);
    $attendanceRecords = $stmt->fetchAll();

    // Process records to determine the correct display status
    foreach ($attendanceRecords as &$record) {
        $status = $record['status'];
        $time_in = $record['time_in'] ? date('H:i:s', strtotime($record['time_in'])) : null;
        
        if ($status === 'present' && $time_in && $time_in > $morning_late_threshold) {
            $record['display_status'] = 'late';
        } elseif ($status === 'halfday') {
            $record['display_status'] = 'halfday';
        } elseif ($status === 'absent') {
            $record['display_status'] = 'absent';
        } else {
            // Default to present if status is set and not late/halfday
            $record['display_status'] = $status ? 'present' : 'pending'; 
        }
    }
    unset($record);

} catch (PDOException $e) {
    // Handle DB connection errors gracefully
    $attendanceRecords = [];
    error_log("DB Error in FacAttRecord.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Attendance Records - Faculty Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        #accordionSidebar { background-color: #800000 !important; }
        .btn-maroon { background-color: #800000; color: white; border:none; }
        .btn-maroon:hover { background-color: #600000; color:white; }
        .img-profile-custom { width: 32px; height: 32px; object-fit: cover; border-radius: 50%; }
        .faculty-name-top { color: #5a5c69; font-size: 0.85rem; margin-right: 10px; }
        .logout-link { color: #858796; font-size: 0.9rem; text-decoration: none; cursor: pointer; transition: color 0.2s; }
        .logout-link:hover { color: #800000; text-decoration: none; }
        .topbar-divider { border-right: 1px solid #e3e6f0; height: 2.3rem; margin: auto 1rem; }
        .handled-pill { display: inline-block; padding: .35rem .6rem; border-radius: 999px; background: #f8f9fc; border: 1px solid #e3e6f0; color: #5a5c69; font-size: .8rem; }
        .table-card { background: #fff; border-radius: 8px; padding: 1.25rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.05); border: 1px solid #e3e6f0; }
        .status-present { color: #1cc88a; font-weight: bold; }
        .status-absent { color: #e74a3b; font-weight: bold; }
        .status-late { color: #f6c23e; font-weight: bold; }
        .status-halfday { color: #36b9cc; font-weight: bold; }
        .badge-maroon { background-color: #800000; color: white; }
        .search-container { position: relative; max-width: 300px; }
        .search-container input { border-radius: 20px; padding-left: 35px; border: 1px solid #d1d3e2; }
        .search-container i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #d1d3e2; }
        .sidebar-social-heading { color: rgba(255, 255, 255, 0.4); font-weight: 800; font-size: .65rem; text-transform: uppercase; letter-spacing: .05rem; padding: 0 1rem; margin-top: 1rem; }
        .nav-social-link { display: flex; align-items: center; padding: 0.75rem 1rem; color: rgba(255, 255, 255, 0.8) !important; text-decoration: none; font-weight: 600; transition: 0.3s; }
        .nav-social-link:hover { color: #fff !important; background: rgba(255, 255, 255, 0.1); }
        .nav-social-link i { margin-right: 0.75rem; width: 1.25rem; text-align: center; }
        
        /* MODIFICATION: Styles for the scrollable table with a sticky header */
        .table-responsive-scrollable {
            max-height: 65vh;
            overflow-y: auto;
        }
        .table-responsive-scrollable thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background-color: #f8f9fc !important; /* Overrides other styles to ensure a solid background */
            border-bottom: 2px solid #e3e6f0; /* Match table header style */
        }
        .table-bordered th, .table-bordered td { vertical-align: middle; }
    </style>
</head>

<body id="page-top">
<div id="wrapper">
    <!-- Sidebar -->
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
        <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center" href="faculty_dashboard.php" style="padding: 1.5rem 1rem; height: auto;">
            <img src="img/logo.jpg" alt="Logo" class="rounded-circle mb-2" style="width: 70px; height: 70px; object-fit: cover;">
            <div class="sidebar-brand-text">FACULTY</div>
        </a>
        <hr class="sidebar-divider my-0">
        <li class="nav-item"><a class="nav-link" href="faculty_dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">My Class</div>
        <!-- MODIFICATION: Updated link to my_students.php -->
        <li class="nav-item"><a class="nav-link" href="adviserHandle.php"><i class="fas fa-users"></i><span>My Students</span></a></li>
        <li class="nav-item active"><a class="nav-link" href="FacAttRecord.php"><i class="fas fa-calendar-check"></i><span>Attendance Records</span></a></li>
        <!-- MODIFICATION: Updated link to generate_sf2.php -->
        <li class="nav-item"><a class="nav-link" href="generate_sf2.php"><i class="fas fa-file-export"></i><span>Generate SF2</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-social-heading">Social Media</div>
        <li class="nav-item"><a class="nav-social-link" href="https://web.facebook.com/DepEdTayoSINHS301394.official/?_rdc=1&_rdr#" target="_blank"><i class="fab fa-facebook-f"></i><span>Facebook</span></a></li>
        <li class="nav-item"><a class="nav-social-link" href="https://www.youtube.com/channel/UCDw3mhzSTm_NFk_2dFbhBKg" target="_blank"><i class="fab fa-youtube"></i><span>YouTube</span></a></li>
        <li class="nav-item"><a class="nav-social-link" href="https://ph.search.yahoo.com/search" target="_blank"><i class="fab fa-google"></i><span>Google</span></a></li>
        <hr class="sidebar-divider d-none d-md-block">
    </ul>

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
                <ul class="navbar-nav ml-auto align-items-center">
                    <div class="topbar-divider d-none d-sm-block"></div>
                    <?php
                    $facultyPhotoPath = 'uploads/advisers/' . ($faculty['photo'] ?? 'profile.svg');
                    if (!file_exists($facultyPhotoPath) || empty($faculty['photo'])) {
                        $facultyPhotoPath = 'img/profile.svg';
                    }
                    ?>
                    <li class="nav-item d-flex align-items-center">
                        <span class="faculty-name-top">Teacher <?= htmlspecialchars($faculty['firstname'] . ' ' . $faculty['lastname']) ?></span>
                        <img class="img-profile-custom" src="<?= $facultyPhotoPath ?>" onerror="this.src='img/profile.svg'">
                    </li>
                    <li class="nav-item ml-3">
                        <a class="logout-link" href="#" data-toggle="modal" data-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2"></i>Logout
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid">
                <!-- PAGE HEADER -->
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                    <div>
                        <h1 class="h3 mb-1 text-gray-800"><i class="fas fa-clipboard-list"></i> Attendance Records</h1>
                        <span class="handled-pill">Sections: <strong><?= htmlspecialchars($handledSectionText) ?></strong></span>
                    </div>
                    <div class="d-flex align-items-center mt-2 mt-md-0">
                        <div class="search-container mr-3">
                            <i class="fas fa-search"></i>
                            <input type="text" id="attendanceSearch" class="form-control form-control-sm" placeholder="Search records...">
                        </div>
                        <span class="badge badge-maroon p-2">Total Records: <?= count($attendanceRecords) ?></span>
                    </div>
                </div>

                <!-- ATTENDANCE TABLE -->
                <div class="table-card">
                    <!-- MODIFICATION: Added 'table-responsive-scrollable' class -->
                    <div class="table-responsive-scrollable">
                        <table class="table table-bordered table-hover" id="attendanceTable">
                            <thead>
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
                                    <!-- MODIFICATION: Improved 'no records' message -->
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-5">
                                            <i class="fas fa-calendar-times fa-3x" style="opacity: 0.5;"></i>
                                            <p class="mt-3">No attendance records found for your sections.</p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php $i = 1; foreach ($attendanceRecords as $record): ?>
                                        <tr>
                                            <td><?= $i++ ?></td>
                                            <td><?= htmlspecialchars(date('M j, Y', strtotime($record['date']))) ?></td>
                                            <td><?= htmlspecialchars(ucwords(strtolower($record['lastname'] . ', ' . $record['firstname']))) ?></td>
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
<div class="modal fade" id="logoutModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal"><span>&times;</span></button>
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
<script src="js/sb-admin-2.min.js"></script>
<script>
    $(document).ready(function(){
        $("#attendanceSearch").on("keyup", function() {
            var value = $(this).val().toLowerCase();
            $("#attendanceTable tbody tr").filter(function() {
                // Hide or show the row based on whether it contains the search value
                $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
            });
        });
    });
</script>
<?php include 'footer.php'; ?>
</body>
</html>