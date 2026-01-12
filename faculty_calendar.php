<?php
/* --------------------------------------------------------------
 * Faculty Calendar (READ-ONLY VIEW) - MATCHING DASHBOARD LAYOUT
 * -------------------------------------------------------------- */
session_start();

require_once 'conn.php';

// Check for faculty login session
if (!isset($_SESSION['faculty_logged_in'])) {
    header('Location: faculty_login.php');
    exit();
}

// Fetch faculty details for the top bar
$sql = "
    SELECT a.firstname, a.lastname, a.photo
    FROM faculty_login f
    INNER JOIN advisers a ON a.employee_id = f.employee_id
    WHERE f.faculty_id = ?
    LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['faculty_id']);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$faculty) {
    die("Faculty record not found. Please log in again.");
}

// --- 2. CALENDAR LOGIC & EVENT PRE-FETCHING ---
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

$first_day_of_month = date('Y-m-01');
$last_day_of_month  = date('Y-m-t');

// Fetch events
$allEvents = [];
$eventQuery = "SELECT * FROM events 
               WHERE start_date BETWEEN ? AND ? 
               ORDER BY start_date, start_time";
$eventStmt = $conn->prepare($eventQuery);
$eventStmt->bind_param("ss", $first_day_of_month, $last_day_of_month);
$eventStmt->execute();
$eventsResult = $eventStmt->get_result();
while ($event = $eventsResult->fetch_assoc()) {
    $allEvents[$event['start_date']][] = $event;
}
$eventStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Faculty Dashboard - Student Calendar</title>

