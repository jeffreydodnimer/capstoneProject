<?php
session_start();
// Ensure user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php'; // Database connection file

// Create logs directory if needed for RFID operations
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}
$rfid_error_log = 'logs/rfid_errors.txt';

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
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, 'logs/rfid_errors.txt');
        echo "<script>alert('Invalid security token. Please try again.'); location='link_rfid.php';</script>";
        exit();
    }
}

/*****************
RFID MANAGEMENT
******************/

// Handle Link RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_rfid'])) {
    validate_csrf_token();
    $rfid_number = htmlspecialchars(trim($_POST['rfid_number']), ENT_QUOTES, 'UTF-8');
    $lrn = htmlspecialchars(trim($_POST['lrn']), ENT_QUOTES, 'UTF-8');

    if (!$conn) {
        echo "<script>alert('Database connection failed for linking RFID.'); window.location.href='link_rfid.php';</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Ensure student exists
        $stmt = $conn->prepare("SELECT lrn FROM students WHERE lrn = ?");
        if ($stmt === false) {
            throw new Exception("Error preparing check statement: " . $conn->error);
        }
        $stmt->bind_param("s", $lrn);
        $stmt->execute();
        $check_result = $stmt->get_result();
        if ($check_result->num_rows === 0) {
            throw new Exception("Student with LRN '{$lrn}' does not exist.");
        }
        $stmt->close();

        // Check if the RFID number is already registered
        $check_rfid_stmt = $conn->prepare("SELECT rfid_number FROM rfid WHERE rfid_number = ?");
        if ($check_rfid_stmt === false) {
            throw new Exception("Error preparing RFID check statement: " . $conn->error);
        }
        $check_rfid_stmt->bind_param("s", $rfid_number);
        $check_rfid_stmt->execute();
        $check_rfid_result = $check_rfid_stmt->get_result();
        if ($check_rfid_result->num_rows > 0) {
            throw new Exception("RFID number already in use.");
        }
        $check_rfid_stmt->close();

        // Check if the provided LRN is already linked in the RFID table
        $check_lrn_stmt = $conn->prepare("SELECT lrn FROM rfid WHERE lrn = ?");
        if ($check_lrn_stmt === false) {
            throw new Exception("Error preparing LRN check statement: " . $conn->error);
        }
        $check_lrn_stmt->bind_param("s", $lrn);
        $check_lrn_stmt->execute();
        $check_lrn_result = $check_lrn_stmt->get_result();
        if ($check_lrn_result->num_rows > 0) {
            throw new Exception("This student already has an RFID linked.");
        }
        $check_lrn_stmt->close();

        // Insert the new RFID record
        $stmt = $conn->prepare("INSERT INTO rfid (rfid_number, lrn) VALUES (?, ?)");
        if ($stmt === false) {
            throw new Exception("Error preparing insert statement: " . $conn->error);
        }
        $stmt->bind_param("ss", $rfid_number, $lrn);
        if (!$stmt->execute()) {
            throw new Exception("Insert error: " . $stmt->error);
        }
        $stmt->close();
        $conn->commit();
        header("Location: link_rfid.php?status=linked");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error linking RFID: " . $e->getMessage() . "'); window.location.href='link_rfid.php';</script>";
        exit();
    }
}

// Handle Unlink/Delete RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unlink_rfid'])) {
    validate_csrf_token();
    $rfid_number = htmlspecialchars($_POST['rfid_number'], ENT_QUOTES, 'UTF-8');
    if (!$conn) {
        echo "<script>alert('Database connection failed for unlinking RFID.'); window.location.href='link_rfid.php';</script>";
        exit();
    }
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM rfid WHERE rfid_number = ?");
        if ($stmt === false) {
            throw new Exception("Error preparing delete statement: " . $conn->error);
        }
        $stmt->bind_param("s", $rfid_number);
        if (!$stmt->execute()) {
            throw new Exception("Delete error: " . $stmt->error);
        }
        $stmt->close();
        $conn->commit();
        header("Location: link_rfid.php?status=unlinked");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error unlinking RFID: " . $e->getMessage() . "'); window.location.href='link_rfid.php';</script>";
        exit();
    }
}

