<?php
// admin_dashboard.php

// --- AJAX ENDPOINT FOR REAL-TIME DASHBOARD STATS ---
if (isset($_GET['action']) && $_GET['action'] === 'get_stats') {
    header('Content-Type: application/json');

    include 'conn.php';
    if (!isset($conn) || $conn->connect_error) {
        http_response_code(500);
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }

    date_default_timezone_set('Asia/Manila');
    $current_date = date('Y-m-d');
    $currentMonth = (int)date('m');
    $currentYear  = (int)date('Y');

    // Determine current school year
    if ($currentMonth >= 8) {
        $current_school_year = $currentYear . '-' . ($currentYear + 1);
    } else {
        $current_school_year = ($currentYear - 1) . '-' . $currentYear;
    }

    // 1. Total enrolled students (current school year)
    $totalStudents = 0;
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT lrn) AS total_students
        FROM enrollments
        WHERE school_year = ?
    ");
    $stmt->bind_param("s", $current_school_year);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $totalStudents = (int)($result->fetch_assoc()['total_students'] ?? 0);
    }
    $stmt->close();

    // 2. Students who COMPLETED attendance today:
    //    have status present/late AND both time_in and time_out filled.
    $presentToday = 0;
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT lrn) AS completed_today
        FROM attendance
        WHERE date = ?
          AND status IN ('present', 'late')
          AND time_in  IS NOT NULL
          AND time_out IS NOT NULL
    ");
    $stmt->bind_param("s", $current_date);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        $presentToday = (int)($result->fetch_assoc()['completed_today'] ?? 0);
    }
    $stmt->close();

    // 3. Absent & rates
    $absentToday    = max(0, $totalStudents - $presentToday);
    $attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
    $absenceRate    = $totalStudents > 0 ? round(($absentToday   / $totalStudents) * 100, 1) : 0;

    // 4. Weekly overview (Mon–Fri) based on COMPLETED attendance per day
    $weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
    $attendanceOverview = [];
    foreach ($weekdays as $d) {
        $attendanceOverview[$d] = ['percentage' => 0];
    }

    $stmt = $conn->prepare("
        SELECT DAYNAME(date) AS day_name,
               COUNT(DISTINCT lrn) AS completed_count
        FROM attendance
        WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)
          AND status IN ('present', 'late')
          AND time_in  IS NOT NULL
          AND time_out IS NOT NULL
        GROUP BY day_name
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $day = $row['day_name'];
            if (isset($attendanceOverview[$day])) {
                $completedCount = (int)$row['completed_count'];
                $percent = $totalStudents > 0
                    ? round(($completedCount / $totalStudents) * 100, 1)
                    : 0;
                $attendanceOverview[$day]['percentage'] = $percent;
            }
        }
    }
    $stmt->close();
    $conn->close();

    echo json_encode([
        'totalStudents'  => number_format($totalStudents),
        'presentToday'   => number_format($presentToday),
        'absentToday'    => number_format($absentToday),
        'attendanceRate' => $attendanceRate,
        'absenceRate'    => $absenceRate,
        'chartData'      => [
            'labels'      => array_keys($attendanceOverview),
            'percentages' => array_values(array_column($attendanceOverview, 'percentage')),
        ],
    ]);
    exit;
}

// --- FULL PAGE LOAD (initial render, same logic as AJAX) ---
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}
$userEmail = $_SESSION['email'];

include 'conn.php';
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

date_default_timezone_set('Asia/Manila');
$current_date  = date('Y-m-d');
$currentMonth  = (int)date('m');
$currentYear   = (int)date('Y');

if ($currentMonth >= 8) {
    $current_school_year = $currentYear . '-' . ($currentYear + 1);
} else {
    $current_school_year = ($currentYear - 1) . '-' . $currentYear;
}

// Total students
$totalStudents = 0;
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT lrn) AS total_students
    FROM enrollments
    WHERE school_year = ?
");
$stmt->bind_param("s", $current_school_year);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $totalStudents = (int)($result->fetch_assoc()['total_students'] ?? 0);
}
$stmt->close();

// Completed attendance today (time_in & time_out)
$presentToday = 0;
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT lrn) AS completed_today
    FROM attendance
    WHERE date = ?
      AND status IN ('present', 'late')
      AND time_in  IS NOT NULL
      AND time_out IS NOT NULL
");
$stmt->bind_param("s", $current_date);
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    $presentToday = (int)($result->fetch_assoc()['completed_today'] ?? 0);
}
$stmt->close();

