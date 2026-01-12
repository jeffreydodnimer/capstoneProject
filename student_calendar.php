<?php
/* --------------------------------------------------------------
 * Admin Calendar with Event Scheduling + Modal View + Delete + EDIT
 * -------------------------------------------------------------- */
session_start();

// --- 1. DATABASE & AUTHENTICATION ---
require_once 'conn.php'; // Ensure this defines $conn

if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

$email = $_SESSION['email'];
$sql = "SELECT * FROM users WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// --- 2. HANDLE AJAX DELETE REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $eventId = intval($_POST['event_id'] ?? 0);
    $date    = $_POST['date'] ?? '';
    $ok = false;
    $msg = 'Event not found or delete failed.';
    if ($eventId > 0) {
        $del = $conn->prepare("DELETE FROM events WHERE id = ?");
        $del->bind_param("i", $eventId);
        if ($del->execute() && $del->affected_rows > 0) {
            $ok = true;
            $msg = 'Deleted';
        }
        $del->close();
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => $ok, 'message' => $msg, 'event_id' => $eventId, 'date' => $date]);
    exit;
}

// --- 3. EVENT FORM HANDLING & VALIDATION (ADD + EDIT) ---
$errors = [];
$success = '';
$formData = [];
$redirectEventId = null; // Track event ID for redirect after update

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_event']) || isset($_POST['edit_event']))) {
    $formData['title']       = trim($_POST['title']);
    $formData['description'] = trim($_POST['description']);
    $formData['start_date']  = $_POST['start_date'];
    $formData['start_time']  = $_POST['start_time'];
    $formData['end_date']    = $_POST['end_date'];
    $formData['end_time']    = $_POST['end_time'];
    $formData['location']    = trim($_POST['location']);
    $formData['color']       = preg_replace('/[^#a-fA-F0-9]/', '', $_POST['color']) ?: '#ff5722';

    if (!$formData['title'])      $errors[] = "Title is required.";
    if (!$formData['start_date']) $errors[] = "Start date is required.";
    if (!$formData['start_time']) $errors[] = "Start time is required.";
    if (!$formData['end_date'])   $errors[] = "End date is required.";
    if (!$formData['end_time'])   $errors[] = "End time is required.";

    $start_datetime = $formData['start_date'] . ' ' . $formData['start_time'];
    $end_datetime   = $formData['end_date'] . ' ' . $formData['end_time'];
    if (strtotime($start_datetime) >= strtotime($end_datetime)) {
        $errors[] = "End time must be later than start time.";
    }

    if (empty($errors)) {
        if (isset($_POST['edit_event']) && isset($_POST['event_id'])) {
            // UPDATE EVENT
            $eventId = intval($_POST['event_id']);
            $redirectEventId = $eventId; // Store for redirect
            $stmt = $conn->prepare(
                "UPDATE events SET title=?, description=?, start_date=?, start_time=?, end_date=?, end_time=?, location=?, color=? WHERE id=?"
            );
            $stmt->bind_param(
                "ssssssssi",
                $formData['title'], $formData['description'], $formData['start_date'],
                $formData['start_time'], $formData['end_date'], $formData['end_time'],
                $formData['location'], $formData['color'], $eventId
            );
        } else {
            // ADD NEW EVENT
            $stmt = $conn->prepare(
                "INSERT INTO events (title, description, start_date, start_time, end_date, end_time, location, color) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssssss",
                $formData['title'], $formData['description'], $formData['start_date'],
                $formData['start_time'], $formData['end_date'], $formData['end_time'],
                $formData['location'], $formData['color']
            );
        }
        
        if ($stmt->execute()) {
            if (isset($_POST['edit_event'])) {
                // Redirect to same page with success param to close modal
                header("Location: ?edit_success=1");
                exit;
            } else {
                $success = "Event created successfully!";
                $formData = [];
            }
        } else {
            $errors[] = "Database error occurred.";
        }
        $stmt->close();
    }
}