// Handle Update RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rfid'])) {
    validate_csrf_token();
    $original_rfid = htmlspecialchars(trim($_POST['original_rfid']), ENT_QUOTES, 'UTF-8');
    $rfid_number = htmlspecialchars(trim($_POST['edit_rfid_number']), ENT_QUOTES, 'UTF-8');
    $lrn = htmlspecialchars(trim($_POST['edit_lrn']), ENT_QUOTES, 'UTF-8');

    if (!$conn) {
        echo "<script>alert('Database connection failed for updating RFID.'); window.location.href='link_rfid.php';</script>";
        exit();
    }
    $conn->begin_transaction();
    try {
        // Check if student exists
        $check_stmt = $conn->prepare("SELECT lrn FROM students WHERE lrn = ?");
        if ($check_stmt === false) {
            throw new Exception("Error preparing check statement: " . $conn->error);
        }
        $check_stmt->bind_param("s", $lrn);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        if ($check_result->num_rows === 0) {
            throw new Exception("Student with LRN '{$lrn}' does not exist.");
        }
        $check_stmt->close();

        // If RFID number is changed, verify that the new RFID is not in use.
        if ($original_rfid !== $rfid_number) {
            $check_rfid_stmt = $conn->prepare("SELECT rfid_number FROM rfid WHERE rfid_number = ?");
            if ($check_rfid_stmt === false) {
                throw new Exception("Error preparing RFID check statement: " . $conn->error);
            }
            $check_rfid_stmt->bind_param("s", $rfid_number);
            $check_rfid_stmt->execute();
            if ($check_rfid_stmt->get_result()->num_rows > 0) {
                throw new Exception("New RFID number is already in use.");
            }
            $check_rfid_stmt->close();
        }

        // Check if the new LRN is already linked to another RFID (excluding the current one)
        $check_lrn_stmt = $conn->prepare("SELECT lrn FROM rfid WHERE lrn = ? AND rfid_number != ?");
        if ($check_lrn_stmt === false) {
            throw new Exception("Error preparing LRN check statement: " . $conn->error);
        }
        $check_lrn_stmt->bind_param("ss", $lrn, $original_rfid);
        $check_lrn_stmt->execute();
        if ($check_lrn_stmt->get_result()->num_rows > 0) {
            throw new Exception("This student (LRN) is already linked to another RFID card.");
        }
        $check_lrn_stmt->close();

        // Update the RFID record
        $stmt = $conn->prepare("UPDATE rfid SET rfid_number = ?, lrn = ? WHERE rfid_number = ?");
        if ($stmt === false) {
            throw new Exception("Error preparing update statement: " . $conn->error);
        }
        $stmt->bind_param("sss", $rfid_number, $lrn, $original_rfid);
        if (!$stmt->execute()) {
            throw new Exception("Update error: " . $stmt->error);
        }
        $stmt->close();
        $conn->commit();
        header("Location: link_rfid.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        echo "<script>alert('Error updating RFID: " . $e->getMessage() . "'); window.location.href='link_rfid.php';</script>";
        exit();
    }
}

