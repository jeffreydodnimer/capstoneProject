<?php
// generate_sf2_exact_layout.php — SF2 PDF Generator (A4 Landscape)
ob_start();

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/fpdf/fpdf.php';

// --- DB config ---
$host = 'localhost';
$db   = 'rfid_capstone';
$user = 'root';
$pass = '';

// --- Inputs ---
$section_id   = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;
$month_ym     = isset($_GET['month']) ? preg_replace('/[^0-9\-]/', '', (string)$_GET['month']) : date('Y-m');
$school_year  = isset($_GET['school_year']) ? trim((string)$_GET['school_year']) : '';
$school_id    = isset($_GET['school_id']) ? trim((string)$_GET['school_id']) : '301394';
$school_name  = isset($_GET['school_name']) ? trim((string)$_GET['school_name']) : 'SAN ISIDRO NATIONAL HIGH SCHOOL';
$adviser_name = isset($_GET['adviser_name']) ? trim((string)$_GET['adviser_name']) : 'TEACHER NAME HERE';
$head_name    = isset($_GET['school_head_name']) ? trim((string)$_GET['school_head_name']) : 'ELENITA BUSA BELARE';

$Y = (int)substr($month_ym, 0, 4);
$M = (int)substr($month_ym, 5, 2);
$month_name = date('F', mktime(0, 0, 0, $M, 1, $Y));
$days_in_month = cal_days_in_month(CAL_GREGORIAN, $M, $Y);

$school_days = [];
for ($d = 1; $d <= $days_in_month; $d++) {
    if (date('N', strtotime("$Y-$M-$d")) <= 5) $school_days[] = $d;
}
$num_school_days = count($school_days);

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    ob_end_clean();
    die("DB connection error: " . $e->getMessage());
}

$sec = $pdo->prepare("SELECT section_name, grade_level FROM sections WHERE section_id = :id");
$sec->execute(['id' => $section_id]);
$secrow = $sec->fetch();
$section_name = $secrow['section_name'] ?? 'N/A';
$grade_level_val = $secrow['grade_level'] ?? 'N/A';

$st = $pdo->prepare("SELECT s.lrn, CONCAT(s.lastname, ', ', s.firstname) AS name, s.sex FROM students s JOIN enrollments e ON s.lrn = e.lrn WHERE e.section_name = :sname AND e.school_year = :sy ORDER BY s.sex DESC, s.lastname ASC");
$st->execute(['sname' => $section_name, 'sy' => $school_year]);
$all_students = $st->fetchAll();

$male = array_filter($all_students, fn($s) => strtolower($s['sex']) == 'male');
$fem  = array_filter($all_students, fn($s) => strtolower($s['sex']) == 'female');

$att = $pdo->prepare("SELECT lrn, DAY(date) as day FROM attendance WHERE DATE_FORMAT(date, '%Y-%m') = :m AND status IN ('present','late')");
$att->execute(['m' => $month_ym]);
$presence = [];
while($r = $att->fetch()) { $presence[$r['lrn']][$r['day']] = true; }

class SF2PDF extends FPDF {
    function Header() {
        if (file_exists('deped_logo.png')) { $this->Image('deped_logo.png', 10, 8, 22); }
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 7, 'School Form 2 (SF2) Daily Attendance Report of Learners', 0, 1, 'C');
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 4, '(This replaces Form 1, Form 2 & STS Form 4 - Absenteeism and Dropout Profile)', 0, 1, 'C');
        $this->Ln(5);
        $this->SetFont('Arial', '', 8);
        $this->SetX(40); $this->Cell(15, 6, 'School ID'); $this->Rect(55, $this->GetY(), 25, 6); $this->Cell(25, 6, $GLOBALS['school_id'], 0, 0, 'C');
        $this->SetX(90); $this->Cell(20, 6, 'School Year'); $this->Rect(110, $this->GetY(), 25, 6); $this->Cell(25, 6, $GLOBALS['school_year'], 0, 0, 'C');
        $this->SetX(150); $this->Cell(35, 6, 'Report for the Month of'); $this->Rect(185, $this->GetY(), 30, 6); $this->Cell(30, 6, $GLOBALS['month_name'], 0, 1, 'C');
        $this->Ln(2);
        $this->SetX(30); $this->Cell(25, 6, 'Name of School'); $this->Rect(55, $this->GetY(), 90, 6); $this->Cell(90, 6, $GLOBALS['school_name'], 0, 0, 'L');
        $this->SetX(160); $this->Cell(20, 6, 'Grade Level'); $this->Rect(180, $this->GetY(), 15, 6); $this->Cell(15, 6, $GLOBALS['grade_level_val'], 0, 0, 'C');
        $this->SetX(210); $this->Cell(12, 6, 'Section'); $this->Rect(222, $this->GetY(), 40, 6); $this->Cell(40, 6, $GLOBALS['section_name'], 0, 1, 'C');
        $this->Ln(4);
    }
}

$pdf = new SF2PDF('L', 'mm', 'A4');
$pdf->SetMargins(7, 10, 7);
$pdf->SetAutoPageBreak(false);
$pdf->AddPage();

