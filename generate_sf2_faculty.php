<?php
/* --------------------------------------------------------------
 * generate_sf2_adviser.php – SF2 PDF Generator (Adviser version)
 * -------------------------------------------------------------- */

session_start();                     // <-- must be first
ob_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/fpdf/fpdf.php';

/* --------------------------------------------------------------
 * 2️⃣  Session & authorisation
 * -------------------------------------------------------------- */
if (!isset($_SESSION['adviser_logged_in'], $_SESSION['adviser_id'])) {
    // If you are still using faculty-login keys, replace the above with
    // if (!isset($_SESSION['faculty_logged_in'], $_SESSION['faculty_id'])) { … }
    ob_end_clean();
    $err = new FPDF();
    $err->AddPage();
    $err->SetFont('Arial', 'B', 12);
    $err->Cell(0, 10, 'Error: Access Denied – you are not logged in.', 0, 1, 'C');
    $err->Output();
    exit;
}

/* -----------------------------------------------------------------
 * 3️⃣  Database connection (must exist before any $pdo usage)
 * ----------------------------------------------------------------- */
$host = 'localhost';
$db   = 'rfid_capstone';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    ob_end_clean();
    die('DB connection error: ' . $e->getMessage());
}

/* -----------------------------------------------------------------
 * 4️⃣  Pull the logged-in user’s employee ID
 * ----------------------------------------------------------------- */
$loggedInEmployeeId = $_SESSION['adviser_id'];   // change to 'faculty_id' if you use faculty keys

error_log('Logged-in employee_id (from session): ' . $loggedInEmployeeId);

$stmtFac = $pdo->prepare(
    "SELECT employee_id
     FROM faculty_login   -- change to adviser_login if you have a separate table
     WHERE faculty_id = :fid"  
);
$stmtFac->execute(['fid' => $loggedInEmployeeId]);
$faculty = $stmtFac->fetchColumn();   // now guaranteed to be a scalar or null
error_log('Fetched employee_id from faculty_login: ' . $faculty);
/* ----------------------------------------------------------------- */

/* --------------------------------------------------------------
 * 5️⃣  INPUT parameters (GET)
 * -------------------------------------------------------------- */
$section_id  = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$month_ym    = isset($_GET['month']) ? preg_replace('/[^0-9-]/', '', $_GET['month']) : date('Y-m');
$school_year = trim($_GET['school_year'] ?? '2025-2026');

/* default “school” values – change if you need them dynamic */
$school_id   = '301394';
$school_name = 'SAN ISIDRO NATIONAL HIGH SCHOOL';
$head_name   = 'ELENITA BUSA BELARE';

/* --------------------------------------------------------------
 * 6️⃣  Section-adviser verification
 * -------------------------------------------------------------- */
$stmtSec = $pdo->prepare(
    "SELECT s.section_name,
            s.grade_level,
            s.employee_id      AS section_adviser_id,
            CONCAT(a.firstname,' ',a.lastname) AS adviser_name
     FROM sections s
     LEFT JOIN advisers a ON s.employee_id = a.employee_id
     WHERE s.section_id = :sid"
);
$stmtSec->execute(['sid' => $section_id]);
$secRow = $stmtSec->fetch();

error_log('Section row: ' . json_encode($secRow, JSON_PRETTY_PRINT));

if (!$secRow || $secRow['section_adviser_id'] !== $faculty) {
    ob_end_clean();
    $err = new FPDF();
    $err->AddPage();
    $err->SetFont('Arial', 'B', 12);
    $err->Cell(0, 10, 'Error: You are not authorised to view this report.', 0, 1, 'C');
    $err->Output();
    exit;
}

/* Pull values for later use */
$section_name    = $secRow['section_name'];
$grade_level_val = $secRow['grade_level'];
$adviser_name    = $secRow['adviser_name'] ?? 'NOT ASSIGNED';

/* --------------------------------------------------------------
 * 7️⃣  Calendar helpers (unchanged from your original code)
 * -------------------------------------------------------------- */
$Y = (int)substr($month_ym, 0, 4);
$M = (int)substr($month_ym, 5, 2);
$month_name = date('F', mktime(0, 0, 0, $M, 1, $Y));
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $M, $Y);

