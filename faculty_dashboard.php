<?php
// faculty_dashboard.php
session_start();
require 'conn.php';

if (!isset($_SESSION['faculty_logged_in'])) {
    header("Location: faculty_login.php");
    exit();
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

// Get faculty info + handled sections
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

// AJAX endpoint for real-time stats
if (isset($_GET['action']) && $_GET['action'] === 'get_faculty_stats') {
    header('Content-Type: application/json');

    date_default_timezone_set('Asia/Manila');
    $current_date = date('Y-m-d');
    $emp_id       = (string)$faculty['employee_id'];

    // Total students under this faculty's sections
    $stmt1 = $conn->prepare("
        SELECT COUNT(DISTINCT e.lrn) AS total
        FROM enrollments e
        INNER JOIN sections s
            ON s.section_name = e.section_name
           AND s.grade_level  = e.grade_level
        WHERE s.employee_id = ?
    ");
    $stmt1->bind_param("s", $emp_id);
    $stmt1->execute();
    $totalStudents = (int)($stmt1->get_result()->fetch_assoc()['total'] ?? 0);
    $stmt1->close();

    // Students who COMPLETED attendance today
    $stmt2 = $conn->prepare("
        SELECT COUNT(DISTINCT att.lrn) AS completed
        FROM attendance att
        INNER JOIN enrollments e ON e.lrn = att.lrn
        INNER JOIN sections s
            ON s.section_name = e.section_name
           AND s.grade_level  = e.grade_level
        WHERE att.date = ?
          AND att.status IN ('present', 'late')
          AND s.employee_id = ?
          AND att.time_in  IS NOT NULL
          AND att.time_out IS NOT NULL
    ");
    $stmt2->bind_param("ss", $current_date, $emp_id);
    $stmt2->execute();
    $presentToday = (int)($stmt2->get_result()->fetch_assoc()['completed'] ?? 0);
    $stmt2->close();

    $absentToday    = max(0, $totalStudents - $presentToday);
    $attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
    $absenceRate    = 100 - $attendanceRate;

    // Weekly chart data
    $weekdays   = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $chartData  = array_fill_keys($weekdays, 0);

    $stmt3 = $conn->prepare("
        SELECT DAYNAME(att.date) AS day_name,
               COUNT(DISTINCT att.lrn) AS completed
        FROM attendance att
        INNER JOIN enrollments e ON e.lrn = att.lrn
        INNER JOIN sections s
            ON s.section_name = e.section_name
           AND s.grade_level  = e.grade_level
        WHERE YEARWEEK(att.date, 1) = YEARWEEK(CURDATE(), 1)
          AND att.status IN ('present', 'late')
          AND s.employee_id = ?
          AND att.time_in  IS NOT NULL
          AND att.time_out IS NOT NULL
        GROUP BY day_name
    ");
    $stmt3->bind_param("s", $emp_id);
    $stmt3->execute();
    $res = $stmt3->get_result();
    while ($row = $res->fetch_assoc()) {
        $day = $row['day_name'];
        if (isset($chartData[$day])) {
            $completed = (int)$row['completed'];
            $chartData[$day] = $totalStudents > 0
                ? round(($completed / $totalStudents) * 100, 1)
                : 0;
        }
    }
    $stmt3->close();

    echo json_encode([
        'totalStudents'   => number_format($totalStudents),
        'presentToday'    => number_format($presentToday),
        'absentToday'     => number_format($absentToday),
        'attendanceRate'  => $attendanceRate,
        'absenceRate'     => $absenceRate,
        'percentages'     => array_values($chartData),
        'handledSections' => $handledSectionText,
    ]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Faculty Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <style>
        #accordionSidebar { background-color: #800000 !important; }
        .img-profile-custom {
            width: 40px; height: 40px; object-fit: cover;
            border-radius: 50%; background:#fff; border:1px solid #e3e6f0;
        }
        .text-custom-gray { color:#5a5c69 !important; font-size:.9rem; font-weight:400; }
        
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

        .handled-pill{
            display:inline-block;padding:.35rem .6rem;border-radius:999px;
            background:#f8f9fc;border:1px solid #e3e6f0;color:#5a5c69;font-size:.9rem;
        }
        /* Icon Color Overrides from Screenshot */
        .icon-total { color: #e74a3b !important; } /* Red/Orange Hat */
        .icon-present { color: #2ecc71 !important; } /* Green User Check */
        .icon-absent { color: #f1c40f !important; } /* Yellow User X */

        /* Social Media Section Styling */
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
           href="faculty_dashboard.php" style="padding:1.5rem 1rem;height:auto;">
            <img src="img/logo.jpg" alt="Logo" class="rounded-circle mb-2" style="width:70px;height:70px;object-fit:cover;">
            <div class="sidebar-brand-text">FACULTY</div>
        </a>
        <hr class="sidebar-divider my-0">
        <li class="nav-item active"><a class="nav-link" href="faculty_dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
        
        <hr class="sidebar-divider">
        <div class="sidebar-heading">My Class</div>
        <li class="nav-item"><a class="nav-link" href="adviserHandle.php"><i class="fas fa-users"></i><span>My Students</span></a></li>
        <li class="nav-item"><a class="nav-link" href="FacAttRecord.php"><i class="fas fa-calendar-check"></i><span>Attendance Records</span></a></li>
        <li class="nav-item"><a class="nav-link" href="faculty_calendar.php"><i class="fas fa-calendar"></i><span>Student Calendar</span></a></li>

        <hr class="sidebar-divider">
        <!-- NEW SOCIAL MEDIA SECTION -->
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
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
                <ul class="navbar-nav ml-auto align-items-center">
                    <?php
                    $photoName = basename((string)($faculty['photo'] ?? ''));
                    $profilePhoto = (!empty($photoName) && is_file(__DIR__ . '/uploads/advisers/' . $photoName))
                        ? 'uploads/advisers/' . rawurlencode($photoName) : 'img/profile.svg';
                    ?>
                    <li class="nav-item d-flex align-items-center">
                        <span class="mr-2 d-none d-lg-inline text-custom-gray">Teacher <?= htmlspecialchars($faculty['firstname'].' '.$faculty['lastname']) ?></span>
                        <img class="img-profile-custom" src="<?= htmlspecialchars($profilePhoto) ?>" alt="Profile">
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
                <div class="mb-3">
                    <h1 class="h3 mb-1 text-gray-800">Dashboard</h1>
                    <span class="handled-pill">Section Handled: <strong id="handledSectionsText"><?= htmlspecialchars($handledSectionText) ?></strong></span>
                </div>

                <!-- Stats Row -->
                <div class="row">
                    <!-- Total Enrolled -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Enrolled Students</div>
                                        <div class="h3 mb-0 font-weight-bold text-gray-800" id="totalStudentsCount">0</div>
                                        <small class="text-muted">For SY 2025-2026</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-graduate fa-3x icon-total"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Present Today -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Present Today (Time In &amp; Out)</div>
                                        <div class="h3 mb-0 font-weight-bold text-gray-800" id="presentTodayCount">0</div>
                                        <small class="text-success"><span id="attendanceRate">0</span>% attendance rate</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-3x icon-present"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Absent Today -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">Absent Today</div>
                                        <div class="h3 mb-0 font-weight-bold text-gray-800" id="absentTodayCount">0</div>
                                        <small class="text-danger"><span id="absenceRate">0</span>% absence rate</small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-times fa-3x icon-absent"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chart -->
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Weekly Attendance Statistics (Completed %)</h6>
                            </div>
                            <div class="card-body">
                                <div style="height:300px;"><canvas id="facultyChart"></canvas></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">Select "Logout" below if you are ready to end your session.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger" href="faculty_logout.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
let facultyChart;

function initChart() {
    const ctx = document.getElementById('facultyChart').getContext('2d');
    facultyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Monday','Tuesday','Wednesday','Thursday','Friday'],
            datasets: [{
                label: 'Completed Attendance Rate %',
                data: [0,0,0,0,0],
                backgroundColor: '#800000'
            }]
        },
        options: {
            responsive:true, maintainAspectRatio:false,
            scales:{ y:{ beginAtZero:true, max:100, ticks:{ callback:v => v + '%' } } }
        }
    });
}

function updateStats() {
    fetch('faculty_dashboard.php?action=get_faculty_stats', { cache:'no-store' })
        .then(res => res.json())
        .then(data => {
            document.getElementById('totalStudentsCount').innerText = data.totalStudents;
            document.getElementById('presentTodayCount').innerText  = data.presentToday;
            document.getElementById('absentTodayCount').innerText   = data.absentToday;
            document.getElementById('attendanceRate').innerText     = data.attendanceRate;
            document.getElementById('absenceRate').innerText        = (100 - data.attendanceRate).toFixed(1);

            if (facultyChart && Array.isArray(data.percentages)) {
                facultyChart.data.datasets[0].data = data.percentages;
                facultyChart.update();
            }
        });
}

$(document).ready(function() {
    initChart();
    updateStats();
    setInterval(updateStats, 5000);
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>