$name_w = 60; 
$day_w = 5.2; 
$stat_w = 18; 
$rem_w = 38;

// --- TABLE HEADER ---
$pdf->SetFont('Arial', 'B', 6);
$x_start = $pdf->GetX(); $y_start = $pdf->GetY();
$pdf->Cell($name_w, 10, "LEARNER'S NAME", 'LTR', 0, 'C');
$pdf->Cell($day_w * 25, 5, '(1st row for date)', 1, 0, 'C');
$pdf->SetFont('Arial', 'B', 5);
$pdf->MultiCell($stat_w, 2.5, "Total for the\nMonth", 1, 'C');
$pdf->SetXY($x_start + $name_w + ($day_w * 25) + $stat_w, $y_start);
$pdf->SetFont('Arial', 'B', 4.2);
$pdf->MultiCell($rem_w, 2.5, "REMARKS (if DROPPED OUT, state reason, please refer to legend number 2. If TRANSFERRED IN/OUT, write the name of School.)", 1, 'C');
$pdf->SetXY($x_start, $y_start + 5);
$pdf->SetFont('Arial', '', 5);
$pdf->Cell($name_w, 5, "(Last Name, First Name, Middle Name)", 'LBR', 0, 'C');
$pdf->SetFont('Arial', 'B', 6);
for ($i = 0; $i < 5; $i++) {
    foreach (['M', 'T', 'W', 'TH', 'F'] as $d) $pdf->Cell($day_w, 5, $d, 1, 0, 'C');
}
$pdf->SetFont('Arial', 'B', 4.5);
$pdf->Cell($stat_w / 2, 5, 'ABSENT', 1, 0, 'C');
$pdf->Cell($stat_w / 2, 5, 'TARDY', 1, 1, 'C');

// --- DRAW STUDENT ROWS ---
function drawStudentList($pdf, $label, $list, $presence, $school_days, $name_w, $day_w, $stat_w, $rem_w, &$tally) {
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell($name_w, 4, $label, 1, 0, 'L');
    $pdf->Cell(($day_w * 25) + $stat_w + $rem_w, 4, '', 1, 1);
    
    $pdf->SetFont('Arial', '', 6);
    $count = 1;
    foreach ($list as $s) {
        $pdf->Cell($name_w, 3.5, $count++ . '. ' . substr($s['name'], 0, 35), 1, 0);
        $abs = 0; $d_idx = 0;
        for ($i=1; $i<=25; $i++) {
            $char = '';
            if (isset($school_days[$d_idx])) {
                $actual_d = $school_days[$d_idx];
                if (!isset($presence[$s['lrn']][$actual_d])) { $char = 'x'; $abs++; }
                else { $tally[$i]++; }
                $d_idx++;
            }
            $pdf->Cell($day_w, 3.5, $char, 1, 0, 'C');
        }
        $pdf->Cell($stat_w/2, 3.5, $abs ?: '', 1, 0, 'C');
        $pdf->Cell($stat_w/2, 3.5, '', 1, 0, 'C');
        $pdf->Cell($rem_w, 3.5, '', 1, 1);
    }
}

$m_tally = array_fill(1, 26, 0);
$f_tally = array_fill(1, 26, 0);

drawStudentList($pdf, 'MALE', $male, $presence, $school_days, $name_w, $day_w, $stat_w, $rem_w, $m_tally);

// Total Male Row (Centered and Bold)
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell($name_w, 4, 'MALE TOTAL PER DAY', 1, 0, 'C');
for($i=1; $i<=25; $i++) $pdf->Cell($day_w, 4, $m_tally[$i] ?: '', 1, 0, 'C');
$pdf->Cell($stat_w + $rem_w, 4, '', 1, 1);

drawStudentList($pdf, 'FEMALE', $fem, $presence, $school_days, $name_w, $day_w, $stat_w, $rem_w, $f_tally);

// Total Female Row (Centered and Bold)
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell($name_w, 4, 'FEMALE TOTAL PER DAY', 1, 0, 'C');
for($i=1; $i<=25; $i++) $pdf->Cell($day_w, 4, $f_tally[$i] ?: '', 1, 0, 'C');
$pdf->Cell($stat_w + $rem_w, 4, '', 1, 1);

// Combined Total Row (Centered and Bold)
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell($name_w, 4, 'Combined TOTAL PER DAY', 1, 0, 'C');
$total_attendance_sum = 0;
for ($i=1; $i<=25; $i++) {
    $daily_total = $m_tally[$i] + $f_tally[$i];
    $total_attendance_sum += $daily_total;
    $pdf->Cell($day_w, 4, $daily_total ?: '', 1, 0, 'C');
}
$pdf->Cell($stat_w + $rem_w, 4, '', 1, 1);

// --- FOOTER SECTION (GUIDELINES & SUMMARY) ---
$pdf->Ln(2);
$currX = $pdf->GetX();
$currY = $pdf->GetY();