$school_days = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    if (date('N', strtotime("$Y-$M-$d")) <= 5) $school_days[] = $d;   // Mon-Fri only
}
$num_school_days = count($school_days);

/* --------------------------------------------------------------
 * 8️⃣  Student list (male/female split) – unchanged
 * -------------------------------------------------------------- */
$stmtSt = $pdo->prepare(
    "SELECT s.lrn,
            CONCAT(s.lastname, ', ', s.firstname) AS name,
            s.sex
     FROM students s
     JOIN enrollments e ON s.lrn = e.lrn
     WHERE e.section_name = :sname AND e.school_year = :sy
     ORDER BY s.sex DESC, s.lastname ASC"
);
$stmtSt->execute(['sname' => $section_name, 'sy' => $school_year]);
$all_students = $stmtSt->fetchAll();

$male    = array_filter($all_students, fn($s) => strtolower($s['sex']) === 'male');
$female  = array_filter($all_students, fn($s) => strtolower($s['sex']) === 'female');

/* --------------------------------------------------------------
 * 9️⃣  Attendance mapping (present / late vs. absent) – unchanged
 * -------------------------------------------------------------- */
$presence = [];
$stmtAtt = $pdo->prepare(
    "SELECT lrn, DAY(date) AS day
     FROM attendance
     WHERE DATE_FORMAT(date, '%Y-%m') = :m
       AND status IN ('present','late')"
);
$stmtAtt->execute(['m' => $month_ym]);
while ($r = $stmtAtt->fetch()) {
    $presence[$r['lrn']][$r['day']] = true;   // true = present / late
}

/* --------------------------------------------------------------
 * 10️⃣  Global variables for the PDF header
 * -------------------------------------------------------------- */
$GLOBALS['school_id']          = $school_id;
$GLOBALS['school_year']        = $school_year;
$GLOBALS['month_name']         = $month_name;
$GLOBALS['school_name']        = $school_name;
$GLOBALS['grade_level_val']    = $grade_level_val;
$GLOBALS['section_name']       = $section_name;
$GLOBALS['adviser_name']       = $adviser_name;

/* --------------------------------------------------------------
 * 11️⃣  PDF class (unchanged)
 * -------------------------------------------------------------- */
class SF2PDF_Adviser extends FPDF {
    function Header() {
        if (file_exists('deped_logo.png')) $this->Image('deped_logo.png', 10, 8, 22);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'School Form 2 (SF2) Daily Attendance Report of Learners', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 4,
            '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)', 0, 1, 'C');
        $this->Ln(5);

        $this->SetFont('Arial', '', 8);
        $x = $this->GetX(); $y = $this->GetY();

        // School ID
        $this->SetX(40); $this->Cell(15, 6, 'School ID', 'LTR', 0, 'C');
        $this->Rect(55, $y, 25, 6);
        $this->Cell(25, 6, $GLOBALS['school_id'], 0, 0, 'C');

        // School Year
        $this->SetX(90); $this->Cell(20, 6, 'School Year', 'LTR', 0, 'C');
        $this->Rect(110, $y, 25, 6);
        $this->Cell(25, 6, $GLOBALS['school_year'], 0, 0, 'C');

        // Report for the month
        $this->SetX(150); $this->Cell(35, 6, 'Report for the Month of', 'LTR', 0, 'C');
        $this->Rect(185, $y, 30, 6);
        $this->Cell(30, 6, $GLOBALS['month_name'], 0, 1, 'C');

        $this->Ln(2);

        // Name of School
        $this->SetX(30); $this->Cell(25, 6, 'Name of School', 'LBR', 0, 'L');
        $this->Rect(55, $y, 90, 6);
        $this->Cell(90, 6, $GLOBALS['school_name'], 0, 0, 'L');

        // Grade Level
        $this->SetX(160); $this->Cell(20, 6, 'Grade Level', 'LTR', 0, 'C');
        $this->Rect(180, $y, 15, 6);
        $this->Cell(15, 6, $GLOBALS['grade_level_val'], 0, 0, 'C');

