<?php
// Start output buffering
ob_start();
session_start();

// --- 1. AUTHENTICATION CHECK ---
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

// --- CONFIGURATION & DATABASE CONNECTION ---
$host = 'localhost';
$db_name = 'rfid_capstone';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    ob_end_clean();
    die("Database connection failed: " . $e->getMessage());
}

date_default_timezone_set('Asia/Manila');

// --- FETCH USER DATA FOR NAVBAR ---
$email = $_SESSION['email'];
$stmt_user = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt_user->execute([$email]);
$user = $stmt_user->fetch();

// --- HELPER FUNCTIONS ---
function fetch_employees($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT DISTINCT a.employee_id, CONCAT(a.firstname, ' ', a.lastname) as employee_name
            FROM advisers a
            JOIN sections s ON a.employee_id = s.employee_id
            ORDER BY employee_name ASC
        ");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function fetch_sections_by_employee($pdo, $employee_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT s.section_id, s.section_name, s.grade_level
            FROM sections s
            WHERE s.employee_id = :employee_id
            ORDER BY s.grade_level ASC, s.section_name ASC
        ");
        $stmt->execute(['employee_id' => $employee_id]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// --- GET SECTIONS ENDPOINT (AJAX) ---
if (isset($_GET['action']) && $_GET['action'] === 'get_sections') {
    // Clear buffer before sending JSON to avoid junk output
    ob_end_clean(); 
    header('Content-Type: application/json');
    $employee_id = $_GET['employee_id'] ?? '';

    if (empty($employee_id)) {
        echo json_encode([]);
        exit;
    }

    $sections = fetch_sections_by_employee($pdo, $employee_id);
    echo json_encode($sections);
    exit;
}

// --- HTML FORM LOGIC ---
$employees = fetch_employees($pdo);
$school_years = $pdo->query("SELECT DISTINCT school_year FROM enrollments ORDER BY school_year DESC")->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Generate Attendance Report (SF2)</title>

    <!-- Bootstrap 5 & SB Admin 2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        /* --- SIDEBAR STYLING --- */
        #accordionSidebar {
            background-color: #0011ff !important;
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
            font-weight: 500;
        }
        .nav-item .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar-heading {
            color: rgba(255, 255, 255, 0.6) !important;
            font-weight: bold !important;
            text-transform: uppercase;
        }
        hr.sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15) !important;
        }

        /* --- BACKGROUND STYLING (UPDATED) --- */
        .content-background {
            /* Updated to use cover.jpg */
            background-image: url('img/pic5.jpg'); 
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            min-height: 100vh; /* Full screen height */
            padding: 30px;
            display: flex;
            justify-content: center; /* Center the form card */
            align-items: flex-start;
        }

        /* --- FORM CARD STYLING WITH SCROLLBAR --- */
        .report-card {
            background-color: #fff;
            width: 100%;
            max-width: 700px; 
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            border-top: 6px solid #4e73df;
            margin-top: 20px;
            
            /* Flex Column Layout for Fixed Header + Scrolling Body */
            display: flex;
            flex-direction: column;
            max-height: 80vh; /* Restrict max height to trigger scroll */
        }

        /* Flexbox for Title and Back Button */
        .header-section {
            padding: 20px 30px;
            background-color: #fff;
            border-bottom: 1px solid #eee;
            flex-shrink: 0; 
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header-section h1 { 
            color: #2e384d; 
            font-weight: 700; 
            font-size: 1.5rem; 
            margin: 0;
        }

        /* Scrollable Content Area */
        .form-content {
            padding: 30px;
            overflow-y: auto; /* Enable Vertical Scroll */
            flex-grow: 1;     /* Fill remaining space */
        }

        /* Custom Scrollbar Styling */
        .form-content::-webkit-scrollbar { width: 8px; }
        .form-content::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        .form-content::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        .form-content::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

        .form-group { margin-bottom: 15px; }
        
        label { 
            display: block; 
            margin-bottom: 8px; 
            color: #5a5c69; 
            font-weight: 600; 
            font-size: 0.9em; 
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e6ed;
            border-radius: 6px;
            font-size: 1em;
            transition: all 0.3s ease;
            background-color: #f8f9fc;
            color: #2e384d;
        }

        select:focus, input:focus {
            border-color: #4e73df;
            background-color: #fff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(78, 115, 223, 0.1);
        }

        input[readonly] { 
            background-color: #eaecf4; 
            cursor: not-allowed; 
            color: #858796; 
        }

        button#generateBtn {
            width: 100%;
            padding: 14px;
            background-color: #4e73df;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1.1em;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s;
            margin-top: 10px;
        }

        button#generateBtn:hover { background-color: #2e59d9; }
        button#generateBtn:disabled { background-color: #bdc3c7; cursor: not-allowed; }
    </style>
</head>

