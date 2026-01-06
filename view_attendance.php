<?php
session_start();

// --- 1. AUTHENTICATION CHECK ---
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

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

// Fetch User Data for Navbar
$email = $_SESSION['email'];
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt_user->execute([$email]);
$user = $stmt_user->fetch();

date_default_timezone_set('Asia/Manila');

// --- Fetch Time Settings ---
try {
    $pdo->query("SELECT 1 FROM time_settings LIMIT 1");
} catch (PDOException $e) {
    // Create table if not exists
}

$stmt_settings = $pdo->query("SELECT morning_start, morning_end, morning_late_threshold, afternoon_end FROM time_settings WHERE id = 1");
$time_settings = $stmt_settings->fetch();
$morning_start_time_limit = $time_settings['morning_start'] ?? '06:00:00';
$morning_end_time_limit   = $time_settings['morning_end'] ?? '09:00:00';
$morning_late_threshold   = $time_settings['morning_late_threshold'] ?? '08:30:00';
$afternoon_end_time_limit = $time_settings['afternoon_end'] ?? '16:30:00';

// --- Automated Update Logic ---
$current_date = date('Y-m-d');
$current_time = date('H:i:s');
$stmt_update_absent = $pdo->prepare("
    UPDATE attendance SET status = 'absent'
    WHERE time_out IS NULL AND (date < :current_date OR (date = :current_date AND :current_time > :afternoon_end))
");
$stmt_update_absent->execute(['current_date' => $current_date, 'current_time' => $current_time, 'afternoon_end' => $afternoon_end_time_limit]);

// --- FETCH RECORDS ---
$attendanceRecords = [];
$stmt = $pdo->prepare("
    SELECT a.date, a.time_in, a.time_out, a.status,
        s.firstname, s.lastname, 
        e.grade_level, e.section_name,
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

// --- Process Records ---
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
$jsonRecords = json_encode($attendanceRecords);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Attendance Records</title>

    <!-- Bootstrap 5 & SB Admin 2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- SIDEBAR STYLING FROM SCREENSHOT --- */
        #accordionSidebar {
            background-color: #0011ff !important; /* The Bright Blue Color */
            background-image: none !important;    /* Remove default gradient */
        }

        /* Logo Area */
        .sidebar-brand {
            height: auto !important;
            padding: 20px 0 !important;
            flex-direction: column !important; /* Stack Logo and Text Vertically */
        }
        
        .sidebar-brand-text {
            margin-top: 10px;
            font-size: 1.2rem;
            font-weight: 800; /* Bold 'ADMIN' */
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Navigation Links */
        .nav-item .nav-link {
            color: #ffffff !important; /* White text */
            opacity: 1 !important;
            padding: 12px 1rem;
            font-weight: 500;
        }

        .nav-item .nav-link i {
            color: #ffffff !important; /* White icons */
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .nav-item .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1); /* Slight hover effect */
        }

        /* Section Headings (STUDENT MANAGEMENT, etc.) */
        .sidebar-heading {
            color: rgba(255, 255, 255, 0.6) !important;
            font-size: 0.7rem !important;
            font-weight: bold !important;
            text-transform: uppercase;
            padding-top: 15px;
            padding-bottom: 5px;
        }

        /* Dividers */
        hr.sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
            margin: 0 1rem 1rem 1rem !important;
        }
        
        /* --- PAGE SPECIFIC STYLES --- */
        .content-background {
            background-image: url('img/pi.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 90vh;
            padding: 30px;
        }
        
        .table-card {
            background-color: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            width: min(100%, 1400px);
            margin: 0 auto;
        }

        /* Search Bar Styles */
        .search-box {
            position: relative;
            width: 100%;
            max-width: 400px;
            margin: 0 auto 20px auto;
        }
        .search-input {
            width: 100%;
            padding: 12px 50px 12px 20px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 50px;
            outline: none;
        }
        .search-input:focus {
            border-color: #0011ff;
        }
        .search-icon {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }

        /* --- ENHANCED SCROLLABLE TABLE STYLES --- */
        .table-responsive-custom {
            max-height: 65vh;
            overflow-y: auto;
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #e3e6f0;
            background-color: rgba(255,255,255,0.98);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        
        /* --- PERFECT MATCH WITH NAV BAR COLOR - ALL 8 HEADERS --- */
        thead th {
            position: sticky !important;
            top: 0 !important;
            z-index: 100 !important;
            background: linear-gradient(135deg, #0011ff 0%, #0033ff 100%) !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            padding: 16px 12px !important;
            text-transform: uppercase !important;
            font-size: 0.88rem !important;
            letter-spacing: 0.5px !important;
            box-shadow: 0 3px 10px rgba(0,17,255,0.3) !important;
            border: none !important;
            border-bottom: 3px solid rgba(255,255,255,0.2) !important;
            text-shadow: 0 1px 2px rgba(0,0,0,0.2) !important;
        }
        
        /* Ensure ALL header cells get the styling */
        thead th:nth-child(1), /* Date */
        thead th:nth-child(2), /* Student Name */
        thead th:nth-child(3), /* Grade Level */
        thead th:nth-child(4), /* Section */
        thead th:nth-child(5), /* Adviser */
        thead th:nth-child(6), /* Time In */
        thead th:nth-child(7), /* Time Out */
        thead th:nth-child(8)  /* Status */
        {
            background: linear-gradient(135deg, #0011ff 0%, #0033ff 100%) !important;
            color: #ffffff !important;
        }
        
        td {
            padding: 16px 12px;
            border-bottom: 1px solid #e3e6f0;
            vertical-align: middle;
        }
        
        /* Status Colors */
        .status-present { color: #28a745; font-weight: bold; }
        .status-absent { color: #dc3545; font-weight: bold; }
        .status-late { color: #ffc107; font-weight: bold; }
        .status-halfday { color: #3b82f6; font-weight: bold; }
        
        /* Hover effect for rows */
        tr:hover {
            background-color: rgba(0,17,255,0.05) !important;
        }
    </style>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- ================= SIDEBAR ================= -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="admin_dashboard.php">
                <div class="sidebar-brand-icon">
                    <!-- Make sure logo path is correct -->
                    <img src="img/logo.jpg" alt="Logo" class="rounded-circle" 
                         style="width: 80px; height: 80px; object-fit: cover; border: 3px solid white;">
                </div>
                <div class="sidebar-brand-text mx-3">ADMIN</div>
            </a>

            <!-- Divider -->
            <hr class="sidebar-divider my-2">

            <!-- Nav Item - Dashboard -->
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Student Management
            </div>

            <li class="nav-item">
                <a class="nav-link" href="students_list.php">
                    <i class="fas fa-user-graduate"></i>
                    <span>Add Student</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="guardian.php">
                    <i class="fas fa-home"></i>
                    <span>Guardian Of Students</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="enrollment.php">
                    <i class="fas fa-pen"></i>
                    <span>Enroll Student</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="link_rfid.php">
                    <i class="far fa-credit-card"></i>
                    <span>Link RFID</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Teachers Management
            </div>

            <li class="nav-item">
                <a class="nav-link" href="add_adviser.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Add Adviser</span></a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="section_student.php">
                    <i class="fas fa-book-reader"></i>
                    <span>Assign Adviser</span></a>
            </li>

            <!-- Divider -->
            <hr class="sidebar-divider">

            <!-- Heading -->
            <div class="sidebar-heading">
                Social Media
            </div>

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
        <!-- ================= END SIDEBAR ================= -->

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <ul class="navbar-nav ml-auto">
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?php echo htmlspecialchars($user['email'] ?? 'Admin'); ?></span>
                                <img class="img-profile rounded-circle" src="img/profile.svg">
                            </a>
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i> Logout
                                </a>
                            </div>
                        </li>
                    </ul>
                </nav>
                <!-- End of Topbar -->

                <!-- ================= MAIN CONTENT ================= -->
                <!-- Wrapper for background image -->
                <div class="content-background">
                    
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 text-gray-800 bg-white p-2 rounded"><i class="fas fa-clipboard-list"></i> Attendance Records</h1>
                        <a href="admin_dashboard.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>

                    <div class="table-card">
                        <!-- Search Section -->
                        <div class="search-box">
                            <input type="text" id="searchInput" class="search-input" placeholder="Search students, sections, advisers..." autocomplete="off">
                            <i class="fas fa-search search-icon"></i>
                        </div>

                        <div class="text-center mb-3">
                            <span class="badge bg-secondary p-2" id="recordCount" style="font-size: 1rem;">
                                Total Records: <?= count($attendanceRecords) ?>
                            </span>
                        </div>
                        
                        <!-- SCROLLABLE TABLE CONTAINER -->
                        <div class="table-responsive-custom">
                            <table class="table table-hover mb-0" id="attendanceTable">
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
                                    <!-- Rows populated by JS -->
                                </tbody>
                            </table>
                        </div>
                        
                        <div id="noResults" class="text-center p-4 text-muted" style="display: none;">
                            <i class="fas fa-search fa-2x mb-2"></i><br>
                            No records found matching your search criteria.
                        </div>
                    </div>

                </div>
                <!-- ================= END MAIN CONTENT ================= -->

            </div>
            <!-- End of Main Content -->
        </div>
        <!-- End of Content Wrapper -->
    </div>
    <!-- End of Page Wrapper -->

    <!-- Logout Modal-->
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
                    <a class="btn btn-primary" href="logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/sb-admin-2.min.js"></script>

    <script>
        const allRecords = <?= $jsonRecords ?>;
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.querySelector('#attendanceTable tbody');
        const recordCount = document.getElementById('recordCount');
        const noResults = document.getElementById('noResults');

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function displayRecords(records) {
            tableBody.innerHTML = '';
            
            if (records.length === 0) {
                noResults.style.display = 'block';
                document.getElementById('attendanceTable').style.display = 'none';
            } else {
                noResults.style.display = 'none';
                document.getElementById('attendanceTable').style.display = 'table';
                
                records.forEach(record => {
                    const row = document.createElement('tr');
                    
                    const displayTimeIn = record.time_in ?
                        new Date(record.time_in.replace(' ', 'T')).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';

                    const displayTimeOut = record.time_out ?
                        new Date(record.time_out.replace(' ', 'T')).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : 'N/A';

                    row.innerHTML = `
                        <td>${formatDate(record.date)}</td>
                        <td class="fw-bold">${record.firstname} ${record.lastname}</td>
                        <td>${record.grade_level || 'N/A'}</td>
                        <td>${record.section_name || 'N/A'}</td>
                        <td>${record.adviser_name || 'N/A'}</td>
                        <td>${displayTimeIn}</td>
                        <td>${displayTimeOut}</td>
                        <td class="status-${(record.display_status || '').toLowerCase()}">
                            ${record.display_status ? record.display_status.charAt(0).toUpperCase() + record.display_status.slice(1) : 'N/A'}
                        </td>
                    `;
                    tableBody.appendChild(row);
                });
            }
            recordCount.innerText = `Total Records: ${records.length}`;
        }

        function filterRecords() {
            const searchTerm = searchInput.value.toLowerCase().trim();
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

        searchInput.addEventListener('input', filterRecords);
        
        // Initial Load
        displayRecords(allRecords);
    </script>

</body>
</html>