        // Section
        $this->SetX(210); $this->Cell(12, 6, 'Section', 'LTR', 0, 'C');
        $this->Rect(222, $y, 40, 6);
        $this->Cell(40, 6, $GLOBALS['section_name'], 0, 1, 'C');

        $this->Ln(4);
    }
}

/* --------------------------------------------------------------
 * 12️⃣  Instantiate PDF
 * -------------------------------------------------------------- */
$pdf = new SF2PDF_Adviser('L', 'mm', 'A4');
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetMargins(7, 10, 7);
$pdf->AddPage();

/* --------------------------------------------------------------
 * 13️⃣  PDF metadata (optional but handy)
 * -------------------------------------------------------------- */
$pdf->SetTitle("SF2 – {$section_name} – {$month_name}");
$pdf->SetAuthor('Adviser Dashboard');
$pdf->SetCreator('Upstage AI');
$pdf->AliasNbPages();

/* --------------------------------------------------------------
 * 14️⃣  Table layout (unchanged)
 * -------------------------------------------------------------- */
$name_w = 60;
$day_w  = 5.2;
$stat_w = 18;
$rem_w  = 285 - $name_w - ($day_w * $num_school_days) - $stat_w;

$pdf->SetFont('Arial', 'B', 6);
$x_start = $pdf->GetX(); $y_start = $pdf->GetY();
$pdf->Cell($name_w, 10, "LEARNER'S NAME", 'LTR', 0, 'C');
$pdf->Cell($day_w * $num_school_days, 5, '(first row = date)', 1, 0, 'C');
$pdf->SetFont('Arial', 'B', 5);
$pdf->MultiCell($stat_w, 2.5, "Total for the\nMonth", 1, 'C');
$pdf->SetXY($x_start + $name_w + $day_w * $num_school_days + $stat_w, $y_start);
$pdf->SetFont('Arial', 'B', 4.2);
$pdf->MultiCell($rem_w, 2.5,
    "REMARKS (if DROPPED OUT, state reason,\n refer to legend number 2. If\nTRANSFERRED IN/OUT, write the name\n of School.)", 1, 'C');
$pdf->SetXY($x_start, $y_start + 5);
$pdf->SetFont('Arial', '', 5);
$pdf->Cell($name_w, 5,
    "(Last Name, First Name, Middle Name)", 'LBR', 0, 'C');

$pdf->SetFont('Arial', 'B', 6);
foreach ($school_days as $d) {
    $pdf->Cell($day_w, 5, $d, 1, 0, 'C');
}
$pdf->SetFont('Arial', 'B', 4.5);
$pdf->Cell($stat_w / 2, 5, 'ABSENT', 1, 0, 'C');
$pdf->Cell($stat_w / 2, 5, 'TARDY', 1, 1, 'C');

/* --------------------------------------------------------------
 * 15️⃣  Helper: draw a single student row (male / female)
 * -------------------------------------------------------------- */
function drawStudentList($pdf, $label, $list, $presence, $school_days,
                        $name_w, $day_w, $stat_w, $rem_w, &$tally) {
    if (empty($list)) return;
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell($name_w, 4, $label, 1, 0, 'L');
    $pdf->Cell(($day_w * count($school_days)) + $stat_w + $rem_w, 4, '', 1, 1);
    $pdf->SetFont('Arial', '', 6);
    $count = 1;
    foreach ($list as $s) {
        $pdf->Cell($name_w, 3.5, $count++ . '. ' . substr($s['name'], 0, 35), 1, 0);
        $abs = 0;
        foreach ($school_days as $idx => $day) {
            if (!isset($presence[$s['lrn']][$day])) {
                $pdf->Cell($day_w, 3.5, 'x', 1, 0, 'C'); $abs++;
            } else {
                $pdf->Cell($day_w, 3.5, '', 1, 0, 'C'); $tally[$idx]++;
            }
        }
        $pdf->Cell($stat_w / 2, 3.5, $abs ?: '', 1, 0, 'C');
        $pdf->Cell($stat_w / 2, 3.5, '', 1, 0, 'C');
        $pdf->Cell($rem_w, 3.5, '', 1, 1);
    }
}

/* --------------------------------------------------------------
 * 16️⃣  Total rows (Male / Female / Combined)
 * -------------------------------------------------------------- */