<!-- SAME AS DASHBOARD -->
<link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
<link href="css/sb-admin-2.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">

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

    /* ====== CALENDAR SPECIFIC STYLES ====== */
    .calendar-wrapper {
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

    table.calendar { 
        width: 100%; 
        max-width: 600px; 
        border-collapse: collapse; 
        background-color: rgba(255, 255, 255, 0.8); 
        box-shadow: 0 4px 8px rgba(0,0,0,0.1); 
        border: 2px solid #999; 
    }
    .calendar th { 
        background-color: #1565c0; 
        color: white; 
        padding: 15px; 
        font-size: 1.1rem; 
        border: 1px solid #0d47a1; 
        width: 14.28%; 
    }
    .calendar td {
        height: 60px; 
        border: 1px solid #999; 
        text-align: center; 
        font-size: 1.1rem; 
        color: #333; 
        background-color: #eee;
        position: relative; 
        cursor: pointer; 
        padding: 5px; 
        vertical-align: top;
    }
    .calendar td.today { 
        background-color: #ffeb3b !important; 
        font-weight: bold; 
        color: black; 
        border: 1px solid #fbc02d; 
    }
    .event-count { 
        position: absolute; 
        top: 2px; 
        right: 2px; 
        background: #e74c3c; 
        color: white; 
        border-radius: 50%; 
        width: 18px; 
        height: 18px; 
        font-size: 0.7rem; 
        line-height: 18px; 
        font-weight: bold; 
    }
    .event-item { 
        margin-bottom: 6px; 
        padding: 6px; 
        border-left: 3px solid; 
        background: rgba(255,255,255,0.7); 
        border-radius: 3px; 
    }
    .month-title { 
        font-size: 1.8rem; 
        margin-bottom: 10px; 
        color: #222; 
        text-shadow: 1px 1px 2px rgba(255,255,255,0.8); 
    }
    .button-container { 
        display: flex; 
        justify-content: flex-end; 
        width: 100%; 
        max-width: 600px; 
        margin-top: 20px; 
    }
    .btn-back { 
        background-color: #6c757d; 
        color: white; 
        border: none; 
        padding: 10px 20px; 
        font-size: 1.1rem; 
        border-radius: 5px; 
        transition: all 0.3s; 
    }
    .btn-back:hover { 
        background-color: #5a6268; 
        transform: translateY(-2px); 
    }
</style>
</head>

<body id="page-top">
<!-- Page Wrapper -->
<div id="wrapper">

    <!-- ======= SIDEBAR - 100% IDENTICAL TO DASHBOARD ======= -->
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

        <!-- Brand -->
        <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center" href="faculty_dashboard.php" style="padding:1.5rem 1rem;height:auto;">
            <img src="img/logo.jpg" alt="Logo" class="rounded-circle mb-2" style="width:70px;height:70px;object-fit:cover;">
            <div class="sidebar-brand-text">FACULTY</div>
        </a>

        <!-- Divider -->
        <hr class="sidebar-divider my-0">

        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link" href="faculty_dashboard.php">
                <i class="fas fa-fw fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading: MY CLASS -->
        <div class="sidebar-heading">MY CLASS</div>

        <!-- My Students -->
        <li class="nav-item">
            <a class="nav-link" href="adviserHandle.php">
                <i class="fas fa-users"></i>
                <span>My Students</span>
            </a>
        </li>

        <!-- Attendance Records -->
        <li class="nav-item">
            <a class="nav-link" href="FacAttRecord.php">
                <i class="fas fa-calendar-check"></i>
                <span>Attendance Records</span>
            </a>
        </li>

        <!-- Student Calendar (Active) -->
        <li class="nav-item active">
            <a class="nav-link" href="faculty_calendar.php">
                <i class="fas fa-calendar"></i>
                <span>Student Calendar</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider">

        <!-- Heading: SOCIAL MEDIA -->
        <div class="sidebar-social-heading">Social Media</div>

        <!-- Facebook -->
        <li class="nav-item">
            <a class="nav-social-link" href="https://web.facebook.com/DepEdTayoSINHS301394.official/?_rdc=1&_rdr#" target="_blank">
                <i class="fab fa-facebook-f"></i>
                <span>Facebook</span>
            </a>
        </li>

        <!-- YouTube -->
        <li class="nav-item">
            <a class="nav-social-link" href="https://www.youtube.com/channel/UCDw3mhzSTm_NFk_2dFbhBKg" target="_blank">
                <i class="fab fa-youtube"></i>
                <span>YouTube</span>
            </a>
        </li>

        <!-- Google -->
        <li class="nav-item">
            <a class="nav-social-link" href="https://ph.search.yahoo.com/search" target="_blank">
                <i class="fab fa-google"></i>
                <span>Google</span>
            </a>
        </li>

        <!-- Divider -->
        <hr class="sidebar-divider d-none d-md-block">
    </ul>
    <!-- End of Sidebar -->

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">

        <!-- Main Content -->
        <div id="content">

            <!-- ======= TOPBAR - 100% IDENTICAL TO DASHBOARD ======= -->
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
                    
                    <!-- Logout -->
                    <li class="nav-item ml-3">
                        <a class="logout-link" href="#" data-toggle="modal" data-target="#logoutModal">
                            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2"></i>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
            <!-- End of Topbar -->

            <!-- Calendar Content -->
            <div class="container-fluid">
                <div class="calendar-wrapper">
                    <div class="text-center">
                        <div class="clock-label">Current Philippine Date and Time:</div>
                        <div class="clock-time" id="clock">Loading...</div>
                    </div>
                    <div class="month-title"><?php echo "$monthName $year"; ?></div>

                    <table class="calendar">
                        <thead>
                            <tr><?php foreach ($weekDays as $weekDay) { echo "<th>$weekDay</th>"; } ?></tr>
                        </thead>
                        <tbody>
                            <tr>
                            <?php
                            for ($i = 0; $i < $startDay; $i++) { echo "<td></td>"; }
                            $dayOfWeek = $startDay;
                            for ($day = 1; $day <= $totalDays; $day++, $dayOfWeek++) {
                                $currentDate = sprintf("%04d-%02d-%02d", $year, $month, $day);
                                $isToday = ($day == $today);
                                $eventCount = isset($allEvents[$currentDate]) ? count($allEvents[$currentDate]) : 0;
                            ?>
                                <td class="<?= $isToday ? 'today' : '' ?> event-cell"
                                    data-date="<?= $currentDate ?>"
                                    data-event-count="<?= $eventCount ?>">
                                    <div class="day-number" style="font-size:1.2em; font-weight:bold;"><?= $day ?></div>

                                    <?php if ($eventCount > 0): ?>
                                        <div class="event-count" title="<?= $eventCount ?> Event(s)"><?= $eventCount ?></div>
                                        <div class="event-list d-none" id="events-<?= $currentDate ?>">
                                            <?php foreach ($allEvents[$currentDate] as $event): ?>
                                                <div class="event-item" style="border-left-color: <?= htmlspecialchars($event['color']) ?>">
                                                    <div><strong><?= htmlspecialchars($event['title']) ?></strong></div>
                                                    <div><?= htmlspecialchars($event['description'] ?: 'No description') ?></div>
                                                    <?php
                                                        $startDT = date('M d, Y h:i A', strtotime($event['start_date'] . ' ' . $event['start_time']));
                                                        $endDT   = date('M d, Y h:i A', strtotime($event['end_date']   . ' ' . $event['end_time']));
                                                    ?>
                                                    <div class="small text-muted mt-1">
                                                        <div><strong>Start:</strong> <?= htmlspecialchars($startDT) ?></div>
                                                        <div><strong>End:</strong> <?= htmlspecialchars($endDT) ?></div>
                                                        <div><strong>Location:</strong> <?= htmlspecialchars($event['location'] ?: 'N/A') ?></div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            <?php
                                if ($dayOfWeek == 6) {
                                    if ($day < $totalDays) echo "</tr><tr>";
                                    $dayOfWeek = -1;
                                }
                            }
                            if ($dayOfWeek >= 0) {
                                for ($i = $dayOfWeek + 1; $i < 7; $i++) { echo "<td></td>"; }
                            }
                            ?>
                            </tr>
                        </tbody>
                    </table>

                    <div class="button-container">
                        <button class="btn-back" onclick="window.location.href='faculty_dashboard.php'"><strong>← Back to Dashboard</strong></button>
                    </div>
                </div>

                <!-- Event Details Modal -->
                <div class="modal fade" id="eventDetailModal" tabindex="-1" aria-labelledby="eventDetailLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="eventDetailLabel"><i class="fas fa-info-circle me-2"></i>Event Details for <span id="detailDate"></span></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body"><div id="modalEventContent"></div></div>
                            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <!-- End of Content Wrapper -->

</div>
<!-- End of Page Wrapper -->

<!-- ======= LOGOUT MODAL - SAME AS DASHBOARD ======= -->
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

<!-- Scripts -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Real-time clock for Philippine Time
    let phTime = new Date('<?php echo $currentDateTimeJS; ?>');
    function updateClock() {
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const dateString = phTime.toLocaleDateString('en-US', dateOptions);
        const timeString = phTime.toLocaleTimeString('en-US', timeOptions);
        document.getElementById('clock').textContent = `${dateString.replace(',', ' |')} at ${timeString}`;
        phTime.setSeconds(phTime.getSeconds() + 1);
    }
    updateClock();
    setInterval(updateClock, 1000);

    $(document).ready(function() {
        $('.event-cell').on('click', function() {
            if ($(this).data('event-count') > 0) {
                $('#detailDate').text($(this).data('date'));
                $('#modalEventContent').html($(this).find('.event-list').html());
                const detailModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
                detailModal.show();
            }
        });
    });
</script>
</body>
</html>