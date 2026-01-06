<?php
// faculty_generate_sf2.php — Form for Faculty to Generate SF2 Report

// --- Start Session and Database Connection ---
session_start();
require 'conn.php'; // Assuming this is your database connection file

if (!isset($_SESSION['faculty_logged_in'])) {
    header("Location: faculty_login.php");
    exit();
}

// Use PDO for database connection for consistency and security
$host = 'localhost';
$db_name = 'rfid_capstone';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Get Faculty and Section Information from Session ---
$faculty_id = (int)$_SESSION['faculty_id'];

// Fetch the sections the faculty member handles
$stmt = $pdo->prepare("
    SELECT s.section_id, s.section_name, s.grade_level
    FROM sections s
    WHERE s.employee_id = ?
    ORDER BY s.grade_level, s.section_name
");
$stmt->execute([$faculty_id]);
$sections = $stmt->fetchAll();

if (empty($sections)) {
    die("No sections assigned to this faculty member.");
}

// Get Report Parameters (Month and School Year) for pre-filling the form
$month_ym = isset($_GET['month']) ? preg_replace('/[^0-9\-]/', '', (string)$_GET['month']) : date('Y-m');
$school_years = $pdo->query("SELECT DISTINCT school_year FROM enrollments ORDER BY school_year DESC")->fetchAll(PDO::FETCH_COLUMN);

// Get faculty name for the hidden input
$stmt = $pdo->prepare("
    SELECT CONCAT(a.firstname, ' ', a.lastname) as employee_name
    FROM advisers a
    WHERE a.employee_id = ?
    LIMIT 1
");
$stmt->execute([$faculty_id]);
$faculty_name = $stmt->fetch()['employee_name'];

// --- HTML FORM LOGIC ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Generate My SF2 Report</title>
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
            border-top: 6px solid #800000; /* Maroon color to match faculty dashboard */
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
            color: #800000; /* Maroon */
            font-weight: 600;
            font-size: 0.85rem;
            padding: 6px 10px;
            border-radius: 4px;
            transition: background-color 0.2s;
        }
        .back-btn:hover { background-color: #eaecf4; color: #600000; }

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
            border-color: #800000; /* Maroon on focus */
            background-color: #fff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(128, 0, 0, 0.1);
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
            background-color: #800000; /* Maroon */
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 700;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        button:hover { background-color: #600000; /* Darker Maroon on Hover */ }
        button:disabled { background-color: #bdc3c7; cursor: not-allowed; }
    </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header-section">
        <div class="back-btn-container" style="margin-bottom:6px;">
            <a href="faculty_dashboard.php" class="back-btn">&larr; Back to Dashboard</a>
        </div>
        <h1>Generate My SF2 Report</h1>
    </div>

    <!-- Form -->
    <div class="form-content">
        <!-- Form action points to the PDF generator, passing necessary parameters -->
        <form action="generate_sf2_faculty.php" method="get" id="sf2Form" target="_blank">
            <!-- Hidden inputs to pass faculty-specific data -->
            <input type="hidden" name="section_id" id="section_id_hidden" value="" />
            <input type="hidden" name="adviser_name" id="adviser_name_hidden" value="<?= htmlspecialchars($faculty_name) ?>" />
            
            <div class="form-group">
                <label for="school_year">School Year:</label>
                <select id="school_year" name="school_year" required>
                    <option value="">Select School Year</option>
                    <?php foreach ($school_years as $year): ?>
                        <option value="<?= htmlspecialchars($year) ?>" <?= ($year == '2025-2026') ? 'selected' : '' ?>><?= htmlspecialchars($year) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="section_id">My Section:</label>
                <select id="section_id" name="section_id" required>
                    <?php foreach ($sections as $sec): ?>
                        <option value="<?= htmlspecialchars($sec['section_id']) ?>">
                            <?= htmlspecialchars($sec['section_name']) ?> (Grade <?= htmlspecialchars($sec['grade_level']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="month">Report for Month:</label>
                <input type="month" id="month" name="month" value="<?= htmlspecialchars($month_ym) ?>" required />
            </div>

            <!-- Button inside the form to ensure proper submission -->
            <button type="submit" id="generateBtn" disabled>Generate PDF</button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const schoolYearSelect = document.getElementById('school_year');
    const sectionSelect = document.getElementById('section_id');
    const monthInput = document.getElementById('month');
    const generateBtn = document.getElementById('generateBtn');
    const form = document.getElementById('sf2Form');

    function checkFormCompletion() {
        const schoolYear = schoolYearSelect.value;
        const sectionId = sectionSelect.value;
        const monthVal = monthInput.value;
        generateBtn.disabled = !(schoolYear && sectionId && monthVal);
    }

    // Initial check and setup
    checkFormCompletion();

    // Re-check when any relevant field changes
    schoolYearSelect.addEventListener('change', checkFormCompletion);
    sectionSelect.addEventListener('change', checkFormCompletion);
    monthInput.addEventListener('change', checkFormCompletion);
});
</script>
</body>
</html>
<?php
?>