// Column 1: Guidelines
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell(80, 4, 'GUIDELINES:', 0, 1);
$pdf->SetFont('Arial', '', 5);
$guidelines = "1. The attendance shall be accomplished daily. Refer to the codes for checking learners' attendance.\n2. Dates shall be written in the columns after Learner's Name.\n3. To compute the following:\n    a. Percentage of Enrolment = (Registered Learners / Enrolment 1st Friday) x 100\n    b. Average Daily Attendance = Total Daily Attendance / Number of School Days\n    c. Percentage of Attendance = (Average Daily Attendance / Registered Learners) x 100\n4. Every end of the month, the class adviser will submit this form to the office of the principal.\n5. The adviser will provide necessary interventions for learners at risk of dropping out.\n6. Attendance performance will be reflected in Form 137 and Form 138.";
$pdf->MultiCell(85, 2.5, $guidelines, 0, 'L');

// Column 2: Codes and Reasons
$pdf->SetXY($currX + 90, $currY);
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell(70, 4, '1. CODES FOR CHECKING ATTENDANCE', 0, 1);
$pdf->SetFont('Arial', '', 5);
$pdf->Cell(70, 3, '(blank) - Present; (x) - Absent; Tardy (half shaded) Upper for Late, Lower for Cutting Classes', 0, 1);
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell(70, 4, '2. REASONS/CAUSES FOR DROPPING OUT', 0, 1);
$pdf->SetFont('Arial', '', 5);
$reasons = "a. Domestic-Related Factors (Siblings, Marriage, Parents' attitude, Family problems)\nb. Individual-Related Factors (Illness, Overage, Death, Drug Abuse, Poor performance, etc.)\nc. School-Related Factors (Teacher, Classroom condition, Peer influence)\nd. Geographic/Environmental (Distance, Armed conflict, Calamities)\ne. Financial-Related (Child labor, work)\nf. Others (Specify)";
$pdf->MultiCell(75, 2.5, $reasons, 0, 'L');

// Column 3: Summary Table (Functional)
$pdf->SetXY($currX + 175, $currY);
$pdf->SetFont('Arial', 'B', 6);
$pdf->Cell(45, 4, 'Month: ' . $month_name, 1, 0, 'L');
$pdf->Cell(30, 4, 'No. of Days of Classes: ' . $num_school_days, 1, 1, 'L');

$pdf->SetX($currX + 175);
$pdf->Cell(45, 4, 'Summary', 1, 0, 'C');
$pdf->Cell(10, 4, 'M', 1, 0, 'C');
$pdf->Cell(10, 4, 'F', 1, 0, 'C');
$pdf->Cell(10, 4, 'TOTAL', 1, 1, 'C');

// Functional Summary Calculations
$reg_m = count($male);
$reg_f = count($fem);
$reg_total = $reg_m + $reg_f;
$avg_attendance = ($num_school_days > 0) ? round($total_attendance_sum / $num_school_days, 2) : 0;
$perc_attendance = ($reg_total > 0) ? round(($avg_attendance / $reg_total) * 100, 2) : 0;

$summary_rows = [
    ['* Enrolment as of (1st Friday of June)', $reg_m, $reg_f, $reg_total],
    ['Late Enrolment (during the month)', 0, 0, 0],
    ['Registered Learners as of end of month', $reg_m, $reg_f, $reg_total],
    ['Percentage of Enrolment as of end of month', '', '', '100%'],
    ['Average Daily Attendance', '', '', $avg_attendance],
    ['Percentage of Attendance for the month', '', '', $perc_attendance . '%'],
    ['Number of students absent for 5 consecutive days', 0, 0, 0],
    ['Drop out', 0, 0, 0],
    ['Transferred out', 0, 0, 0],
    ['Transferred in', 0, 0, 0]
];

foreach ($summary_rows as $row) {
    $pdf->SetX($currX + 175);
    $pdf->SetFont('Arial', '', 5);
    $pdf->Cell(45, 3.5, $row[0], 1, 0, 'L');
    $pdf->Cell(10, 3.5, $row[1], 1, 0, 'C');
    $pdf->Cell(10, 3.5, $row[2], 1, 0, 'C');
    $pdf->Cell(10, 3.5, $row[3], 1, 1, 'C');
}

// Signatures
$pdf->Ln(4);
$pdf->SetX($currX + 175);
$pdf->SetFont('Arial', '', 6);
$pdf->Cell(0, 4, "I certify that this is a true and correct report:", 0, 1);
$pdf->Ln(2);
$pdf->SetX($currX + 185);
$pdf->Cell(60, 4, $adviser_name, 'B', 1, 'C');
$pdf->SetX($currX + 185);
$pdf->Cell(60, 4, '(Signature of Teacher over Printed Name)', 0, 1, 'C');
$pdf->Ln(2);
$pdf->SetX($currX + 185);
$pdf->Cell(60, 4, $head_name, 'B', 1, 'C');
$pdf->SetX($currX + 185);
$pdf->Cell(60, 4, '(Signature of School Head over Printed Name)', 0, 1, 'C');

ob_end_clean();
$pdf->Output('I', "SF2_{$section_name}.pdf");