$m_tally = array_fill(0, $num_school_days, 0);
$f_tally = array_fill(0, $num_school_days, 0);

drawStudentList($pdf, 'MALE',   $male,   $presence, $school_days, $name_w, $day_w, $stat_w, $rem_w, $m_tally);
drawStudentList($pdf, 'FEMALE', $female, $presence, $school_days, $name_w, $day_w, $stat_w, $rem_w, $f_tally);

/* Male totals */
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell($name_w, 4, 'MALE TOTAL PER DAY', 1, 0, 'C');
for ($i = 0; $i < $num_school_days; $i++) $pdf->Cell($day_w, 4, $m_tally[$i] ?: '', 1, 0, 'C');
$pdf->Cell($stat_w + $rem_w, 4, '', 1, 1);

/* Female totals */
$pdf->Cell($name_w, 4, 'FEMALE TOTAL PER DAY', 1, 0, 'C');
for ($i = 0; $i < $num_school_days; $i++) $pdf->Cell($day_w, 4, $f_tally[$i] ?: '', 1, 0, 'C');
$pdf->Cell($stat_w + $rem_w, 4, '', 1, 1);

/* Combined total */
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell($name_w, 4, 'Combined TOTAL PER DAY', 1, 0, 'C');
$combined_total = 0;
for ($i = 0; $i < $num_school_days; $i++) {
    $daily = $m_tally[$i] + $f_tally[$i];
    $combined_total += $daily;
    $pdf->Cell($day_w, 4, $daily ?: '', 1, 0, 'C');
}
$pdf->Cell($stat_w + $rem_w, 4, '', 1, 1);


/* --------------------------------------------------------------
 * 17️⃣  Footer – Guidelines, Codes, Summary
 * -------------------------------------------------------------- */
$pdf->Ln(2);
$currX = $pdf->GetX(); $currY = $pdf->GetY();

/* Guidelines */
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell(80, 4, 'GUIDELINES:', 0, 1);
$pdf->SetFont('Arial', '', 5);
$guidelines = "1. The attendance shall be accomplished daily. Refer to the codes for checking learners' attendance.\n"
             . "2. Dates shall be written in the columns after Learner's Name.\n"
             . "3. To compute the following:\n"
             . "   a. Percentage of Enrolment = (Registered Learners / Enrolment 1st Friday) × 100\n"
             . "   b. Average Daily Attendance = Total Daily Attendance ÷ Number of School Days\n"
             . "   c. Percentage of Attendance = (Average Daily Attendance ÷ Registered Learners) × 100\n"
             . "4. Every end of the month, the class adviser will submit this form to the office of the principal.\n"
             . "5. The adviser will provide necessary interventions for learners at risk of dropping out.\n"
             . "6. Attendance performance will be reflected in Form 137 and Form 138.";
$pdf->MultiCell(85, 2.5, $guidelines, 0, 'L');

/* Attendance codes */
$pdf->SetXY($currX + 95, $currY);
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell(70, 4, '1. CODES FOR CHECKING ATTENDANCE', 0, 1);
$pdf->SetFont('Arial', '', 5);
$pdf->Cell(
    70,
    3,
    '(blank) – Present; (x) – Absent; Tardy (upper for Late, lower for Cutting Classes)',
    0,
    1
);
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell(70, 4, '2. REASONS/CAUSES FOR DROPPING OUT', 0, 1);
$pdf->SetFont('Arial', '', 5);
$reasons = "a. Domestic-Related Factors (Siblings, Marriage, Parents' attitude, Family problems)\n"
          . "b. Individual-Related Factors (Illness, Overage, Death, Drug Abuse, Poor performance)\n"
          . "c. School-Related Factors (Teacher, Classroom condition, Peer influence)\n"
          . "d. Geographic/Environmental (Distance, Armed conflict, Calamities)\n"
          . "e. Financial-Related (Child labor, work)\n"
          . "f. Others (Specify)";
$pdf->MultiCell(75, 2.5, $reasons, 0, 'L');

/* Summary table */
$pdf->SetXY($currX + 180, $currY);
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell(45, 4, 'Month: ' . $month_name, 1, 0, 'L');
$pdf->Cell(30, 4,);