<body id="page-top">

    <!-- Page Wrapper -->
    <div id="wrapper">

        <!-- ================= SIDEBAR ================= -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex align-items-center justify-content-center" href="admin_dashboard.php">
                <div class="sidebar-brand-icon">
                    <img src="img/logo.jpg" alt="Logo" class="rounded-circle" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid white;">
                </div>
                <div class="sidebar-brand-text mx-3">ADMIN</div>
            </a>
            <hr class="sidebar-divider my-2">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a>
            </li>
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
                <div class="content-background">
                    
                    <!-- Report Generation Card (SCROLLABLE) -->
                    <div class="report-card">
                        <!-- Header with Back Button -->
                        <div class="header-section">
                            <h1><i class="fas fa-file-alt mr-2 text-primary"></i>Generate SF2 Report</h1>
                            <a href="admin_dashboard.php" class="btn btn-secondary btn-sm shadow-sm">
                                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Dashboard
                            </a>
                        </div>

                        <!-- Form with Scrollbar -->
                        <div class="form-content">
                            <form action="generate_sf2_exact.php" method="get" id="sf2Form" target="_blank">
                                <div class="form-group">
                                    <label for="school_name">School Name:</label>
                                    <input type="text" name="school_name" id="school_name" value="SAN ISIDRO NATIONAL HIGH SCHOOL" required />
                                </div>
                                <div class="form-group">
                                    <label for="school_id">School ID:</label>
                                    <input type="text" name="school_id" id="school_id" value="301394" required />
                                </div>
                                <div class="form-group">
                                    <label for="school_year">School Year:</label>
                                    <select id="school_year" name="school_year" required>
                                        <option value="">Select School Year</option>
                                        <?php foreach ($school_years as $year): ?>
                                            <option value="<?= htmlspecialchars($year) ?>"><?= htmlspecialchars($year) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="employee_id">Adviser:</label>
                                    <select id="employee_id" name="employee_id" required>
                                        <option value="">Select Adviser</option>
                                        <?php foreach ($employees as $employee): ?>
                                            <option value="<?= htmlspecialchars($employee['employee_id']) ?>"><?= htmlspecialchars($employee['employee_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <input type="hidden" name="adviser_name" id="adviser_name_hidden" value="" />
                                
                                <div class="form-group">
                                    <label for="section_id">Section:</label>
                                    <select id="section_id" name="section_id" required disabled>
                                        <option value="">Select Adviser First</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="grade_level">Grade Level:</label>
                                    <input type="text" name="grade_level" id="grade_level" readonly placeholder="Auto-filled upon section selection" />
                                </div>
                                <div class="form-group">
                                    <label for="school_head_name">School Head:</label>
                                    <input type="text" name="school_head_name" id="school_head_name" value="ELENITA BUSA BELARE" required />
                                </div>
                                <div class="form-group">
                                    <label for="month">Report for Month:</label>
                                    <input type="month" id="month" name="month" required />
                                </div>

                                <button type="submit" id="generateBtn" disabled>
                                    <i class="fas fa-file-pdf mr-2"></i>Generate PDF
                                </button>
                            </form>
                        </div>
                    </div>
                    <!-- End Report Card -->

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
    document.addEventListener('DOMContentLoaded', function() {
        const schoolYearSelect = document.getElementById('school_year');
        const employeeSelect = document.getElementById('employee_id');
        const sectionSelect = document.getElementById('section_id');
        const gradeLevelInput = document.getElementById('grade_level');
        const monthInput = document.getElementById('month');
        const generateBtn = document.getElementById('generateBtn');
        const adviserNameHidden = document.getElementById('adviser_name_hidden');

        function checkFormCompletion() {
            const schoolYear = schoolYearSelect.value;
            const employeeId = employeeSelect.value;
            const sectionId = sectionSelect.value;
            const monthVal = monthInput.value;
            generateBtn.disabled = !(schoolYear && employeeId && sectionId && monthVal);
        }

        function updateAdviserName() {
            const selectedOption = employeeSelect.options[employeeSelect.selectedIndex];
            if (selectedOption) {
                adviserNameHidden.value = selectedOption.text;
            } else {
                adviserNameHidden.value = '';
            }
        }

        async function updateSections() {
            const employeeId = employeeSelect.value;
            sectionSelect.innerHTML = '<option value="">Loading...</option>';
            sectionSelect.disabled = true;
            gradeLevelInput.value = '';
            adviserNameHidden.value = ''; 
            checkFormCompletion();

            if (!employeeId) { sectionSelect.innerHTML = '<option value="">Select Adviser</option>'; return; }
            try {
                const response = await fetch(`?action=get_sections&employee_id=${employeeId}`);
                const sections = await response.json();
                sectionSelect.innerHTML = '<option value="">Select Section</option>';
                if (sections.length > 0) {
                    sections.forEach(sec => {
                        const opt = document.createElement('option');
                        opt.value = sec.section_id;
                        opt.textContent = `${sec.section_name} (Grade ${sec.grade_level})`;
                        sectionSelect.appendChild(opt);
                    });
                    sectionSelect.disabled = false;
                } else {
                    sectionSelect.innerHTML = '<option value="">No Sections Found</option>';
                }
            } catch (error) {
                console.error('Error fetching sections:', error);
                sectionSelect.innerHTML = '<option value="">Error Loading</option>';
            }
        }

        function updateGradeLevel() {
            const selectedSection = sectionSelect.options[sectionSelect.selectedIndex];
            if (selectedSection && selectedSection.textContent) {
                const gradeLevelMatch = selectedSection.textContent.match(/\((Grade \d+)\)/);
                if (gradeLevelMatch) {
                    gradeLevelInput.value = gradeLevelMatch[1].replace('Grade ', '');
                } else {
                    gradeLevelInput.value = '';
                }
            } else {
                gradeLevelInput.value = '';
            }
            checkFormCompletion();
        }

        employeeSelect.addEventListener('change', () => {
            updateSections();
            updateAdviserName();
        });
        
        sectionSelect.addEventListener('change', updateGradeLevel);
        monthInput.addEventListener('change', checkFormCompletion);
        schoolYearSelect.addEventListener('change', checkFormCompletion);

        checkFormCompletion();
    });
    </script>
</body>
</html>
<?php include 'footer.php'; ?>