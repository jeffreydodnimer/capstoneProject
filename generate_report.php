<?php
// Start output buffering to prevent any accidental output
ob_start();

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

// --- LOGIC ROUTER: Decide whether to show the form or generate the PDF ---
if (isset($_GET['generate_pdf'])) {

    // --- PDF GENERATION LOGIC ---

    require('fpdf/fpdf.php');

    // --- Input Validation ---
    $section_id = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
    $month_year_str = isset($_GET['month']) ? htmlspecialchars(trim($_GET['month'])) : '';
    $school_year = isset($_GET['school_year']) ? htmlspecialchars(trim($_GET['school_year'])) : '';
    $school_id = isset($_GET['school_id']) ? htmlspecialchars(trim($_GET['school_id'])) : 'N/A';
    $school_name = isset($_GET['school_name']) ? htmlspecialchars(trim($_GET['school_name'])) : 'N/A';
    $adviser_name = isset($_GET['adviser_name']) ? htmlspecialchars(trim($_GET['adviser_name'])) : 'N/A';
    $school_head_name = isset($_GET['school_head_name']) ? htmlspecialchars(trim($_GET['school_head_name'])) : 'N/A';

    if (empty($section_id) || empty($month_year_str) || !preg_match('/^\d{4}-\d{2}$/', $month_year_str) || empty($school_year)) {
        ob_end_clean();
        die("Error: Missing or invalid parameters. Please use the form to generate the report.");
    }

    $year = (int)substr($month_year_str, 0, 4);
    $month = (int)substr($month_year_str, 5, 2);
    $month_name = date('F', mktime(0, 0, 0, $month, 1, $year));
    $days_in_month = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    // 1. Get section and student data
    $stmt_section = $pdo->prepare("SELECT section_name, grade_level FROM sections WHERE section_id = :section_id");
    $stmt_section->execute(['section_id' => $section_id]);
    $section_info = $stmt_section->fetch();
    $section_name = $section_info['section_name'] ?? 'N/A';
    $grade_level = $section_info['grade_level'] ?? 'N/A';

    $stmt_students = $pdo->prepare("
        SELECT s.lrn, CONCAT(s.lastname, ', ', s.firstname, ' ', s.middlename) as name
        FROM students s
        JOIN enrollments e ON s.lrn = e.lrn
        WHERE e.section_id = :section_id AND e.school_year = :school_year
        ORDER BY s.lastname, s.firstname
    ");
    $stmt_students->execute(['section_id' => $section_id, 'school_year' => $school_year]);
    $all_students_raw = $stmt_students->fetchAll();

    if (empty($all_students_raw)) {
        ob_end_clean();
        die("Error: No students are enrolled in Section ID '$section_id' for School Year '$school_year'.");
    }
    
    $total_students = count($all_students_raw);
    $split_point = (int)ceil($total_students / 2);
    $male_students = array_slice($all_students_raw, 0, $split_point);
    $female_students = array_slice($all_students_raw, $split_point);

    // 2. Get attendance records
    $student_lrns = array_column($all_students_raw, 'lrn');
    if (!empty($student_lrns)) {
        $in_placeholders = implode(',', array_fill(0, count($student_lrns), '?'));
        $sql = "
            SELECT lrn, date, status FROM attendance 
            WHERE lrn IN ($in_placeholders) AND DATE_FORMAT(date, '%Y-%m') = ? AND status IN ('present', 'late')
        ";
        $params = array_merge($student_lrns, [$month_year_str]);
        $stmt_attendance = $pdo->prepare($sql);
        $stmt_attendance->execute($params);
        $records = $stmt_attendance->fetchAll();
    } else {
        $records = [];
    }

    $attendance_matrix = [];
    foreach ($records as $record) {
        $day = (int)date('d', strtotime($record['date']));
        $attendance_matrix[$record['lrn']][$day] = ($record['status'] == 'late') ? 'L' : 'P';
    }

    // 3. Get valid school days
    $settings_stmt = $pdo->query("SELECT * FROM time_settings WHERE id = 1 LIMIT 1");
    $settings = $settings_stmt->fetch();
    $allowed_days_map = [
        1 => !empty($settings['allow_mon']), 2 => !empty($settings['allow_tue']),
        3 => !empty($settings['allow_wed']), 4 => !empty($settings['allow_thu']),
        5 => !empty($settings['allow_fri']), 6 => !empty($settings['allow_sat']),
        7 => !empty($settings['allow_sun'])
    ];
    
    class PDF extends FPDF {
        function Header() {
            global $school_name, $school_id, $school_year, $grade_level, $section_name, $month_name;
            if (file_exists('deped_logo.png')) {
                $this->Image('deped_logo.png', 10, 8, 15);
            }
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 5, 'School Form 2 (SF2) Daily Attendance Report of Learners', 0, 1, 'C');
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 4, '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)', 0, 1, 'C');
            $this->Ln(3);
            $this->SetFont('Arial', '', 8);
            $this->SetX(40);
            $this->Cell(20, 5, 'School ID', 1, 0);
            $this->Cell(25, 5, $school_id, 1, 0, 'C');
            $this->Cell(22, 5, 'School Year', 1, 0, 'R');
            $this->Cell(25, 5, $school_year, 1, 0, 'C');
            $this->Cell(40, 5, 'Report for the Month of', 1, 0, 'R');
            $this->Cell(35, 5, $month_name, 1, 1, 'C');
            $this->SetX(40);
            $this->Cell(25, 5, 'Name of School', 1, 0);
            $this->Cell(112, 5, $school_name, 1, 0, 'C');
            $this->Cell(25, 5, 'Grade Level', 1, 0, 'R');
            $this->Cell(15, 5, $grade_level, 1, 0, 'C');
            $this->Cell(20, 5, 'Section', 1, 0, 'R');
            $this->Cell(30, 5, $section_name, 1, 1, 'C');
            $this->Ln(2);
        }
    }
    
    function generateAttendanceTable($pdf, $students, $attendance_matrix, $days_in_month, $year, $month, $allowed_days_map, $gender_label) {
        if(empty($students)) return ['absences' => array_fill(1, $days_in_month, 0), 'tardies' => array_fill(1, $days_in_month, 0)];
        
        $name_width = 75; $day_width = 7.5; $total_width = 15; $remarks_width = 45;
        
        $pdf->SetFont('Arial','B',8);
        $pdf->Cell($name_width, 5, "LEARNER'S NAME", 'LTR', 0, 'C');
        $pdf->Cell($day_width * $days_in_month, 5, '(1st row for date)', 1, 0, 'C');
        $pdf->Cell($total_width * 2, 5, 'Total for the Month', 1, 0, 'C');
        $pdf->MultiCell($remarks_width, 5, "REMARKS(If DROPPED OUT, state reason...)", 1, 'C');
        
        $pdf->SetFont('Arial','B',7);
        $pdf->Cell($name_width, 5, '(Last Name, First Name, Middle Name)', 'LRB', 0, 'C');
        foreach (range(1, $days_in_month) as $day) {
             $day_of_week_char = strtoupper(substr(date('l', strtotime("$year-$month-$day")), 0, 2));
             if ($day_of_week_char == 'TH') $day_of_week_char = 'H'; else $day_of_week_char = substr($day_of_week_char, 0, 1);
             $pdf->Cell($day_width, 5, $day_of_week_char, 1, 0, 'C');
        }
        $pdf->Cell($total_width, 5, 'ABSENT', 1, 0, 'C');
        $pdf->Cell($total_width, 5, 'TARDY', 1, 0, 'C');
        $pdf->Cell($remarks_width, 5, '(If TRANSFERRED IN/OUT, write name of School.)', 1, 1, 'C');

        $pdf->SetFont('Arial','B',8);
        $pdf->Cell($name_width, 5, $gender_label, 1, 0, 'L');
        $pdf->Cell($day_width * $days_in_month + ($total_width * 2) + $remarks_width, 5, '', 'TRB', 1);

        $daily_absences = array_fill(1, $days_in_month, 0); $daily_tardies = array_fill(1, $days_in_month, 0);
        $pdf->SetFont('Arial','',8);
        foreach($students as $student){
            $pdf->Cell($name_width, 5, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $student['name']), 1, 0, 'L');
            $total_absent = 0; $total_tardy = 0;
            for($day = 1; $day <= $days_in_month; $day++) {
                $day_of_week_num = (int)date('N', strtotime("$year-$month-$day"));
                if (empty($allowed_days_map[$day_of_week_num])) {
                    $pdf->SetFillColor(200,200,200); $pdf->Cell($day_width, 5, '', 1, 0, 'C', true); continue; 
                }
                $status = $attendance_matrix[$student['lrn']][$day] ?? 'A';
                if($status == 'P') { $pdf->Cell($day_width, 5, '', 1, 0, 'C'); } 
                else if ($status == 'L') { $pdf->Cell($day_width, 5, '•', 1, 0, 'C'); $total_tardy++; $daily_tardies[$day]++; } 
                else { $pdf->Cell($day_width, 5, 'X', 1, 0, 'C'); $total_absent++; $daily_absences[$day]++; }
            }
            $pdf->Cell($total_width, 5, $total_absent > 0 ? $total_absent : '', 1, 0, 'C');
            $pdf->Cell($total_width, 5, $total_tardy > 0 ? $total_tardy : '', 1, 0, 'C');
            $pdf->Cell($remarks_width, 5, '', 1, 1, 'L');
        }
        return ['absences' => $daily_absences, 'tardies' => $daily_tardies];
    }
    
    $pdf = new PDF('L', 'mm', 'Legal');
    $pdf->AddPage();
    
    $male_totals = generateAttendanceTable($pdf, $male_students, $attendance_matrix, $days_in_month, $year, $month, $allowed_days_map, 'MALE');
    $female_totals = generateAttendanceTable($pdf, $female_students, $attendance_matrix, $days_in_month, $year, $month, $allowed_days_map, 'FEMALE');

    $pdf->SetFont('Arial','B',7);
    $name_width = 75; $day_width = 7.5; $total_width = 15; $remarks_width = 45;
    $combined_absences = [];
    
    $pdf->Cell($name_width, 5, 'MALE | TOTAL Per Day', 1, 0, 'R');
    for($day = 1; $day <= $days_in_month; $day++) {
        $abs = $male_totals['absences'][$day] ?? 0;
        $combined_absences[$day] = ($combined_absences[$day] ?? 0) + $abs;
        $pdf->Cell($day_width, 5, $abs > 0 ? $abs : '', 1, 0, 'C');
    }
    $pdf->Cell($total_width*2 + $remarks_width, 5, '', 1, 1);

    $pdf->Cell($name_width, 5, 'FEMALE | TOTAL Per Day', 1, 0, 'R');
    for($day = 1; $day <= $days_in_month; $day++) {
        $abs = $female_totals['absences'][$day] ?? 0;
        $combined_absences[$day] = ($combined_absences[$day] ?? 0) + $abs;
        $pdf->Cell($day_width, 5, $abs > 0 ? $abs : '', 1, 0, 'C');
    }
    $pdf->Cell($total_width*2 + $remarks_width, 5, '', 1, 1);
    
    $pdf->Cell($name_width, 5, 'COMBINED | TOTAL Per Day', 1, 0, 'R');
    for($day = 1; $day <= $days_in_month; $day++) {
        $abs = $combined_absences[$day] ?? 0;
        $pdf->Cell($day_width, 5, $abs > 0 ? $abs : '', 1, 0, 'C');
    }
    $pdf->Cell($total_width*2 + $remarks_width, 5, '', 1, 1);

    ob_end_clean();
    $pdf->Output('I', "SF2_{$section_name}_{$month_year_str}.pdf");
    exit();

} else {

    // --- HTML FORM LOGIC ---
    if (isset($_GET['action']) && $_GET['action'] === 'get_sections') {
        header('Content-Type: application/json');
        $grade_level = $_GET['grade_level'] ?? '';
        $school_year = $_GET['school_year'] ?? '';
        if (empty($grade_level) || empty($school_year)) { exit(json_encode([])); }
        $stmt = $pdo->prepare("
            SELECT DISTINCT sec.section_id, sec.section_name, CONCAT_WS(' ', adv.firstname, adv.lastname) AS adviser_name
            FROM sections sec JOIN enrollments e ON sec.section_id = e.section_id LEFT JOIN advisers adv ON sec.adviser_id = adv.adviser_id
            WHERE e.grade_level = :grade_level AND e.school_year = :school_year ORDER BY sec.section_name ASC
        ");
        $stmt->execute(['grade_level' => $grade_level, 'school_year' => $school_year]);
        exit(json_encode($stmt->fetchAll()));
    }

    $school_years = $pdo->query("SELECT DISTINCT school_year FROM enrollments ORDER BY school_year DESC")->fetchAll(PDO::FETCH_COLUMN);
    $grade_levels = $pdo->query("SELECT DISTINCT grade_level FROM enrollments WHERE grade_level IS NOT NULL AND grade_level != '' ORDER BY grade_level ASC")->fetchAll(PDO::FETCH_COLUMN);
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Generate Attendance Report (SF2)</title>
        <style>
            /* General Reset and Body Styling */
            * { box-sizing: border-box; margin: 0; padding: 0; }
            
            body {
                font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
                background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 10px;
                color: #333;
                overflow-y: auto; /* Allow body scroll if screen is very small */
            }

            /* Container Card Styling */
            .container {
                background-color: #ffffff;
                width: 100%;
                max-width: 600px;
                max-height: 90vh; /* Limit height to 90% of viewport */
                display: flex;
                flex-direction: column;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                border-top: 6px solid #4e73df; /* Top Accent Color */
                overflow: hidden; /* Hide overflow of children */
            }

            /* Header Section - Fixed at top */
            .header-section {
                padding: 20px 30px 10px 30px;
                background-color: #fff;
                flex-shrink: 0;
                border-bottom: 1px solid #eee;
            }

            /* Back Button Styling */
            .back-btn-container {
                margin-bottom: 10px;
            }
            .back-btn {
                display: inline-flex;
                align-items: center;
                text-decoration: none;
                color: #4e73df;
                font-weight: 600;
                font-size: 0.85rem;
                padding: 6px 10px;
                border-radius: 4px;
                transition: background-color 0.2s;
            }
            .back-btn:hover {
                background-color: #eaecf4;
                color: #2e59d9;
            }

            /* Header Styling */
            h1 {
                color: #2e384d;
                margin-bottom: 10px;
                text-align: center;
                font-weight: 700;
                font-size: 1.5rem;
            }

            /* Scrollable Form Content */
            .form-content {
                padding: 20px 30px;
                overflow-y: auto; /* Scroll inside this div */
                flex-grow: 1;
            }

            /* Form Group Styling */
            .form-group {
                margin-bottom: 15px;
            }

            label {
                display: block;
                margin-bottom: 5px;
                color: #5a5c69;
                font-weight: 600;
                font-size: 0.85em;
            }

            select, input[type="text"], input[type="month"] {
                width: 100%;
                padding: 10px 12px;
                border: 2px solid #e0e6ed;
                border-radius: 6px;
                font-size: 0.95em;
                transition: all 0.3s ease;
                background-color: #f8f9fc;
                color: #2e384d;
            }

            /* Focus States */
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

            /* Generate Button Container - Fixed at bottom */
            .button-section {
                padding: 15px 30px 20px 30px;
                background-color: #fff;
                flex-shrink: 0;
                border-top: 1px solid #eee;
            }

            /* Generate Button Styling */
            button {
                width: 100%;
                padding: 12px;
                background-color: #4e73df; /* Primary Blue */
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 1em;
                font-weight: 700;
                cursor: pointer;
                transition: background-color 0.2s;
                box-shadow: 0 4px 6px rgba(78, 115, 223, 0.2);
            }

            button:hover {
                background-color: #2e59d9; /* Darker Blue */
            }

            button:disabled {
                background-color: #bdc3c7;
                cursor: not-allowed;
                box-shadow: none;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <!-- Header Section -->
            <div class="header-section">
                <!-- Back Button -->
                <div class="back-btn-container">
                    <a href="admin_dashboard.php" class="back-btn">
                        &larr; Back to Dashboard
                    </a>
                </div>
                <h1>Generate SF2 Report</h1>
            </div>

            <!-- Scrollable Form Content -->
            <div class="form-content">
                <form action="" method="get" target="_blank">
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
                        <label for="grade_level">Grade Level:</label>
                        <select id="grade_level" name="grade_level" required>
                            <option value="">Select Grade Level</option>
                            <?php foreach ($grade_levels as $grade): ?>
                                <option value="<?= htmlspecialchars($grade) ?>"><?= htmlspecialchars($grade) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="section_id">Section:</label>
                        <select id="section_id" name="section_id" required disabled>
                            <option value="">Select School Year & Grade First</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="adviser_name">Adviser:</label>
                        <input type="text" name="adviser_name" id="adviser_name" readonly required placeholder="Auto-filled upon section selection" />
                    </div>
                    <div class="form-group">
                        <label for="school_head_name">School Head:</label>
                        <input type="text" name="school_head_name" id="school_head_name" value="ELENITA BUSA BELARE" required />
                    </div>
                    <div class="form-group">
                        <label for="month">Report for Month:</label>
                        <input type="month" id="month" name="month" required />
                    </div>
                </form>
            </div>

            <!-- Fixed Button Section -->
            <div class="button-section">
                <button type="submit" id="generateBtn" name="generate_pdf" value="1" disabled>Generate PDF</button>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const schoolYearSelect = document.getElementById('school_year');
            const gradeLevelSelect = document.getElementById('grade_level');
            const sectionSelect = document.getElementById('section_id');
            const adviserInput = document.getElementById('adviser_name');
            const monthInput = document.getElementById('month');
            const generateBtn = document.getElementById('generateBtn');
            // Move button outside form logic or keep it inside. 
            // Since button is in a different div structure now, we attach event listener to form submit or just let button trigger submit since it's inside a form in the HTML structure above, but I moved form tags.
            // Actually, looking at HTML above, form tags wrap the inputs but not the button if I split the divs.
            // Let's fix the HTML structure quickly in the JS logic or just attach click event.
            // Better approach: Ensure the button is type="submit" and is inside the <form> tag.
            
            // Re-structuring the form tag to wrap everything properly:
            const form = document.querySelector('form');
            
            // Let's make sure the button is inside the form by moving it via JS or adjusting HTML.
            // To be safe, I will attach the click listener to submit the form.
            
            generateBtn.onclick = function() {
                form.submit();
            };

            let sectionsCache = [];
            
            function checkFormCompletion() {
                const schoolYear = schoolYearSelect.value;
                const gradeLevel = gradeLevelSelect.value;
                const sectionId = sectionSelect.value;
                const month = monthInput.value;
                generateBtn.disabled = !(schoolYear && gradeLevel && sectionId && month);
            }

            async function updateSections() {
                const gradeLevel = gradeLevelSelect.value;
                const schoolYear = schoolYearSelect.value;
                sectionSelect.innerHTML = '<option value="">Loading...</option>';
                sectionSelect.disabled = true;
                adviserInput.value = '';
                checkFormCompletion();

                if (!gradeLevel || !schoolYear) { sectionSelect.innerHTML = '<option value="">Select Year & Grade</option>'; return; }
                try {
                    const response = await fetch(`?action=get_sections&grade_level=${gradeLevel}&school_year=${schoolYear}`);
                    sectionsCache = await response.json();
                    sectionSelect.innerHTML = '<option value="">Select Section</option>';
                    if (sectionsCache.length > 0) {
                        sectionsCache.forEach(section => {
                            const option = document.createElement('option');
                            option.value = section.section_id;
                            option.textContent = section.section_name;
                            sectionSelect.appendChild(option);
                        });
                        sectionSelect.disabled = false;
                    } else { sectionSelect.innerHTML = '<option value="">No Sections Found</option>'; }
                } catch (error) { console.error('Error fetching sections:', error); sectionSelect.innerHTML = '<option value="">Error Loading</option>'; }
            }

            function updateAdviser() {
                const selectedSectionId = sectionSelect.value;
                adviserInput.value = '';
                if (selectedSectionId && sectionsCache.length > 0) {
                    const selectedSection = sectionsCache.find(s => s.section_id == selectedSectionId);
                    if (selectedSection && selectedSection.adviser_name) { adviserInput.value = selectedSection.adviser_name; } 
                    else { adviserInput.placeholder = "No adviser assigned"; }
                }
                checkFormCompletion();
            }

            schoolYearSelect.addEventListener('change', updateSections);
            gradeLevelSelect.addEventListener('change', updateSections);
            sectionSelect.addEventListener('change', updateAdviser);
            monthInput.addEventListener('change', checkFormCompletion);
            
            checkFormCompletion();
        });
        </script>
    </body>
    </html>
    <?php
}
?>