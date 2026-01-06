<?php
session_start();
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php';
if (!$conn) {
    die("Database connection failed.");
}

// Create logs folder if needed
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}
$section_error_log = 'logs/section_errors.txt';

// --- Database Connection Check ---
if ($conn->connect_error) {
    error_log(date('c') . " DB Conn Error: " . $conn->connect_error . "\n", 3, 'logs/error_log.txt');
    die("Database connection failed. Please check the logs.");
}
$conn->set_charset("utf8");

// --- CSRF Token Generation ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validate_csrf_token() {
    global $section_error_log;
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, $section_error_log);
        echo "<script>alert('Invalid security token. Please try again.');location='section_student.php'</script>";
        exit();
    }
}

function reset_sections_autoincrement($conn) {
    if ($conn->query("SELECT COUNT(*) FROM sections")->fetch_row()[0] == 0) {
        $conn->query("ALTER TABLE sections AUTO_INCREMENT = 1");
    }
}

$available_grade_levels = [
    'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10', 'Grade 11', '11-GAS', 'Grade 12', '12-GAS'
];

// --- Handle Add Section ---
if (isset($_POST['add_section'])) {
    validate_csrf_token();
    $section_name = htmlspecialchars(trim($_POST['section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level  = htmlspecialchars(trim($_POST['grade_level_input']), ENT_QUOTES, 'UTF-8');
    $employee_id   = filter_var($_POST['employee_id'], FILTER_VALIDATE_INT);

    if (empty($section_name) || empty($grade_level) || $employee_id === false) {
        echo "<script>alert('All fields are required.'); location='section_student.php'</script>";
        exit();
    }
    
    // Check for duplicate section name + grade level
    $dup_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ?");
    $dup_stmt->bind_param("ss", $section_name, $grade_level);
    $dup_stmt->execute();
    if ($dup_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: A section with this name already exists for this grade level.'); location='section_student.php'</script>";
        exit();
    }
    $dup_stmt->close();

    // Check if adviser is already assigned
    $adviser_check = $conn->prepare("SELECT section_id FROM sections WHERE employee_id = ?");
    $adviser_check->bind_param("i", $employee_id);
    $adviser_check->execute();
    if ($adviser_check->get_result()->num_rows > 0) {
        echo "<script>alert('Error: This adviser is already assigned. One adviser can only manage one section.'); location='section_student.php'</script>";
        exit();
    }
    $adviser_check->close();

    $stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, employee_id) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $section_name, $grade_level, $employee_id);
    if ($stmt->execute()) {
        header("Location: section_student.php?status=added");
    } else {
        error_log(date('c') . " EXECUTE ERR (Add Section): " . $stmt->error . "\n", 3, $section_error_log);
        echo "<script>alert('Error adding section.'); location='section_student.php'</script>";
    }
    exit();
}

// --- Handle Edit Section ---
if (isset($_POST['edit_section'])) {
    validate_csrf_token();
    $section_id   = filter_var($_POST['edit_section_id'], FILTER_VALIDATE_INT);
    $section_name = htmlspecialchars(trim($_POST['edit_section_name']), ENT_QUOTES, 'UTF-8');
    $grade_level  = htmlspecialchars(trim($_POST['edit_grade_level_input']), ENT_QUOTES, 'UTF-8');
    $employee_id   = filter_var($_POST['edit_employee_id'], FILTER_VALIDATE_INT);

    if (!$section_id || empty($section_name) || empty($grade_level) || $employee_id === false) {
        echo "<script>alert('All fields are required.'); location='section_student.php'</script>";
        exit();
    }

    $dup_stmt = $conn->prepare("SELECT section_id FROM sections WHERE section_name = ? AND grade_level = ? AND section_id != ?");
    $dup_stmt->bind_param("ssi", $section_name, $grade_level, $section_id);
    $dup_stmt->execute();
    if ($dup_stmt->get_result()->num_rows > 0) {
        echo "<script>alert('Error: Another section with this name already exists for this grade level.'); location='section_student.php'</script>";
        exit();
    }
    $dup_stmt->close();
    
    $adviser_check = $conn->prepare("SELECT section_id FROM sections WHERE employee_id = ? AND section_id != ?");
    $adviser_check->bind_param("ii", $employee_id, $section_id);
    $adviser_check->execute();
    if ($adviser_check->get_result()->num_rows > 0) {
        echo "<script>alert('Error: This adviser is already assigned to another section.'); location='section_student.php'</script>";
        exit();
    }
    $adviser_check->close();

    $stmt = $conn->prepare("UPDATE sections SET section_name=?, grade_level=?, employee_id=? WHERE section_id=?");
    $stmt->bind_param("ssii", $section_name, $grade_level, $employee_id, $section_id);
    if ($stmt->execute()) {
        header("Location: section_student.php?status=updated");
    } else {
        error_log(date('c') . " EXECUTE ERR (Edit Section): " . $stmt->error . "\n", 3, $section_error_log);
        echo "<script>alert('Error updating section.'); location='section_student.php'</script>";
    }
    exit();
}

// --- Handle Delete Section ---
if (isset($_POST['delete_section'])) {
    validate_csrf_token();
    $section_id = filter_var($_POST['section_id'], FILTER_VALIDATE_INT);
    if ($section_id) {
        $stmt = $conn->prepare("DELETE FROM sections WHERE section_id = ?");
        $stmt->bind_param("i", $section_id);
        if ($stmt->execute()) {
            reset_sections_autoincrement($conn);
            header("Location: section_student.php?status=deleted");
        } else {
            error_log(date('c') . " EXECUTE ERR (Delete Section): " . $stmt->error . "\n", 3, $section_error_log);
            echo "<script>alert('Could not delete section. It might be in use by enrollments.'); location='section_student.php'</script>";
        }
        exit();
    }
}

function safeTrimCSV($value) {
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)($value ?? '')));
}

