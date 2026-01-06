<?php
// Start output buffering to prevent any accidental output before headers
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
            overflow-y: auto;
        }
        /* Container Card Styling */
        .container {
            background-color: #fff;
            width: 100%;
            max-width: 700px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-top: 6px solid #4e73df;
            overflow: hidden;
        }
        .header-section {
            padding: 20px 30px 10px 30px;
            background-color: #fff;
            border-bottom: 1px solid #eee;
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
        .back-btn:hover { background-color: #eaecf4; color: #2e59d9; }

        h1 { color: #2e384d; text-align: center; font-weight: 700; font-size: 1.25rem; margin: 6px 0 12px; }

        .form-content {
            padding: 20px 30px;
            overflow-y: auto;
            flex-grow: 1;
        }
        .form-group { margin-bottom: 12px; }
        label { display: block; margin-bottom: 6px; color: #5a5c69; font-weight: 600; font-size: 0.85em; }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e6ed;
            border-radius: 6px;
            font-size: 0.95em;
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
        input[readonly] { background-color: #eaecf4; cursor: not-allowed; color: #858796; }

        .button-section {
            padding: 15px 30px 20px 30px;
            background-color: #fff;
            border-top: 1px solid #eee;
        }
        button {
            width: 100%;
            padding: 12px;
            background-color: #4e73df;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover { background-color: #2e59d9; }
        button:disabled { background-color: #bdc3c7; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header-section">
        <div class="back-btn-container" style="margin-bottom:6px;">
            <a href="admin_dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
        </div>
        <h1>Generate SF2 Report</h1>
    </div>

    <!-- Form -->
    <div class="form-content">
        <!-- Updated action to point to the exact PDF generator -->
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
            <!-- Added hidden input to capture adviser name for signature -->
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

            <!-- Button inside the form to ensure proper submission -->
            <button type="submit" id="generateBtn" disabled>Generate PDF</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const schoolYearSelect = document.getElementById('school_year');
    const employeeSelect = document.getElementById('employee_id');
    const sectionSelect = document.getElementById('section_id');
    const gradeLevelInput = document.getElementById('grade_level');
    const monthInput = document.getElementById('month');
    const generateBtn = document.getElementById('generateBtn');
    const adviserNameHidden = document.getElementById('adviser_name_hidden');
    const form = document.getElementById('sf2Form');

    function checkFormCompletion() {
        const schoolYear = schoolYearSelect.value;
        const employeeId = employeeSelect.value;
        const sectionId = sectionSelect.value;
        const monthVal = monthInput.value;
        generateBtn.disabled = !(schoolYear && employeeId && sectionId && monthVal);
    }

    // New function to capture adviser name for the PDF
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
        adviserNameHidden.value = ''; // Reset adviser name
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

    // Initial setup
    checkFormCompletion();
});
</script>
</body>
</html>
<?php
?>