// --- 4. HANDLE EDIT EVENT LOAD ---
// When clicking the edit button, the page is reloaded with a GET parameter
$editEventData = null;
if (isset($_GET['edit_event']) && is_numeric($_GET['edit_event'])) {
    $eventId = intval($_GET['edit_event']);
    $stmt = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->bind_param("i", $eventId);
    $stmt->execute();
    $result = $stmt->get_result();
    $editEventData = $result->fetch_assoc();
    $stmt->close();
    
    if (!$editEventData) {
        $errors[] = "Event not found.";
    }
}

// --- 5. SHOW SUCCESS MESSAGE FROM REDIRECT ---
if (isset($_GET['edit_success'])) {
    $success = "Event updated successfully!";
}

// --- 6. CALENDAR LOGIC & EVENT PRE-FETCHING ---
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
$jsEvents = json_encode($allEvents);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<title>Admin Dashboard - Calendar</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<link href="css/sb-admin-2.min.css" rel="stylesheet">
<style>
    /* Sidebar & Calendar Styles */
    #accordionSidebar { background-color: #0022ff !important; background-image: none !important; }
    .sidebar-brand { height: auto !important; padding: 20px 0 !important; flex-direction: column !important; }
    .sidebar-brand-text { margin-top: 10px; font-size: 1.2rem; font-weight: 800; letter-spacing: 1px; text-transform: uppercase; }
    .nav-item .nav-link { color: #ffffff !important; opacity: 1 !important; padding: 12px 1rem; font-weight: 500; }
    .nav-item .nav-link i { color: #ffffff !important; margin-right: 10px; width: 20px; text-align: center; }
    .nav-item .nav-link:hover { background-color: rgba(255, 255, 255, 0.1); }
    .sidebar-heading { color: rgba(255, 255, 255, 0.6) !important; font-size: 0.7rem !important; font-weight: bold !important; text-transform: uppercase; padding-top: 15px; padding-bottom: 5px; }
    hr.sidebar-divider { border-top: 1px solid rgba(255, 255, 255, 0.15) !important; margin: 0 1rem 1rem 1rem !important; }
    #content { background-color: #f8f9fc; padding: 20px; }

    .calendar-wrapper {
        background: linear-gradient(rgba(255,255,255,0.7), rgba(255,255,255,0.7)), url('img/pic6.jpg');
        background-repeat: no-repeat; background-position: center center; background-size: cover;
        min-height: 85vh; display: flex; flex-direction: column; align-items: center; justify-content: center;
        padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    .clock-label { color: #333; font-size: 1.1rem; margin-bottom: 5px; text-shadow: 1px 1px 2px rgba(255,255,255,0.8); }
    .clock-time { font-size: 1.8rem; font-weight: bold; color: #222; margin-bottom: 25px; text-shadow: 1px 1px 2px rgba(255,255,255,0.8); }

    table.calendar { width: 100%; max-width: 600px; border-collapse: collapse; background-color: rgba(255, 255, 255, 0.8); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border: 2px solid #999; }
    .calendar th { background-color: #1565c0; color: white; padding: 15px; font-size: 1.1rem; border: 1px solid #0d47a1; width: 14.28%; }
    .calendar td {
        height: 60px; border: 1px solid #999; text-align: center; font-size: 1.1rem; color: #333; background-color: #eee;
        position: relative; cursor: pointer; padding: 5px; vertical-align: top;
    }
    .calendar td.today { background-color: #ffeb3b !important; font-weight: bold; color: black; border: 1px solid #fbc02d; }

    .event-count {
        position: absolute; top: 2px; right: 2px;
        background: #e74c3c; color: white; border-radius: 50%;
        width: 18px; height: 18px; font-size: 0.7rem; line-height: 18px; font-weight: bold;
    }
    .event-list {
        display: none; 
        position: absolute; top: 100%; left: 0; right: 0;
        background: rgba(255,255,255,0.95); border: 1px solid #ccc; border-radius: 5px;
        padding: 8px; margin-top: 2px; z-index: 1000; max-height: 200px; overflow-y: auto;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15); font-size: 0.85rem;
    }
    .event-item {
        margin-bottom: 6px; padding: 6px; border-left: 3px solid; background: rgba(255,255,255,0.7); border-radius: 3px;
    }
    .event-item:last-child { margin-bottom: 0; }
    
    .month-title { font-size: 1.8rem; margin-bottom: 10px; color: #222; text-shadow: 1px 1px 2px rgba(255,255,255,0.8); }
    .alert { max-width: 600px; margin: 0 auto 20px; }

    /* Buttons at the bottom container */
    .button-container {
        display: flex;
        justify-content: space-between;
        width: 100%;
        max-width: 600px;
        margin-top: 20px;
        margin-left: auto;
        margin-right: auto;
    }
    
    /* Add Event Button - Green/Emerald, same size as Back button */
    .add-event-btn {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%); /* Emerald green */
        color: white;
        border: none;
        padding: 10px 20px;    /* Match Back button size */
        font-size: 1.1rem;     /* Match Back button size */
        font-weight: 600;
        border-radius: 5px;    /* Match Back button shape */
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 6px 14px rgba(16, 185, 129, 0.25);
        transition: transform 0.12s ease, box-shadow 0.2s ease, filter 0.2s ease;
    }
    .add-event-btn i {
        font-size: 1.1rem; /* Match icon size */
    }
    .add-event-btn:hover {
        filter: brightness(1.05);
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(16, 185, 129, 0.32);
    }
    .add-event-btn:active {
        transform: translateY(0);
        box-shadow: 0 4px 10px rgba(16, 185, 129, 0.22);
    }
    .add-event-btn:focus {
        outline: 0;
        box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25);
    }
    
    /* Back Button - Gray */
    .btn-back {
        background-color: #6c757d; /* Gray */
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
    .btn-back:active {
        transform: translateY(0);
    }

    /* Event Action Buttons (Pencil/Trash Icons) */
    .event-action-btn {
        border: none;
        background: transparent;
        font-size: 1.1rem;
        margin: 0 4px;
        padding: 2px;
        transition: transform 0.2s;
        cursor: pointer;
        display: inline-block;
    }
    .edit-event-btn { color: #0d6efd; }
    .edit-event-btn:hover { transform: scale(1.2); color: #0a58ca; background: transparent; }
    .delete-event-btn { color: #dc3545; }
    .delete-event-btn:hover { transform: scale(1.2); color: #b02a37; background: transparent; }

    /* Modal Styles (Copied from previous step for completeness) */
    .modal-content { border: none; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; }
    .modal-header { background: linear-gradient(135deg, #0d47a1 0%, #1565c0 100%); color: #ffffff; border-bottom: none; padding: 20px 25px; }
    .modal-title { font-weight: 700; letter-spacing: 0.5px; }
    .modal-header .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }
    .modal-body { background-color: #ffffff; padding: 25px; }
    .modal-body label { font-weight: 600; color: #495057; font-size: 0.95rem; }
    .modal-footer { background-color: #f8f9fa; border-top: 1px solid #e9ecef; padding: 15px 25px; }
    .form-control:focus { border-color: #1565c0; box-shadow: 0 0 0 0.2rem rgba(21, 101, 192, 0.25); }

    @media (max-width: 768px) {
        .calendar-wrapper { padding: 10px; min-height: 70vh; }
        .clock-time { font-size: 1.4rem; }
        .month-title { font-size: 1.5rem; }
        .calendar td { height: 50px; font-size: 1rem; padding: 3px; }
        .calendar th { padding: 10px; font-size: 1rem; }
        .event-list { font-size: 0.75rem; max-height: 150px; }
        .event-action-btn { font-size: 1rem; }
    }
</style>
</head>
<body id="page-top">
<div id="wrapper">
    <!-- SIDEBAR -->
    <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
        <a class="sidebar-brand d-flex align-items-center justify-content-center" href="admin_dashboard.php">
            <div class="sidebar-brand-icon">
                <img src="img/logo.jpg" alt="Logo" class="rounded-circle"
                     style="width: 80px; height: 80px; object-fit: cover; border: 3px solid white;">
            </div>
            <div class="sidebar-brand-text mx-3">ADMIN</div>
        </a>
        <hr class="sidebar-divider my-2">
        <li class="nav-item"><a class="nav-link" href="admin_dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Student Management</div>
        <li class="nav-item"><a class="nav-link" href="students_list.php"><i class="fas fa-user-graduate"></i><span>Add Student</span></a></li>
        <li class="nav-item"><a class="nav-link" href="guardian.php"><i class="fas fa-home"></i><span>Guardian Of Students</span></a></li>
        <li class="nav-item"><a class="nav-link" href="enrollment.php"><i class="fas fa-pen"></i><span>Enroll Student</span></a></li>
        <li class="nav-item"><a class="nav-link" href="link_rfid.php"><i class="far fa-credit-card"></i><span>Link RFID</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Teachers Management</div>
        <li class="nav-item"><a class="nav-link" href="add_adviser.php"><i class="fas fa-chalkboard-teacher"></i><span>Add Adviser</span></a></li>
        <li class="nav-item"><a class="nav-link" href="section_student.php"><i class="fas fa-book-reader"></i><span>Assign Adviser</span></a></li>
        <hr class="sidebar-divider">
        <div class="sidebar-heading">Social Media</div>
        <li class="nav-item"><a class="nav-link" href="https://web.facebook.com/DepEdTayoSINHS301394.official/?_rdc=1&_rdr#" target="_blank"><i class="fab fa-facebook-f"></i><span>Facebook</span></a></li>
        <li class="nav-item"><a class="nav-link" href="https://www.youtube.com/channel/UCDw3mhzSTm_NFk_2dFbhBKg" target="_blank"><i class="fab fa-youtube"></i><span>YouTube</span></a></li>
        <li class="nav-item"><a class="nav-link" href="https://ph.search.yahoo.com/search?p=san+isidro+national+high+school+padre+burgos+quezon" target="_blank"><i class="fab fa-google"></i><span>Google</span></a></li>
        <hr class="sidebar-divider">
    </ul>

    <!-- Content Wrapper -->
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <!-- Topbar -->
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                    <i class="fa fa-bars"></i>
                </button>
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small">
                                <?php echo htmlspecialchars($user['email']); ?>
                            </span>
                            <img class="img-profile rounded-circle" src="img/profile.svg">
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in"
                            aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="#"><i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>Profile</a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#logoutModal">
                                <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>Logout</a>
                        </div>
                    </li>
                </ul>
            </nav>

            <!-- Calendar Content -->
            <div class="container-fluid">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php foreach($errors as $error): ?>
                            <div><?php echo htmlspecialchars($error); ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

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
                                        <div class="event-list" id="events-<?= $currentDate ?>">
                                            <?php foreach ($allEvents[$currentDate] as $event): ?>
                                                <div class="event-item"
                                                     data-event-id="<?= $event['id'] ?>"
                                                     style="border-left-color: <?= htmlspecialchars($event['color']) ?>">
                                                    <div><strong><?= htmlspecialchars($event['title']) ?></strong></div>
                                                    <div><?= htmlspecialchars($event['description'] ?: 'No description') ?></div>

                                                    <!-- Show full event info (start/end date+time + location) -->
                                                    <?php
                                                        $startDT = date('M d, Y h:i A', strtotime($event['start_date'] . ' ' . $event['start_time']));
                                                        $endDT   = date('M d, Y h:i A', strtotime($event['end_date']   . ' ' . $event['end_time']));
                                                    ?>
                                                    <div class="small text-muted mt-1">
                                                        <div><strong>Start:</strong> <?= htmlspecialchars($startDT) ?></div>
                                                        <div><strong>End:</strong> <?= htmlspecialchars($endDT) ?></div>
                                                        <div><strong>Location:</strong> <?= htmlspecialchars($event['location'] ?: 'N/A') ?></div>
                                                    </div>

                                                    <div class="mt-1 text-end">
                                                        <!-- Edit Button: Uses "fa-pen" for diagonal pencil look -->
                                                        <a href="?edit_event=<?= $event['id'] ?>" class="event-action-btn edit-event-btn" title="Edit Event">
                                                            <i class="fas fa-pen"></i>
                                                        </a>
                                                        <!-- Delete Button: Uses "fa-trash-alt" -->
                                                        <button type="button"
                                                            class="event-action-btn delete-event-btn"
                                                            data-event-id="<?= $event['id'] ?>"
                                                            data-date="<?= $currentDate ?>"
                                                            title="Delete Event">
                                                            <i class="fas fa-trash-alt"></i>
                                                        </button>
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

                    <!-- Button Container - Aligned Left/Right -->
                    <div class="button-container">
                        <!-- Add Event Button - Left Side -->
                        <button class="add-event-btn" data-bs-toggle="modal" data-bs-target="#addEventModal">
                            <i class="fas fa-plus me-2"></i>Add Event
                        </button>

                        <!-- Back Button - Right Side -->
                        <button class="btn-back" onclick="window.location.href='admin_dashboard.php'"><strong>← Back to Dashboard</strong></button>
                    </div>
                </div>

                <!-- Add/Edit Event Modal -->
                <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <form method="POST">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="addEventLabel">
                                        <i class="fas fa-calendar-plus me-2"></i>
                                        <?php echo $editEventData ? 'Edit Event' : 'Add New Event'; ?>
                                    </h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <?php if ($editEventData): ?>
                                        <input type="hidden" name="event_id" value="<?= $editEventData['id'] ?>">
                                    <?php endif; ?>
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" required
                                               value="<?= htmlspecialchars($editEventData['title'] ?? $formData['title'] ?? '') ?>" maxlength="100">
                                    </div>
                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3"
                                                  maxlength="500"><?= htmlspecialchars($editEventData['description'] ?? $formData['description'] ?? '') ?></textarea>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="start_date" class="form-label">Start Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="start_date" name="start_date" required
                                                   value="<?= htmlspecialchars($editEventData['start_date'] ?? $formData['start_date'] ?? date('Y-m-d')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="start_time" name="start_time" required
                                                   value="<?= htmlspecialchars($editEventData['start_time'] ?? $formData['start_time'] ?? date('H:i')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="end_date" class="form-label">End Date <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control" id="end_date" name="end_date" required
                                                   value="<?= htmlspecialchars($editEventData['end_date'] ?? $formData['end_date'] ?? date('Y-m-d')) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                            <input type="time" class="form-control" id="end_time" name="end_time" required
                                                   value="<?= htmlspecialchars($editEventData['end_time'] ?? $formData['end_time'] ?? date('H:i', strtotime('+1 hour'))) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="location" class="form-label">Location</label>
                                            <input type="text" class="form-control" id="location" name="location" maxlength="100"
                                                   value="<?= htmlspecialchars($editEventData['location'] ?? $formData['location'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="color" class="form-label">Color</label>
                                            <input type="color" class="form-control form-control-color" id="color" name="color"
                                                   value="<?= htmlspecialchars($editEventData['color'] ?? $formData['color'] ?? '#ff5722') ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" name="<?= $editEventData ? 'edit_event' : 'add_event' ?>" class="btn btn-success">
                                        <i class="fas fa-save me-2"></i><?= $editEventData ? 'Update Event' : 'Save Event' ?>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Event Details Modal -->
                <div class="modal fade" id="eventDetailModal" tabindex="-1" aria-labelledby="eventDetailLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="eventDetailLabel">
                                    <i class="fas fa-info-circle me-2"></i>Event Details for <span id="detailDate"></span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div id="modalEventContent"></div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- /container-fluid -->
        </div> <!-- /content -->

        <!-- Footer -->
        <footer class="sticky-footer bg-white">
            <div class="container my-auto">
                <div class="copyright text-center my-auto">
                    <span>Copyright &copy; Your Website 2024</span>
                </div>
            </div>
        </footer>
    </div> <!-- /content-wrapper -->
</div> <!-- /wrapper -->

<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

<!-- Logout Modal -->
<div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="exampleModalLabel">Ready to Leave?</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">Select "Logout" below if you are ready to end your current session.</div>
        <div class="modal-footer">
            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
            <a class="btn btn-primary" href="logout.php">Logout</a>
        </div>
    </div></div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/sb-admin-2.min.js"></script>

<script>
    const ALL_MONTH_EVENTS = <?php echo $jsEvents; ?>;

    // Clock
    let phTime = new Date('<?php echo $currentDateTimeJS; ?>');
    function updateClock() {
        const dateOptions = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        const timeOptions = { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true };
        const dateString = phTime.toLocaleDateString('en-US', dateOptions);
        const timeString = phTime.toLocaleTimeString('en-US', timeOptions);
        let formattedDate = dateString.replace(',', ' |');
        document.getElementById('clock').textContent = `${formattedDate} at ${timeString}`;
        phTime.setSeconds(phTime.getSeconds() + 1);
    }
    updateClock();
    setInterval(updateClock, 1000);

    $(document).ready(function() {
        $("#sidebarToggle, #sidebarToggleTop").on('click', function(e) {
            $("body").toggleClass("sidebar-toggled");
            $(".sidebar").toggleClass("toggled");
            if ($(".sidebar").hasClass("toggled")) {
                $('.sidebar .collapse').collapse('hide');
            }
        });
        setTimeout(function() { $('.alert').fadeOut('slow'); }, 5000);

        // Show Event Detail Modal on day click
        $('.event-cell').on('click', function() {
            const date = $(this).data('date');
            const eventCount = $(this).data('event-count');
            if (eventCount > 0) {
                $('#detailDate').text(date);
                const contentHtml = $(this).find('.event-list').html();
                $('#modalEventContent').html(contentHtml || '<div class="text-muted">No events.</div>');
                const detailModal = new bootstrap.Modal(document.getElementById('eventDetailModal'));
                detailModal.show();
            }
        });

        // Delete event (AJAX), updates modal and calendar counts
        $(document).on('click', '.delete-event-btn', function(e) {
            e.stopPropagation();
            const btn = $(this);
            const eventId = btn.data('event-id');
            const date    = btn.data('date');

            if (confirm('Are you sure you want to delete this event?')) {
                $.post(window.location.href, { delete_event: 1, event_id: eventId, date: date }, function(res) {
                    if (res.ok) {
                        // Remove from modal list
                        btn.closest('.event-item').remove();

                        // Remove from hidden calendar list
                        $('#events-' + date + ' .event-item[data-event-id="'+eventId+'"]').remove();

                        // Update counts/badge
                        const cell = $('.event-cell[data-date="'+date+'"]');
                        let count = parseInt(cell.data('event-count'), 10) || 0;
                        count = Math.max(0, count - 1);
                        cell.data('event-count', count);
                        const badge = cell.find('.event-count');
                        if (count === 0) {
                            badge.remove();
                            cell.find('.event-list').remove();
                            $('#modalEventContent').html('<div class="text-muted">No events.</div>');
                        } else {
                            badge.text(count).attr('title', count + ' Event(s)');
                        }
                    } else {
                        alert(res.message || 'Delete failed.');
                    }
                }, 'json').fail(function() {
                    alert('Request failed.');
                });
            }
        });
    });
</script>

<?php
// If in edit mode, auto-open the modal on page load.
if ($editEventData):
?>
<script>
$(document).ready(function() {
    $('#addEventModal').modal('show');
});
</script>
<?php endif; ?>

</body>
</html>