/*************************
CSV IMPORT FOR RFID
**************************/
function safeTrimCSV($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

if (isset($_POST['import_rfid_csv']) && isset($_FILES['rfid_csvfile']) && $_FILES['rfid_csvfile']['error'] === UPLOAD_ERR_OK) {
    validate_csrf_token();
    $tmpPath  = $_FILES['rfid_csvfile']['tmp_name'];
    $fileName = $_FILES['rfid_csvfile']['name'];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if ($fileExt !== 'csv') {
        echo "<script>alert('Please upload a CSV file.'); window.location.href='link_rfid.php';</script>";
        exit;
    }
    if ($_FILES['rfid_csvfile']['size'] > 5 * 1024 * 1024) { // 5MB limit
        echo "<script>alert('File size exceeds 5MB limit.'); window.location.href='link_rfid.php';</script>";
        exit;
    }

    if ($conn->connect_error) {
        error_log(date('c') . " DB Conn Error during CSV import: " . $conn->connect_error . "\n", 3, $rfid_error_log);
        echo "<script>alert('Database connection lost. Cannot import CSV.'); window.location.href='link_rfid.php';</script>";
        exit;
    }

    if (($handle = fopen($tmpPath, 'r')) !== false) {
        ini_set('auto_detect_line_endings', 1);
        $header = fgetcsv($handle, 2000, ",");
        $expected_headers = ['rfid_number', 'lrn'];
        
        if (!is_array($header) || count($header) < count($expected_headers)) {
            fclose($handle);
            echo "<script>alert('CSV must have the columns: rfid_number, lrn'); window.location.href='link_rfid.php';</script>";
            exit;
        }

        $header_normalized = array_map(fn($col) => strtolower(trim($col)), $header);
        $col_map = [];
        foreach ($expected_headers as $col) {
            $index = array_search($col, $header_normalized, true);
            if ($index === false) {
                fclose($handle);
                echo "<script>alert('CSV header is missing required column: \"{$col}\"'); window.location.href='link_rfid.php';</script>";
                exit;
            }
            $col_map[$col] = $index;
        }

        $stmt = $conn->prepare("INSERT INTO rfid (rfid_number, lrn) VALUES (?, ?)");
        if (!$stmt) {
            fclose($handle);
            error_log(date('c') . " PREPARE ERR (CSV Import): " . $conn->error . "\n", 3, $rfid_error_log);
            echo "<script>alert('Database error preparing statement for CSV import.'); window.location.href='link_rfid.php';</script>";
            exit;
        }

        $rowCount = 0;
        $errors   = [];
        $row_num  = 1;

        while (($data = fgetcsv($handle, 2000, ",")) !== false) {
            $row_num++;
            if (!is_array($data) || count(array_filter($data)) === 0 || count($data) < count($expected_headers)) {
                continue;
            }

            $csv_rfid = safeTrimCSV($data[$col_map['rfid_number']] ?? '');
            $csv_lrn  = safeTrimCSV($data[$col_map['lrn']] ?? '');
            $valid_row = true;

            if (empty($csv_rfid) || empty($csv_lrn)) {
                $errors[] = "Row {$row_num}: RFID number and LRN are required.";
                $valid_row = false;
            }

            // Check student existence
            if ($valid_row) {
                $check_stmt = $conn->prepare("SELECT 1 FROM students WHERE lrn = ?");
                $check_stmt->bind_param("s", $csv_lrn);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows == 0) {
                    $errors[] = "Row {$row_num}: LRN '{$csv_lrn}' not found in student records.";
                    $valid_row = false;
                }
                $check_stmt->close();
            }

            // Check duplicate RFID record
            if ($valid_row) {
                $check_dup = $conn->prepare("SELECT 1 FROM rfid WHERE rfid_number = ? OR lrn = ?");
                $check_dup->bind_param("ss", $csv_rfid, $csv_lrn);
                $check_dup->execute();
                if ($check_dup->get_result()->num_rows > 0) {
                    $errors[] = "Row {$row_num}: Duplicate record (RFID '{$csv_rfid}' or LRN '{$csv_lrn}' already exists).";
                    $valid_row = false;
                }
                $check_dup->close();
            }

            if ($valid_row) {
                $stmt->bind_param("ss", $csv_rfid, $csv_lrn);
                if (!$stmt->execute()) {
                    error_log(date('c') . " EXECUTE ERR (CSV Import): " . $stmt->error . "\n", 3, $rfid_error_log);
                    $errors[] = "Row {$row_num}: DB error during insert.";
                } else {
                    $rowCount++;
                }
            }
        }
        fclose($handle);
        $stmt->close();

        $message = "CSV Import Complete\nSuccessfully imported: {$rowCount} RFID records.\n";
        if (!empty($errors)) {
            $message .= "\nErrors (" . count($errors) . " rows failed):\n" . implode("\n", array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= "\n... and " . (count($errors) - 5) . " more errors.";
            }
        }
        echo "<script>alert(" . json_encode($message) . "); window.location.href = 'link_rfid.php';</script>";
        exit();
    } else {
        echo "<script>alert('Failed to open CSV file. Please check file permissions or file integrity.'); window.location.href = 'link_rfid.php';</script>";
        exit();
    }
}

