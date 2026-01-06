<?php
session_start(); 

// --- 1. DATABASE & AUTHENTICATION ---
include 'conn.php';

// Check if user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

$email = $_SESSION['email'];
// Fetch user data
$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// --- 2. CALENDAR LOGIC ---
date_default_timezone_set('Asia/Manila');

$month = date('n');
$year  = date('Y');
$today = date('j');
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$totalDays = date('t', $firstDayOfMonth);
$monthName = date('F', $firstDayOfMonth);
$weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$startDay = date('w', $firstDayOfMonth);
$currentDateTimeJS = date('Y-m-d H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Dashboard</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- SB Admin 2 CSS -->
    <link href="css/sb-admin-2.min.css" rel="stylesheet">

    <style>
        /* --- SIDEBAR STYLING (From your first code) --- */
        #accordionSidebar {
            background-color: #0022ff !important;
            background-image: none !important;
        }
        .sidebar-brand {
            height: auto !important;
            padding: 20px 0 !important;
            flex-direction: column !important;
        }
        .sidebar-brand-text {
            margin-top: 10px;
            font-size: 1.2rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .nav-item .nav-link {
            color: #ffffff !important;
            opacity: 1 !important;
            padding: 12px 1rem;
            font-weight: 500;
        }
        .nav-item .nav-link i {
            color: #ffffff !important;
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        .nav-item .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar-heading {
            color: rgba(255, 255, 255, 0.6) !important;
            font-size: 0.7rem !important;
            font-weight: bold !important;
            text-transform: uppercase;
            padding-top: 15px;
            padding-bottom: 5px;
        }
        hr.sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
            margin: 0 1rem 1rem 1rem !important;
        }

        /* --- MAIN CONTENT AREA --- */
        #content {
            background-color: #f8f9fc;
            padding: 20px;
        }

        /* --- NEW CALENDAR STYLING (MATCHING IMAGE) --- */
        .calendar-wrapper {
            /* Background image with a white overlay to match the "washed out" look */
            background: linear-gradient(rgba(255,255,255,0.7), rgba(255,255,255,0.7)), url('img/pic6.jpg');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
            min-height: 85vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Clock Text */
        .clock-label {
            color: #333;
            font-size: 1.1rem;
            margin-bottom: 5px;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.8);
        }
        .clock-time {
            font-size: 1.8rem;
            font-weight: bold;
            color: #222;
            margin-bottom: 25px;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.8);
        }

        /* The Calendar Table */
        table.calendar {
            width: 100%;
            max-width: 600px; /* Constrain width like image */
            border-collapse: collapse;
            background-color: rgba(255, 255, 255, 0.8); /* Slight transparency */
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            border: 2px solid #999;
        }

        .calendar th {
            background-color: #1565c0; /* The specific Blue header */
            color: white;
            padding: 15px;
            font-size: 1.1rem;
            border: 1px solid #0d47a1;
            width: 14.28%;
        }

        .calendar td {
            height: 60px; /* Taller cells */
            border: 1px solid #999;
            text-align: center;
            font-size: 1.1rem;
            color: #333;
            background-color: #eee; /* Light grey cells */
        }

        /* The "Today" Highlight */
        .calendar td.today {
            background-color: #ffeb3b !important; /* Bright Yellow */
            font-weight: bold;
            color: black;
            border: 1px solid #fbc02d;
        }

        /* Back Button */
        .btn-back {
            background-color: #1565c0;
            color: white;
            padding: 10px 40px;
            border-radius: 5px;
            border: none;
            margin-top: 30px;
            font-size: 1.1rem;
            transition: background 0.3s;
        }
        .btn-back:hover {
            background-color: #0d47a1;
            color: white;
            text-decoration: none;
        }

        .month-title {
            font-size: 1.8rem;
            margin-bottom: 10px;
            color: #222;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.8);
        }

        /* Topbar Styling */
        .topbar {
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .topbar .nav-link {
            color: #6e707e !important;
        }

        .topbar .nav-link:hover {
            color: #2e59d9 !important;
        }

        .img-profile {
            width: 40px;
            height: 40px;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .calendar-wrapper {
                padding: 10px;
                min-height: 70vh;
            }
            
            .clock-time {
                font-size: 1.4rem;
            }
            
            .month-title {
                font-size: 1.5rem;
            }
            
            .calendar td {
                height: 50px;
                font-size: 1rem;
            }
            
            .calendar th {
                padding: 10px;
                font-size: 1rem;
            }
            
            .btn-back {
                padding: 8px 30px;
                font-size: 1rem;
            }
        }
    </style>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- ================= SIDEBAR (From first code) ================= -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
            <!-- Sidebar - Brand -->
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="admin_dashboard.php">
                <div class="sidebar-brand-icon">
                    <img src="img/logo.jpg" alt="Logo" class="rounded-circle" 
                         style="width: 80px; height: 80px; object-fit: cover; border: 3px solid white;">
                </div>
                <div class="sidebar-brand-text mx-3">ADMIN</div>
            </a>
            <hr class="sidebar-divider my-2">
            
            <!-- Navigation Items -->
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Student Management</div>
            
            <li class="nav-item">
                <a class="nav-link" href="students_list.php">
                    <i class="fas fa-user-graduate"></i>
                    <span>Add Student</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="guardian.php">
                    <i class="fas fa-home"></i>
                    <span>Guardian Of Students</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="enrollment.php">
                    <i class="fas fa-pen"></i>
                    <span>Enroll Student</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="link_rfid.php">
                    <i class="far fa-credit-card"></i>
                    <span>Link RFID</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Teachers Management</div>
            
            <li class="nav-item">
                <a class="nav-link" href="add_adviser.php">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Add Adviser</span>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link" href="section_student.php">
                    <i class="fas fa-book-reader"></i>
                    <span>Assign Adviser</span>
                </a>
            </li>
            
            <hr class="sidebar-divider">
            <div class="sidebar-heading">Social Media</div>
            
            <li class="nav-item">
                <a class="nav-link" href="https://web.facebook.com" target="_blank">
                    <i class="fab fa-facebook-f"></i>
                    <span>Facebook</span>
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
            <hr class="sidebar-divider">
            
        </ul>

        <!-- Content Wrapper -->
        <div id="content-wrapper" class="d-flex flex-column">

            <!-- Main Content -->
            <div id="content">

                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <!-- Sidebar Toggle (Topbar) -->
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <!-- Topbar Navbar -->
                    <ul class="navbar-nav ml-auto">

                        <!-- Nav Item - User Information -->
                        <li class="nav-item dropdown no-arrow">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </span>
                                <img class="img-profile rounded-circle" src="img/profile.svg">
                            </a>
                            <!-- Dropdown - User Information -->
                            <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                                aria-labelledby="userDropdown">
                                <a class="dropdown-item" href="#">
                                    <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Profile
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                    Logout
                                </a>
                            </div>
                        </li>

                    </ul>

                </nav>
                <!-- End of Topbar -->

                <!-- ================= CALENDAR CONTENT ================= -->
                <div class="container-fluid">
                    <div class="calendar-wrapper">
                        
                        <!-- 1. Clock Header -->
                        <div class="text-center">
                            <div class="clock-label">Current Philippine Date and Time:</div>
                            <div class="clock-time" id="clock">Loading...</div>
                        </div>

                        <!-- 2. Month Title -->
                        <div class="month-title"><?php echo "$monthName $year"; ?></div>

                        <!-- 3. Calendar Table -->
                        <table class="calendar">
                            <thead>
                                <tr>
                                    <?php foreach ($weekDays as $weekDay) { echo "<th>$weekDay</th>"; } ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <?php
                                    // Empty cells before start
                                    for ($i = 0; $i < $startDay; $i++) { echo "<td></td>"; }

                                    $dayOfWeek = $startDay;
                                    for ($day = 1; $day <= $totalDays; $day++, $dayOfWeek++) {
                                        // Highlight today
                                        $class = ($day == $today) ? "today" : "";
                                        echo "<td class='$class'>$day</td>";

                                        // New row logic
                                        if ($dayOfWeek == 6 && $day != $totalDays) {
                                            echo "</tr><tr>";
                                            $dayOfWeek = -1;
                                        }
                                    }

                                    // Empty cells after end
                                    if ($dayOfWeek != 7) {
                                        for ($i = $dayOfWeek; $i < 7; $i++) { echo "<td></td>"; }
                                    }
                                    ?>
                                </tr>
                            </tbody>
                        </table>

                        <!-- 4. Back Button -->
                        <button class="btn-back" onclick="window.history.back()">
                            ← Back
                        </button>

                    </div>
                </div>
                <!-- ================= END CALENDAR CONTENT ================= -->

            </div>
            <!-- End of Main Content -->

            <!-- Footer -->
            <footer class="sticky-footer bg-white">
                <div class="container my-auto">
                    <div class="copyright text-center my-auto">
                        <span>Copyright &copy; Your Website 2024</span>
                    </div>
                </div>
            </footer>
            <!-- End of Footer -->

        </div>
        <!-- End of Content Wrapper -->

    </div>
    <!-- End of Page Wrapper -->

    <!-- Scroll to Top Button-->
    <a class="scroll-to-top rounded" href="#page-top">
        <i class="fas fa-angle-up"></i>
    </a>

    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
        aria-hidden="true">
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

    <!-- Updated Clock Script to match Image Format -->
    <script>
        let phTime = new Date('<?php echo $currentDateTimeJS; ?>');

        function updateClock() {
            // Options for date part: "Tuesday | January 6, 2026"
            const dateOptions = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            
            // Options for time part: "06:34:00 PM"
            const timeOptions = { 
                hour: '2-digit', 
                minute: '2-digit', 
                second: '2-digit', 
                hour12: true 
            };

            const dateString = phTime.toLocaleDateString('en-US', dateOptions); // e.g. "Tuesday, January 6, 2026"
            const timeString = phTime.toLocaleTimeString('en-US', timeOptions); // e.g. "06:34:00 PM"

            // Construct specific format: "Day | Date at Time"
            // toLocaleDateString usually adds commas, we replace the first comma with pipe
            let formattedDate = dateString.replace(',', ' |');
            
            document.getElementById('clock').textContent = `${formattedDate} at ${timeString}`;

            phTime.setSeconds(phTime.getSeconds() + 1);
        }

        updateClock();
        setInterval(updateClock, 1000);

        // Toggle the side navigation
        $(document).ready(function() {
            $("#sidebarToggle, #sidebarToggleTop").on('click', function(e) {
                $("body").toggleClass("sidebar-toggled");
                $(".sidebar").toggleClass("toggled");
                if ($(".sidebar").hasClass("toggled")) {
                    $('.sidebar .collapse').collapse('hide');
                }
            });
        });
    </script>
</body>
</html>