// Absent + rates
$absentToday    = max(0, $totalStudents - $presentToday);
$attendanceRate = $totalStudents > 0 ? round(($presentToday / $totalStudents) * 100, 1) : 0;
$absenceRate    = $totalStudents > 0 ? round(($absentToday   / $totalStudents) * 100, 1) : 0;

// Weekly overview for initial chart
$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$attendanceOverview = [];
foreach ($weekdays as $d) {
    $attendanceOverview[$d] = ['percentage' => 0];
}
$stmt = $conn->prepare("
    SELECT DAYNAME(date) AS day_name,
           COUNT(DISTINCT lrn) AS completed_count
    FROM attendance
    WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)
      AND status IN ('present', 'late')
      AND time_in  IS NOT NULL
      AND time_out IS NOT NULL
    GROUP BY day_name
");
$stmt->execute();
$result = $stmt->get_result();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $day = $row['day_name'];
        if (isset($attendanceOverview[$day])) {
            $completedCount = (int)$row['completed_count'];
            $percent = $totalStudents > 0
                ? round(($completedCount / $totalStudents) * 100, 1)
                : 0;
            $attendanceOverview[$day]['percentage'] = $percent;
        }
    }
}
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-weight: 700 !important; }
        /* Keep existing admin sidebar color */
        #accordionSidebar { transition: width .3s, margin .3s; background-color: rgb(7, 29, 230); }
        #accordionSidebar.toggled { width: 0 !important; overflow: hidden; margin-left: -225px; }
        #content-wrapper.toggled { margin-left: 0; }
        #content-wrapper { transition: margin .3s; }
        .btn-maroon { color:#fff; background-color:#800000; border-color:#800000; }
        .btn-maroon:hover { color:#fff; background-color:#660000; border-color:#590000; }
        .topbar .navbar-nav .nav-item .nav-link .img-profile { height:2rem; width:2rem; }

        /* LOGOUT LINK HOVER STYLE */
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
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <!-- Sidebar -->
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
        <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center"
           href="admin_dashboard.php" style="padding: 1.5rem 1rem; height: auto;">
            <img src="img/logo.jpg" alt="School Logo" class="rounded-circle mb-2"
                 style="width: 85px; height: 85px; object-fit: cover;">
            <div class="sidebar-brand-text" style="font-weight: 900; font-size: 1rem;">ADMIN</div>
        </a>
        <hr class="sidebar-divider my-0">

        <li class="nav-item active">
            <a class="nav-link" href="admin_dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span>
            </a>
        </li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">Student Management</div>
        <li class="nav-item"><a class="nav-link" href="students_list.php"><i class="fa fa-user-graduate"></i><span>Add Student</span></a></li>
        <li class="nav-item"><a class="nav-link" href="guardian.php"><i class="fa fa-house-user"></i><span>Guardian Of Students</span></a></li>
        <li class="nav-item"><a class="nav-link" href="enrollment.php"><i class="fa fa-pen-alt"></i><span>Enroll Student</span></a></li>
        <li class="nav-item"><a class="nav-link" href="link_rfid.php"><i class="fa fa-credit-card"></i><span>Link RFID</span></a></li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">Teachers Management</div>
        <li class="nav-item"><a class="nav-link" href="add_adviser.php"><i class="fa fa-chalkboard-teacher"></i><span>Add Adviser</span></a></li>
        <li class="nav-item"><a class="nav-link" href="section_student.php"><i class="fa fa-book-reader"></i><span>Assign Adviser</span></a></li>

        <hr class="sidebar-divider">
        <div class="sidebar-heading">Social Media</div>
        <li class="nav-item">
            <a class="nav-link" href="https://web.facebook.com/DepEdTayoSINHS301394.official/?_rdc=1&_rdr#" target="_blank">
                <i class="fab fa-facebook-f"></i><span>Facebook</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="https://www.youtube.com/channel/UCDw3mhzSTm_NFk_2dFbhBKg" target="_blank">
                <i class="fab fa-youtube"></i><span>YouTube</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="https://ph.search.yahoo.com/search?p=san+isidro+national+high+school+padre+burgos+quezon" target="_blank">
                <i class="fab fa-google"></i><span>Google</span>
            </a>
        </li>
        <hr class="sidebar-divider d-none d-md-block">
    </ul>
    <!-- End Sidebar -->

    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
                <ul class="navbar-nav ml-auto">
                    <div class="topbar-divider d-none d-sm-block"></div>
                    <li class="nav-item">
                        <a class="nav-link" href="#">
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                <?= htmlspecialchars($userEmail) ?>
                            </span>
                            <img class="img-profile rounded-circle" src="img/profile.svg">
                        </a>
                    </li>
                    
                    <!-- UPDATED LOGOUT BUTTON -->
                    <li class="nav-item d-flex align-items-center">
                        <a class="logout-link px-3" href="#" data-toggle="modal" data-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="container-fluid">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                    <a href="generate_report.php" class="d-none d-sm-inline-block btn btn-sm btn-maroon shadow-sm">
                        <i class="fas fa-download fa-sm text-white-50"></i> Generate Report
                    </a>
                </div>

                <!-- Cards row -->
                <div class="row">
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Enrolled Students
                                        </div>
                                        <h3 class="mb-0 text-gray-800" id="totalStudentsCount">
                                            <?= number_format($totalStudents) ?>
                                        </h3>
                                        <small class="text-muted">
                                            For SY <?= htmlspecialchars($current_school_year) ?>
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-graduate fa-2x text-danger"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Present (completed) -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Present Today (Time In &amp; Time Out)
                                        </div>
                                        <h3 class="mb-0 text-gray-800" id="presentTodayCount">
                                            <?= number_format($presentToday) ?>
                                        </h3>
                                        <small class="text-success" id="attendanceRate">
                                            <?= $attendanceRate ?>% attendance rate
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-check fa-2x text-success"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Absent -->
                    <div class="col-xl-4 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Absent Today
                                        </div>
                                        <h3 class="mb-0 text-gray-800" id="absentTodayCount">
                                            <?= number_format($absentToday) ?>
                                        </h3>
                                        <small class="text-danger" id="absenceRate">
                                            <?= $absenceRate ?>% absence rate
                                        </small>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-user-times fa-2x text-warning"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions + Chart -->
                <div class="row">
                    <div class="col-lg-12 mb-4">
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-secondary text-white fw-semibold">Quick Actions</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-4 col-sm-6">
                                        <a href="attendance_time.php" class="btn btn-primary w-100">
                                            <i class="bi bi-clock"></i> Attendance Time Setting
                                        </a>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <a href="student_calendar.php" class="btn btn-success w-100">
                                            <i class="bi bi-calendar"></i> Student Calendar
                                        </a>
                                    </div>
                                    <div class="col-md-4 col-sm-6">
                                        <a href="view_attendance.php" class="btn btn-warning text-dark w-100">
                                            <i class="bi bi-eye"></i> View Attendance
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-primary text-white fw-semibold">
                                Weekly Attendance Overview (Completed %)
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="150"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- /.container-fluid -->
        </div><!-- /#content -->

        <?php include 'footer.php'; ?>
    </div><!-- /#content-wrapper -->
</div><!-- /#wrapper -->

<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
                <button class="close" type="button" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">×</span>
                </button>
            </div>
            <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                <a class="btn btn-danger" href="admin_login.php">Logout</a>
            </div>
        </div>
    </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>

<script>
let attendanceChart = null;

document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('attendanceChart');
    if (ctx) {
        const initialLabels = <?= json_encode(array_keys($attendanceOverview)) ?>;
        const initialData   = <?= json_encode(array_values(array_column($attendanceOverview, 'percentage'))) ?>;

        attendanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: initialLabels,
                datasets: [{
                    label: 'Completed Attendance %',
                    data: initialData,
                    backgroundColor: 'rgba(13,110,253,0.7)',
                    borderColor: 'rgba(13,110,253,1)',
                    borderWidth: 1,
                    borderRadius: 4,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        ticks: { callback: v => v + '%' }
                    }
                },
                plugins: { legend: { display:false } }
            }
        });
    }

    const totalStudentsEl  = document.getElementById('totalStudentsCount');
    const presentTodayEl   = document.getElementById('presentTodayCount');
    const attendanceRateEl = document.getElementById('attendanceRate');
    const absentTodayEl    = document.getElementById('absentTodayCount');
    const absenceRateEl    = document.getElementById('absenceRate');

    async function updateDashboardStats() {
        try {
            const res = await fetch('admin_dashboard.php?action=get_stats', { cache: 'no-store' });
            if (!res.ok) return;
            const data = await res.json();

            totalStudentsEl.textContent  = data.totalStudents;
            presentTodayEl.textContent   = data.presentToday;
            absentTodayEl.textContent    = data.absentToday;
            attendanceRateEl.textContent = data.attendanceRate + '% attendance rate';
            absenceRateEl.textContent    = data.absenceRate + '% absence rate';

            if (attendanceChart && data.chartData) {
                attendanceChart.data.labels = data.chartData.labels;
                attendanceChart.data.datasets[0].data = data.chartData.percentages;
                attendanceChart.update();
            }
        } catch (e) {
            console.error('Error updating dashboard stats', e);
        }
    }

    setInterval(updateDashboardStats, 3000);
});
</script>
</body>
</html>