// --- CSV Import ---
if (isset($_POST['import_sections_csv']) && isset($_FILES['sections_csvfile']) && $_FILES['sections_csvfile']['error'] === UPLOAD_ERR_OK) {
    validate_csrf_token();
    $tmpPath  = $_FILES['sections_csvfile']['tmp_name'];
    $fileExt  = strtolower(pathinfo($_FILES['sections_csvfile']['name'], PATHINFO_EXTENSION));

    if ($fileExt !== 'csv') {
        echo "<script>alert('Please upload a CSV file.'); location='section_student.php'</script>";
        exit();
    }
    
    if (($handle = fopen($tmpPath, 'r')) !== false) {
        $conn->begin_transaction();
        try {
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("TRUNCATE TABLE sections");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");
            reset_sections_autoincrement($conn);

            ini_set('auto_detect_line_endings', 1);
            $header = array_map(fn($h) => strtolower(safeTrimCSV($h)), fgetcsv($handle, 2000, ","));

            $aliases = [
                'section_name' => ['section_name', 'section'],
                'grade_level'  => ['grade_level', 'grade'],
                'adviser_name' => ['adviser_name', 'adviser', 'advisor_name', 'advisor', 'adviser name', 'advisor name']
            ];
            
            $col_map = [];
            foreach ($aliases as $field => $possible) {
                $found = array_intersect($possible, $header);
                if (empty($found)) throw new Exception("CSV header missing column for: '{$field}'");
                $col_map[$field] = array_search(current($found), $header);
            }
            
            $lookup_stmt = $conn->prepare("
                SELECT employee_id FROM advisers WHERE 
                LOWER(CONCAT(firstname, ' ', lastname)) = ? OR
                LOWER(CONCAT(lastname, ' ', firstname)) = ? OR
                (LOWER(?) LIKE CONCAT(LOWER(firstname), '%') AND LOWER(?) LIKE CONCAT('%', LOWER(lastname)))
                LIMIT 1
            ");
            $insert_stmt = $conn->prepare("INSERT INTO sections (section_name, grade_level, employee_id) VALUES (?, ?, ?)");

            $rowCount = 0; $errors = []; $row_num = 1;
            while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                $row_num++;
                if (empty(array_filter($data, fn($d) => $d !== ''))) continue;

                $csv_section = htmlspecialchars(safeTrimCSV($data[$col_map['section_name']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_grade   = htmlspecialchars(safeTrimCSV($data[$col_map['grade_level']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_adv_name = strtolower(preg_replace('/\s+/', ' ', safeTrimCSV($data[$col_map['adviser_name']] ?? '')));

                if (empty($csv_section) || empty($csv_grade) || empty($csv_adv_name)) {
                    $errors[] = "Row {$row_num}: Missing required fields."; continue;
                }
                
                $lookup_stmt->bind_param("ssss", $csv_adv_name, $csv_adv_name, $csv_adv_name, $csv_adv_name);
                $lookup_stmt->execute();
                $lookup_res = $lookup_stmt->get_result();

                if ($lookup_res->num_rows === 0) {
                    $errors[] = "Row {$row_num}: Adviser '".ucwords($csv_adv_name)."' not found."; continue;
                }
                $employee_id = (int)$lookup_res->fetch_row()[0];

                $insert_stmt->bind_param("ssi", $csv_section, $csv_grade, $employee_id);
                if (!$insert_stmt->execute()) {
                    $errors[] = "Row {$row_num}: DB Error - " . $insert_stmt->error;
                } else {
                    $rowCount++;
                }
            }
            fclose($handle);
            $lookup_stmt->close();
            $insert_stmt->close();

            if (!empty($errors)) {
                $conn->rollback();
                $error_summary = implode("\\n", array_slice($errors, 0, 5));
                if (count($errors) > 5) $error_summary .= "\\n...and " . (count($errors) - 5) . " more.";
                $message = "CSV Import Failed:\\n" . $error_summary;
            } else {
                $conn->commit();
                $message = "CSV Import Complete. Imported {$rowCount} sections.";
            }
            echo "<script>alert(" . json_encode($message) . "); location='section_student.php'</script>";
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            error_log(date('c') . " CSV IMPORT EXCEPTION: " . $e->getMessage() . "\n", 3, $section_error_log);
            echo "<script>alert(" . json_encode($e->getMessage()) . "); location='section_student.php'</script>";
            exit();
        }
    }
}

// --- Fetch Data for Display ---
$advisers_result = $conn->query("SELECT employee_id, CONCAT(firstname, ' ', lastname) AS adviser_fullname FROM advisers ORDER BY lastname, firstname");
$all_advisers_php = $advisers_result ? $advisers_result->fetch_all(MYSQLI_ASSOC) : [];
$all_advisers_json = json_encode($all_advisers_php);

$available_grade_levels_json = json_encode($available_grade_levels);

$sections_query = "
    SELECT s.section_id, s.section_name, s.grade_level, s.employee_id,
           COALESCE(CONCAT(adv.firstname, ' ', adv.lastname), 'N/A') AS adviser_fullname
    FROM sections s
    LEFT JOIN advisers adv ON s.employee_id = adv.employee_id
    ORDER BY s.grade_level, s.section_name
";
$sections_result = $conn->query($sections_query);
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sections Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MODIFICATION: Centralized and updated styles for table and layout -->
    <style>
      .table-card {
          background: #fff;
          border-radius: 16px;
          padding: 1.5rem;
          width: 100%;
          box-shadow: 0 12px 30px rgba(15,23,42,0.08);
      }
      /* MODIFICATION: Added max-height and overflow: auto to create a scrollable container */
      .table-responsive-custom {
          max-height: 65vh;
          overflow: auto;
      }
      .custom-table {
          width: 100%;
          border-collapse: collapse;
          font-size: 0.95rem;
      }
      /* MODIFICATION: Added position: sticky to keep the header fixed during scroll */
      .custom-table thead th {
          position: sticky;
          top: 0;
          z-index: 1;
          text-align: left;
          font-weight: 600;
          text-transform: uppercase;
          font-size: 0.75rem;
          letter-spacing: 0.08em;
          color: #4b5563;
          padding: 0.75rem 1rem;
          border: 1px solid #e5e7eb;
          background-color: #f9fafb;
      }
      .custom-table tbody td {
          padding: 0.85rem 1rem;
          vertical-align: middle;
          border: 1px solid #f1f5f9;
      }
      .custom-table tbody tr:hover { background: rgba(59,130,246,0.06); }
      .actions-cell {
          display: flex;
          gap: 0.5rem;
          justify-content: center;
          min-width: 80px;
      }
      .action-icon-btn {
          border: none; background: none; padding: 0; margin: 0 2px;
          cursor: pointer; transition: all 0.2s ease;
      }
      .action-icon-btn .material-symbols-outlined {
          font-size: 1.1em;
          font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
      }
      .action-icon-btn.edit-icon .material-symbols-outlined { color: #1c74e4; }
      .action-icon-btn.delete-icon .material-symbols-outlined { color: #dc3545; }
      .action-icon-btn:hover { transform: translateY(-1px); opacity: 0.8; }
      .search-box {
          position: relative;
          display: inline-block;
          margin-right: 10px;
      }
      .search-box input {
          padding: 0.5rem 2.5rem 0.5rem 1rem;
          border: 1px solid #d1d5db;
          border-radius: 8px;
          width: 300px;
          font-size: 0.95rem;
          transition: all 0.3s ease;
      }
      .search-box input:focus {
          outline: none;
          border-color: #3b82f6;
          box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
      }
      .search-box .search-icon, .clear-search {
          position: absolute; top: 50%;
          transform: translateY(-50%); color: #6b7280;
      }
      .search-box .search-icon { right: 0.75rem; pointer-events: none; }
      .clear-search {
          right: 2.5rem; background: none; border: none;
          cursor: pointer; padding: 0; display: none;
      }
      .clear-search.show { display: block; }
      .page-title-with-logo { display: flex; align-items: center; gap: 12px; }
      .page-logo {
          width: 45px; height: 45px; object-fit: contain;
          border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
    </style>
  </head>
  <body id="page-top">
    <?php include 'nav.php'; ?>
    <div id="wrapper">
      <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
          <div class="container-fluid mt-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="page-title-with-logo">
                <img src="img/depedlogo.jpg" alt="Section Logo" class="page-logo">
                <h2 class="h3 mb-0 text-gray-800">Sections Dashboard</h2>
              </div>
              <div class="d-flex align-items-center">
                <div class="search-box">
                  <input type="text" id="searchSection" placeholder="Search sections..." autocomplete="off">
                  <button type="button" class="clear-search" id="clearSearch" title="Clear search">
                      <span class="material-symbols-outlined">close</span>
                  </button>
                  <span class="search-icon">
                      <span class="material-symbols-outlined">search</span>
                  </span>
                </div>
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal">
                    <span class="material-symbols-outlined" style="vertical-align: middle;">upload_file</span> Import CSV
                </button>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
                    <span class="material-symbols-outlined" style="vertical-align: middle;">note_add</span> Add Section
                </button>
              </div>
            </div>

            <div class="table-card">
              <div class="table-responsive-custom">
                <!-- MODIFICATION: Removed inline Tailwind classes from table elements -->
                <table class="custom-table">
                  <thead>
                    <tr>
                      <th>#</th>
                      <th>Section Name</th>
                      <th>Grade Level</th>
                      <th>Adviser</th>
                      <th style="text-align: center;">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                      $counter = 1;
                      if ($sections_result && $sections_result->num_rows > 0):
                        while ($row = $sections_result->fetch_assoc()):
                    ?>
                      <tr>
                        <td><?= $counter++ ?></td>
                        <td><?= htmlspecialchars($row['section_name']) ?></td>
                        <td><?= htmlspecialchars($row['grade_level']) ?></td>
                        <td><?= htmlspecialchars($row['adviser_fullname']) ?></td>
                        <td class="actions-cell">
                          <button onclick='openEditSectionModal(<?= json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)' class="action-icon-btn edit-icon" title="Edit Section">
                            <span class="material-symbols-outlined">edit</span>
                          </button>
                          <form method="POST" class="d-inline" onsubmit="return confirm('Delete section <?= htmlspecialchars($row['section_name']) ?>? This cannot be undone.');">
                              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                              <input type="hidden" name="section_id" value="<?= $row['section_id'] ?>">
                              <button type="submit" name="delete_section" class="action-icon-btn delete-icon" title="Delete Section">
                                  <span class="material-symbols-outlined">delete</span>
                              </button>
                          </form>
                        </td>
                      </tr>
                    <?php endwhile; else: ?>
                      <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <span class="material-symbols-outlined" style="font-size:3rem; opacity:0.5;">class</span>
                            <div class="mt-2">No sections found.</div>
                        </td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Modals (Add, Edit, Import) and Toast -->
    <!-- The PHP and HTML for modals are unchanged as they are already well-implemented. -->
    
    <!-- Add Section Modal -->
    <div class="modal fade" id="addSectionModal" tabindex="-1" aria-labelledby="addSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post" id="addSectionForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-header">
              <h5 class="modal-title" id="addSectionModalLabel">Add New Section</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="add_section_name" class="form-label">Section Name</label>
                <input type="text" class="form-control" id="add_section_name" name="section_name" placeholder="e.g., Courage" required>
              </div>
              <div class="mb-3">
                <label for="add_grade_level_input" class="form-label">Grade Level</label>
                <input type="text" class="form-control" id="add_grade_level_input" name="grade_level_input" list="grade_levels_datalist_add" placeholder="Type or select a Grade Level" required>
                <datalist id="grade_levels_datalist_add"></datalist>
              </div>
              <div class="mb-3">
                <label for="add_adviser_name_input" class="form-label">Adviser</label>
                <input type="text" class="form-control" id="add_adviser_name_input" list="advisers_datalist_add" placeholder="Type or select an Adviser" required>
                <datalist id="advisers_datalist_add"></datalist>
                <input type="hidden" id="add_employee_id_hidden" name="employee_id">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="add_section" class="btn btn-primary">Add Section</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1" aria-labelledby="editSectionModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post" id="editSectionForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <input type="hidden" id="edit_section_id" name="edit_section_id">
            <div class="modal-header">
              <h5 class="modal-title" id="editSectionModalLabel">Edit Section</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="edit_section_name" class="form-label">Section Name</label>
                <input type="text" class="form-control" id="edit_section_name" name="edit_section_name" required>
              </div>
              <div class="mb-3">
                <label for="edit_grade_level_input" class="form-label">Grade Level</label>
                <input type="text" class="form-control" id="edit_grade_level_input" name="edit_grade_level_input" list="grade_levels_datalist_edit" placeholder="Type or select a Grade Level" required>
                <datalist id="grade_levels_datalist_edit"></datalist>
              </div>
              <div class="mb-3">
                <label for="edit_adviser_name_input" class="form-label">Adviser</label>
                <input type="text" class="form-control" id="edit_adviser_name_input" list="advisers_datalist_edit" placeholder="Type or select an Adviser" required>
                <datalist id="advisers_datalist_edit"></datalist>
                <input type="hidden" id="edit_employee_id_hidden" name="edit_employee_id">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="edit_section" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Import Sections CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <div class="modal-header">
              <h5 class="modal-title" id="importModalLabel">Import Sections from CSV</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="alert alert-warning">
                <strong>Warning:</strong> Importing will first delete all existing sections.
              </div>
              <div class="mb-3">
                <label for="sections_csvfile" class="form-label">Select CSV File *</label>
                <input type="file" name="sections_csvfile" id="sections_csvfile" class="form-control" accept=".csv" required>
                <div class="form-text mt-2">Required columns: <code>section_name</code>, <code>grade_level</code>, <code>adviser_name</code></div>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" name="import_sections_csv" class="btn btn-primary">Upload & Import</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Toast container for notifications -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
        <div id="liveToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    
    <!-- JavaScript logic remains the same -->
    <script>
      const allAdvisersData = <?= $all_advisers_json ?>;
      const availableGradeLevels = <?= $available_grade_levels_json ?>;

      function populateDatalist(datalistId, items) {
          const datalist = document.getElementById(datalistId);
          datalist.innerHTML = '';
          items.forEach(item => {
              const option = document.createElement('option');
              option.value = item;
              datalist.appendChild(option);
          });
      }

      function setupAdviserInput(inputId, datalistId, hiddenId) {
          const nameInput = document.getElementById(inputId);
          const hiddenInput = document.getElementById(hiddenId);
          const datalist = document.getElementById(datalistId);
          datalist.innerHTML = '';

          allAdvisersData.forEach(adviser => {
              const option = document.createElement('option');
              option.value = adviser.adviser_fullname;
              option.dataset.employeeId = adviser.employee_id;
              datalist.appendChild(option);
          });
          
          const updateHiddenId = () => {
              const selectedOption = Array.from(datalist.options).find(opt => opt.value === nameInput.value);
              hiddenInput.value = selectedOption ? selectedOption.dataset.employeeId : '';
              nameInput.setCustomValidity(selectedOption ? '' : 'Please select a valid adviser.');
          };
          nameInput.addEventListener('input', updateHiddenId);
          nameInput.addEventListener('change', updateHiddenId);
      }

      $(document).ready(function(){
        const searchInput = $('#searchSection');
        const clearBtn = $('#clearSearch');
        const tableRows = $('.custom-table tbody tr');

        searchInput.on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            $(this).val().length > 0 ? clearBtn.addClass('show') : clearBtn.removeClass('show');
            let visibleCount = 0;
            
            tableRows.each(function(){
                const row = $(this);
                if (row.find('td[colspan]').length) return;
                
                if(row.text().toLowerCase().includes(searchTerm)){
                    row.show(); visibleCount++;
                } else {
                    row.hide();
                }
            });

            $('#noResults').remove();
            if(visibleCount === 0 && tableRows.length > 1 && searchTerm.length > 0) {
                $('.custom-table tbody').append('<tr id="noResults"><td colspan="5" class="text-center text-muted py-4"><span class="material-symbols-outlined">search_off</span> No sections found matching your search.</td></tr>');
            }
        });
        clearBtn.on('click', () => searchInput.val('').trigger('input').focus());
        searchInput.on('keydown', (e) => { if(e.key === 'Escape') clearBtn.click(); });
      
        // Handle Status Toast
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        if (status) {
            const toastEl = $('#liveToast');
            const toastBody = $('#toastMessage');
            const formattedStatus = status.charAt(0).toUpperCase() + status.slice(1);
            toastBody.text(`Section ${formattedStatus} successfully!`);
            toastEl.removeClass('bg-success bg-danger').addClass(status === 'deleted' ? 'bg-danger' : 'bg-success');
            new bootstrap.Toast(toastEl[0], { delay: 4000 }).show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        $('#addSectionModal').on('show.bs.modal', function() {
            $('#addSectionForm')[0].reset();
            populateDatalist('grade_levels_datalist_add', availableGradeLevels);
            setupAdviserInput('add_adviser_name_input', 'advisers_datalist_add', 'add_employee_id_hidden');
            $('#add_adviser_name_input').get(0).setCustomValidity('');
        });

        $('#addSectionForm, #editSectionForm').on('submit', function(event) {
            const adviserInput = $(this).find('input[list^="advisers_datalist"]');
            if (!adviserInput.get(0).checkValidity()) {
                event.preventDefault();
                adviserInput.get(0).reportValidity();
            }
        });
      });
      
      function openEditSectionModal(data) {
          $('#edit_section_id').val(data.section_id);
          $('#edit_section_name').val(data.section_name);
          $('#edit_grade_level_input').val(data.grade_level);
          
          populateDatalist('grade_levels_datalist_edit', availableGradeLevels);
          setupAdviserInput('edit_adviser_name_input', 'advisers_datalist_edit', 'edit_employee_id_hidden');
          
          $('#edit_adviser_name_input').val(data.adviser_fullname).trigger('change');
          $('#edit_adviser_name_input').get(0).setCustomValidity('');

          new bootstrap.Modal($('#editSectionModal')[0]).show();
      }
    </script>
  </body>
</html>
<?php include 'footer.php'; ?>