/***********************
Fetch Records for Display
***********************/
$rfid_result = $conn->query("
    SELECT r.rfid_number, r.lrn, s.lastname, s.firstname, s.middlename 
    FROM rfid r
    LEFT JOIN students s ON r.lrn = s.lrn
    ORDER BY s.lastname ASC, s.firstname ASC
");
if (!$rfid_result) {
    die("Error fetching RFID records: " . $conn->error);
}

// Fetch students for datalist suggestions
$students_result_for_dropdown = $conn->query("SELECT lrn, lastname, firstname, middlename FROM students ORDER BY lastname, firstname");
$all_students_for_dropdown = [];
if ($students_result_for_dropdown) {
    while ($row = $students_result_for_dropdown->fetch_assoc()) {
        $fullName = htmlspecialchars($row['lastname'] . ', ' . $row['firstname']);
        if (!empty($row['middlename'])) {
            $fullName .= ' ' . htmlspecialchars(substr($row['middlename'], 0, 1)) . '.';
        }
        $all_students_for_dropdown[] = ['lrn' => $row['lrn'], 'name' => $fullName];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>RFID Dashboard</title>
    <!-- Custom fonts and styles -->
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" />
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,300,400,600,700,800,900" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- MODIFICATION: Replaced old styles with new ones for scrollable table and sticky header -->
    <style>
      @keyframes hl {
        0% { background-color: #c8e6c9; }
        100% { background-color: transparent; }
      }
      .highlight { animation: hl 2s forwards; }
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
        border-bottom: 2px solid #e5e7eb;
        background-color: #f9fafb;
      }
      .custom-table tbody td {
        padding: 0.85rem 1rem;
        border: 1px solid #f1f5f9;
        vertical-align: middle;
      }
      .custom-table tbody tr:hover { background: rgba(59,130,246,0.06); }
      .actions-cell {
        display: flex;
        gap: 0.5rem;
        justify-content: flex-end;
        min-width: 80px;
      }
      .action-icon-btn {
        border: none;
        background: none;
        padding: 0;
        margin: 0 2px;
        cursor: pointer;
        transition: all 0.2s ease;
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
      .search-box .search-icon {
        position: absolute;
        right: 0.75rem;
        top: 50%;
        transform: translateY(-50%);
        color: #6b7280;
        pointer-events: none;
      }
      .clear-search {
        position: absolute;
        right: 2.5rem;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6b7280;
        cursor: pointer;
        padding: 0;
        display: none;
      }
      .clear-search.show { display: block; }
      .page-title-with-logo { display: flex; align-items: center; gap: 12px; }
      .page-logo {
        width: 45px;
        height: 45px;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      }
      .modal-header { background-color: #007bff; color: white; }
      .btn-close-white { filter: invert(1); }
      .modal-content input.form-control {
          border: 1px solid #ccc;
          box-shadow: 2px 4px 8px rgba(0,0,0,0.05);
          border-radius: 8px;
          padding: 0.5rem 0.75rem;
      }
    </style>
  </head>
  <body id="page-top">
    <?php include 'nav.php'; ?>
    <div id="wrapper">
      <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
          <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4 mt-4">
              <div class="page-title-with-logo">
                <img src="img/depedlogo.jpg" alt="School Logo" class="page-logo">
                <h2 class="h3 mb-0 text-gray-800">RFID Dashboard</h2>
              </div>
              <div class="d-flex align-items-center">
                <div class="search-box">
                  <input type="text" id="searchRFID" placeholder="Search RFID records..." autocomplete="off">
                  <button type="button" class="clear-search" id="clearSearch" title="Clear search">
                    <span class="material-symbols-outlined">close</span>
                  </button>
                  <span class="search-icon">
                    <span class="material-symbols-outlined">search</span>
                  </span>
                </div>
                <!-- Import CSV Button -->
                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#importModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                  <span class="material-symbols-outlined" style="vertical-align: middle;">upload_file</span> Import CSV
                </button>
                <!-- Link New RFID Button -->
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRfidModal" style="box-shadow: 0 4px 8px rgba(0,0,0,0.1);">
                  <span class="material-symbols-outlined" style="vertical-align: middle;">link</span> Link New RFID
                </button>
              </div>
            </div>
            
            <!-- MODIFICATION: New alert placeholder -->
            <div id="alert-placeholder"></div>
            
            <div class="table-card">
              <div class="table-responsive-custom">
                <!-- MODIFICATION: Removed inline Tailwind classes from table elements -->
                <table class="custom-table">
                  <thead>
                    <tr>
                      <th>No.</th>
                      <th>RFID Number</th>
                      <th>LRN</th>
                      <th>Student Name</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                      $counter = 1;
                      while ($row = $rfid_result->fetch_assoc()):
                        // Build full name robustly
                        $fullname_parts = [];
                        if (!empty($row['lastname'])) $fullname_parts[] = $row['lastname'];
                        if (!empty($row['firstname'])) $fullname_parts[] = $row['firstname'];
                        if (!empty($row['middlename'])) $fullname_parts[] = substr($row['middlename'], 0, 1) . '.';
                        $fullname = htmlspecialchars(implode(', ', $fullname_parts));
                        if (empty($fullname)) $fullname = 'N/A';
                    ?>
                    <tr>
                      <td><?= $counter++ ?></td>
                      <td><?= htmlspecialchars($row['rfid_number'] ?? 'N/A') ?></td>
                      <td><?= htmlspecialchars($row['lrn'] ?? 'N/A') ?></td>
                      <td><?= $fullname ?></td>
                      <td class="actions-cell">
                        <button onclick='openEditModal(<?= json_encode(["rfid_number" => $row["rfid_number"], "lrn" => $row["lrn"]]) ?>)' class="action-icon-btn edit-icon" title="Edit RFID">
                          <span class="material-symbols-outlined">edit</span>
                        </button>
                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to unlink this RFID from <?= $fullname ?>?');">
                          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                          <input type="hidden" name="rfid_number" value="<?= htmlspecialchars($row['rfid_number'] ?? '') ?>">
                          <button type="submit" name="unlink_rfid" class="action-icon-btn delete-icon" title="Unlink RFID">
                            <span class="material-symbols-outlined">unlink</span>
                          </button>
                        </form>
                      </td>
                    </tr>
                    <?php endwhile; 
                          if ($rfid_result->num_rows === 0): ?>
                      <tr>
                        <td colspan="5" class="text-center text-muted py-4">No RFID records found.</td>
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

<!-- Add RFID Modal -->
<div class="modal fade" id="addRfidModal" tabindex="-1" aria-labelledby="addRfidModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addRfidModalLabel">Link RFID to Student</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <div class="mb-3">
            <label for="rfid_number" class="form-label">RFID Number *</label>
            <input type="text" class="form-control" id="rfid_number" name="rfid_number" placeholder="Scan or Enter RFID Number" required>
          </div>
          <div class="mb-3">
            <label for="lrn_input" class="form-label">Student LRN *</label>
            <input type="text" list="studentLrnList" class="form-control" id="lrn_input" name="lrn" placeholder="Enter LRN or select student" required>
            <datalist id="studentLrnList">
                <?php foreach ($all_students_for_dropdown as $student): ?>
                    <option value="<?= htmlspecialchars($student['lrn']) ?>" label="<?= $student['name'] ?> (LRN: <?= $student['lrn'] ?>)"></option>
                <?php endforeach; ?>
            </datalist>
          </div>
          <div class="text-center">
            <button type="submit" name="link_rfid" class="btn btn-primary px-4">Link RFID</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Edit RFID Modal -->
<div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="editModalLabel">Edit RFID Information</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
          <input type="hidden" id="original_rfid" name="original_rfid">
          <div class="mb-3">
            <label for="edit_rfid_number" class="form-label">RFID Number *</label>
            <input type="text" class="form-control" id="edit_rfid_number" name="edit_rfid_number" placeholder="Enter RFID Number" required>
          </div>
          <div class="mb-3">
            <label for="edit_lrn" class="form-label">Student LRN *</label>
            <input type="text" list="studentLrnList" class="form-control" id="edit_lrn" name="edit_lrn" placeholder="Enter LRN or select student" required>
            <!-- Datalist is reused from the add modal -->
          </div>
          <div class="text-center">
            <button type="submit" name="update_rfid" class="btn btn-primary px-4">Update RFID</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Import RFID CSV Modal -->
<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="importModalLabel">Import RFID Records from CSV</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label for="rfid_csvfile" class="form-label">Select CSV File *</label>
            <input type="file" name="rfid_csvfile" id="rfid_csvfile" class="form-control" accept=".csv" required>
            <small class="text-muted">Max 5MB | Required Columns: rfid_number, lrn</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" name="import_rfid_csv" class="btn btn-primary">Upload & Import</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  $(document).ready(function() {
    // Function to open the Edit RFID Modal
    window.openEditModal = function(rfid) {
      $('#editModal').modal('show');
      $('#original_rfid').val(rfid.rfid_number);
      $('#edit_rfid_number').val(rfid.rfid_number);
      $('#edit_lrn').val(rfid.lrn);
    };

    // MODIFICATION: Replaced old alert system with Bootstrap alerts
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    if (status) {
        let message = "";
        let alertClass = "alert-success";
        switch(status) {
            case "linked":   message = "RFID linked successfully!"; break;
            case "unlinked": message = "RFID unlinked successfully!"; break;
            case "updated":  message = "RFID updated successfully!"; break;
        }
        
        if (message) {
            const alertHtml = `<div class="alert ${alertClass} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>`;
            $('#alert-placeholder').html(alertHtml);
            // Automatically close the alert after 5 seconds
            setTimeout(() => {
                $(".alert").alert('close');
            }, 5000);
        }
        // Clean the URL
        window.history.replaceState({}, document.title, "link_rfid.php");
    }

    // Real-time search functionality
    const searchInput = $('#searchRFID');
    const clearBtn = $('#clearSearch');
    const tableRows = $('.custom-table tbody tr');
    
    searchInput.on('input', function() {
      const searchTerm = $(this).val().toLowerCase().trim();
      $(this).val().length > 0 ? clearBtn.addClass('show') : clearBtn.removeClass('show');

      let visibleCount = 0;
      tableRows.each(function() {
        const row = $(this);
        // Exclude the 'no records' row from search logic
        if (row.find('td[colspan]').length) return;
        
        const rowText = row.text().toLowerCase();
        if (rowText.includes(searchTerm)) {
          row.show();
          visibleCount++;
        } else {
          row.hide();
        }
      });
      
      $('#noResults').remove(); // Remove previous 'no results' message
      if (visibleCount === 0 && tableRows.length > 1) { // Check if there are actual data rows
         $('.custom-table tbody').append('<tr id="noResults"><td colspan="5" class="text-center text-muted py-4"><span class="material-symbols-outlined" style="vertical-align: middle;">search_off</span> No records found matching your search.</td></tr>');
      }
    });

    clearBtn.on('click', function() {
      searchInput.val('').trigger('input').focus();
    });

    searchInput.on('keydown', function(e) { if(e.key === 'Escape') clearBtn.click(); });
  });
</script>

<?php include 'footer.php'; ?>
</body>
</html>