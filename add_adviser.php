<?php
session_start();
// Ensure the user is logged in
if (!isset($_SESSION['email'])) {
    header('Location: admin_login.php');
    exit();
}

include 'conn.php'; // Database connection

// Create logs directory if needed
if (!is_dir('logs')) {
    mkdir('logs', 0755, true);
}
$adviser_error_log = 'logs/adviser_errors.txt';
$general_error_log = 'logs/error_log.txt';

// Create uploads directory if needed
$upload_dir = 'uploads/advisers/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// --- Database Connection Check ---
if ($conn->connect_error) {
    error_log(date('c') . " DB Conn Error: " . $conn->connect_error . "\n", 3, $general_error_log);
    die("Database connection failed. Please check the logs.");
}
$conn->set_charset("utf8");

// --- CSRF Token Generation ---
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function validate_csrf_token($log_file_path) {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        error_log(date('c') . " CSRF Attack Detected: " . $_SERVER['REMOTE_ADDR'] . "\n", 3, $log_file_path);
        echo "<script>alert('Invalid security token. Please try again.');location='add_adviser.php'</script>";
        exit();
    }
}

function upload_adviser_photo($file_input_name, $current_photo = null) {
    global $upload_dir; // Use the global upload directory
    if (!isset($_FILES[$file_input_name]) || $_FILES[$file_input_name]['error'] !== UPLOAD_ERR_OK) {
        return $current_photo; // Return existing photo if no new file uploaded or error
    }

    $file_tmp = $_FILES[$file_input_name]['tmp_name'];
    $file_name = $_FILES[$file_input_name]['name'];
    $file_size = $_FILES[$file_input_name]['size'];
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Validate Extension
    $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($file_ext, $allowed_exts)) {
        throw new Exception("Invalid photo format. Only JPG, PNG, GIF allowed.");
    }

    // Validate Size (Max 2MB)
    if ($file_size > 2 * 1024 * 1024) {
        throw new Exception("Photo size too large. Max 2MB.");
    }

    // Generate unique name
    $new_name = 'adv_' . uniqid() . '.' . $file_ext;
    $destination = $upload_dir . $new_name;

    if (!move_uploaded_file($file_tmp, $destination)) {
        throw new Exception("Failed to upload photo.");
    }

    // Delete old photo if it exists and is not the default/empty
    if ($current_photo && file_exists($upload_dir . $current_photo)) {
        unlink($upload_dir . $current_photo);
    }

    return $new_name;
}

/*****************
ADVISER MANAGEMENT
*******************/

// --- Handle Add Adviser ---
if (isset($_POST['add_adviser'])) {
    validate_csrf_token($adviser_error_log);
    
    $employee_id = htmlspecialchars(trim($_POST['employee_id']), ENT_QUOTES, 'UTF-8');
    $lastname    = htmlspecialchars(trim($_POST['lastname']), ENT_QUOTES, 'UTF-8');
    $firstname   = htmlspecialchars(trim($_POST['firstname']), ENT_QUOTES, 'UTF-8');
    $middlename  = htmlspecialchars(trim($_POST['middlename']), ENT_QUOTES, 'UTF-8');
    $suffix      = htmlspecialchars(trim($_POST['suffix']), ENT_QUOTES, 'UTF-8');
    $gender      = ($_POST['gender'] === 'male') ? 'male' : 'female';
    $pass        = $_POST['pass']; // store as plain text

    if (empty($employee_id) || empty($lastname) || empty($firstname) || empty($pass)) {
        echo "<script>alert('Employee ID, Last Name, First Name, and Password are required.'); location='add_adviser.php'</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Handle Photo Upload
        $photo_filename = upload_adviser_photo('adviser_photo');

        // Check by employee_id (primary key)
        $dup_employee = $conn->prepare("SELECT employee_id FROM advisers WHERE employee_id = ?");
        $dup_employee->bind_param("s", $employee_id);
        $dup_employee->execute();
        if ($dup_employee->get_result()->num_rows > 0) {
            throw new Exception("Employee ID '$employee_id' already exists.");
        }
        $dup_employee->close();

        $stmt = $conn->prepare("INSERT INTO advisers (employee_id, lastname, firstname, middlename, suffix, gender, pass, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssss", $employee_id, $lastname, $firstname, $middlename, $suffix, $gender, $pass, $photo_filename);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        $conn->commit();

        header("Location: add_adviser.php?status=added");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log(date('c') . " Add Adviser Error: " . $e->getMessage() . "\n", 3, $adviser_error_log);
        echo "<script>alert('Error adding adviser: " . addslashes($e->getMessage()) . "'); location='add_adviser.php'</script>";
        exit();
    }
}

// --- Handle Delete Adviser ---
if (isset($_POST['delete_adviser'])) {
    validate_csrf_token($adviser_error_log);
    $employee_id = htmlspecialchars(trim($_POST['employee_id']), ENT_QUOTES, 'UTF-8');
    
    if (empty($employee_id)) {
        echo "<script>alert('Invalid Employee ID.'); location='add_adviser.php'</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Get photo to delete from filesystem
        $get_photo = $conn->prepare("SELECT photo FROM advisers WHERE employee_id = ?");
        $get_photo->bind_param("s", $employee_id);
        $get_photo->execute();
        $photo_to_delete = $get_photo->get_result()->fetch_assoc()['photo'] ?? null;
        $get_photo->close();

        // Check sections using employee_id
        $check_sections = $conn->prepare("SELECT COUNT(*) AS count FROM sections WHERE employee_id = ?");
        $check_sections->bind_param("s", $employee_id);
        $check_sections->execute();
        $section_count = $check_sections->get_result()->fetch_assoc()['count'];
        $check_sections->close();

        if ($section_count > 0) {
            throw new Exception("Cannot delete adviser. They are assigned to $section_count section(s). Please reassign or delete sections first.");
        }

        // Delete using employee_id
        $stmt = $conn->prepare("DELETE FROM advisers WHERE employee_id = ?");
        $stmt->bind_param("s", $employee_id);
        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();

        $conn->commit();

        // Delete file from filesystem after DB commit
        if ($photo_to_delete && file_exists($upload_dir . $photo_to_delete)) {
            unlink($upload_dir . $photo_to_delete);
        }

        header("Location: add_adviser.php?status=deleted");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log(date('c') . " Delete Adviser Error: " . $e->getMessage() . "\n", 3, $adviser_error_log);
        echo "<script>alert('Delete failed: " . addslashes($e->getMessage()) . "'); location='add_adviser.php'</script>";
        exit();
    }
}

// --- Handle Edit Adviser ---
if (isset($_POST['edit_adviser'])) {
    validate_csrf_token($adviser_error_log);
    $old_employee_id = htmlspecialchars(trim($_POST['old_employee_id']), ENT_QUOTES, 'UTF-8');
    $employee_id = htmlspecialchars(trim($_POST['edit_employee_id']), ENT_QUOTES, 'UTF-8');
    $lastname    = htmlspecialchars(trim($_POST['edit_lastname']), ENT_QUOTES, 'UTF-8');
    $firstname   = htmlspecialchars(trim($_POST['edit_firstname']), ENT_QUOTES, 'UTF-8');
    $middlename  = htmlspecialchars(trim($_POST['edit_middlename']), ENT_QUOTES, 'UTF-8');
    $suffix      = htmlspecialchars(trim($_POST['edit_suffix']), ENT_QUOTES, 'UTF-8');
    $gender      = ($_POST['edit_gender'] === 'female') ? 'female' : 'male';
    $pass_new    = trim($_POST['edit_pass']);

    if (empty($old_employee_id) || empty($employee_id) || empty($lastname) || empty($firstname)) {
        echo "<script>alert('Employee ID, Last Name, and First Name are required.'); location='add_adviser.php'</script>";
        exit();
    }

    $conn->begin_transaction();
    try {
        // Get existing photo
        $get_photo = $conn->prepare("SELECT photo FROM advisers WHERE employee_id = ?");
        $get_photo->bind_param("s", $old_employee_id);
        $get_photo->execute();
        $existing_photo = $get_photo->get_result()->fetch_assoc()['photo'] ?? null;
        $get_photo->close();

        // Handle new photo upload
        $new_photo = upload_adviser_photo('edit_adviser_photo', $existing_photo);

        // Check if new employee_id conflicts (if changed)
        if ($old_employee_id !== $employee_id) {
            $dup_employee = $conn->prepare("SELECT employee_id FROM advisers WHERE employee_id = ?");
            $dup_employee->bind_param("s", $employee_id);
            $dup_employee->execute();
            if ($dup_employee->get_result()->num_rows > 0) {
                throw new Exception("The new Employee ID '$employee_id' already exists for another adviser.");
            }
            $dup_employee->close();
        }

        $sql_parts = [
            "employee_id = ?", "lastname = ?", "firstname = ?", "middlename = ?", 
            "suffix = ?", "gender = ?", "photo = ?"
        ];
        $params = [$employee_id, $lastname, $firstname, $middlename, $suffix, $gender, $new_photo];
        $types = "sssssss";
        
        if (!empty($pass_new)) {
            $sql_parts[] = "pass = ?";
            $params[] = $pass_new;
            $types .= "s";
        }
        
        $params[] = $old_employee_id;
        $types .= "s";

        $sql = "UPDATE advisers SET " . implode(", ", $sql_parts) . " WHERE employee_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);

        if (!$stmt->execute()) {
            throw new Exception($stmt->error);
        }
        $stmt->close();
        $conn->commit();
        header("Location: add_adviser.php?status=updated");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        error_log(date('c') . " Edit Adviser Error: " . $e->getMessage() . "\n", 3, $adviser_error_log);
        echo "<script>alert('Error updating adviser: " . addslashes($e->getMessage()) . "'); location='add_adviser.php'</script>";
        exit();
    }
}

/***********************************
CSV IMPORT FOR ADVISERS - PLAIN PASSWORD
***********************************/
function safeTrimCSV($value) {
    if (is_null($value) || is_array($value) || is_bool($value) || is_object($value)) {
        return '';
    }
    return trim(preg_replace('/^\xEF\xBB\xBF/', '', (string)$value));
}

if (isset($_POST['import_advisers_csv']) && isset($_FILES['adviser_csvfile']) && $_FILES['adviser_csvfile']['error'] === UPLOAD_ERR_OK) {
    validate_csrf_token($adviser_error_log);
    $tmpPath = $_FILES['adviser_csvfile']['tmp_name'];
    $fileExt = strtolower(pathinfo($_FILES['adviser_csvfile']['name'], PATHINFO_EXTENSION));

    if ($fileExt !== 'csv') {
        echo "<script>alert('Please upload a CSV file.'); location='add_adviser.php'</script>";
        exit();
    }
    if ($_FILES['adviser_csvfile']['size'] > 5 * 1024 * 1024) {
        echo "<script>alert('File size exceeds 5MB limit.'); location='add_adviser.php'</script>";
        exit();
    }

    if (($handle = fopen($tmpPath, 'r')) !== false) {
        ini_set('auto_detect_line_endings', 1);
        $header = fgetcsv($handle, 2000, ",");

        $expected_fields = [
            'employee_id' => ['employee_id'], 'lastname' => ['lastname', 'last_name'],
            'firstname'   => ['firstname', 'first_name'], 'middlename'  => ['middlename', 'middle_name'],
            'suffix' => ['suffix'], 'gender' => ['gender'], 'pass' => ['pass', 'password']
        ];
        
        $header_normalized = array_map(fn($col) => strtolower(trim($col)), $header);
        $col_map = [];
        foreach ($expected_fields as $field => $possible_headers) {
            $index = false;
            foreach ($possible_headers as $h) {
                $found_index = array_search($h, $header_normalized, true);
                if ($found_index !== false) {
                    $index = $found_index;
                    break;
                }
            }
            if ($index === false) {
                fclose($handle);
                echo "<script>alert('Missing required column: " . ucfirst(str_replace('_', ' ', $field)) . " (expected: " . implode(' or ', $possible_headers) . ")'); location='add_adviser.php'</script>";
                exit();
            }
            $col_map[$field] = $index;
        }

        $conn->begin_transaction();
        try {
            $conn->query("SET FOREIGN_KEY_CHECKS = 0");
            $conn->query("TRUNCATE TABLE sections");
            $conn->query("TRUNCATE TABLE advisers");
            $conn->query("SET FOREIGN_KEY_CHECKS = 1");

            // Include photo in insert (NULL)
            $stmt = $conn->prepare("INSERT INTO advisers (employee_id, lastname, firstname, middlename, suffix, gender, pass, photo) VALUES (?, ?, ?, ?, ?, ?, ?, NULL)");
            
            $rowCount = 0;
            $errors = [];
            $row_num = 1;

            while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                $row_num++;
                if (!is_array($data) || empty(array_filter($data, fn($d) => $d !== ''))) continue;

                $csv_employee_id = safeTrimCSV($data[$col_map['employee_id']] ?? '');
                $csv_lastname    = htmlspecialchars(safeTrimCSV($data[$col_map['lastname']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_firstname   = htmlspecialchars(safeTrimCSV($data[$col_map['firstname']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_middlename  = htmlspecialchars(safeTrimCSV($data[$col_map['middlename']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_suffix      = htmlspecialchars(safeTrimCSV($data[$col_map['suffix']] ?? ''), ENT_QUOTES, 'UTF-8');
                $csv_gender      = strtolower(safeTrimCSV($data[$col_map['gender']] ?? '')) === 'female' ? 'female' : 'male';
                $csv_pass        = safeTrimCSV($data[$col_map['pass']] ?? '');

                if (empty($csv_employee_id) || empty($csv_lastname) || empty($csv_firstname) || empty($csv_pass)) {
                    $errors[] = "Row $row_num: Missing required fields.";
                    continue;
                }

                $stmt->bind_param("sssssss", $csv_employee_id, $csv_lastname, $csv_firstname, $csv_middlename, $csv_suffix, $csv_gender, $csv_pass);
                
                if ($stmt->execute()) {
                    $rowCount++;
                } else {
                    $errors[] = "Row $row_num: Insert failed (" . $stmt->error . ").";
                    error_log(date('c') . " CSV Import Row Error: " . $stmt->error . " for row " . $row_num . "\n", 3, $adviser_error_log);
                }
            }
            fclose($handle);
            $stmt->close();
            $conn->commit();

            $message = "CSV Import Complete!\nSuccessfully imported: $rowCount advisers.";
            if (!empty($errors)) {
                $message .= "\n\n" . count($errors) . " rows failed. Check logs for details.";
            }
            echo "<script>alert(" . json_encode($message) . "); location='add_adviser.php';</script>";
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $conn->query("SET FOREIGN_KEY_CHECKS = 1"); 
            error_log(date('c') . " CSV Import Fatal Error: " . $e->getMessage() . "\n", 3, $adviser_error_log);
            echo "<script>alert('CSV Import failed: " . addslashes($e->getMessage()) . "'); location='add_adviser.php'</script>";
            exit();
        }
    } else {
        echo "<script>alert('Failed to read CSV file. Check file permissions or integrity.'); location='add_adviser.php'</script>";
        exit();
    }
}

// --- Fetch advisers for display ---
$advisers_result = $conn->query("SELECT employee_id, lastname, firstname, middlename, suffix, gender, pass, photo FROM advisers ORDER BY lastname, firstname");
if (!$advisers_result) {
    error_log(date('c') . " Fetch Advisers Error: " . $conn->error . "\n", 3, $general_error_log);
    die("Error fetching advisers.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Advisers Dashboard</title>
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
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
        .search-box .search-icon, .clear-search {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            color: #6b7280;
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
        .modal-header { background-color: #007bff; color: white; }
        .modal-header .btn-close { filter: invert(1); }
        .adviser-photo-thumb {
            width: 40px; height: 40px; object-fit: cover;
            border-radius: 50%; border: 2px solid #e5e7eb;
        }
        .adviser-photo-preview {
            width: 120px; height: 120px; object-fit: cover;
            border-radius: 8px; border: 2px solid #e5e7eb;
            margin-bottom: 10px; background-color: #f3f4f6;
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
                            <img src="img/depedlogo.jpg" alt="Adviser Logo" class="page-logo">
                            <h2 class="h3 mb-0 text-gray-800">Advisers Dashboard</h2>
                        </div>
                        <div class="d-flex align-items-center">
                            <div class="search-box">
                                <input type="text" id="searchAdviser" placeholder="Search advisers..." autocomplete="off">
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
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAdviserModal">
                                <span class="material-symbols-outlined" style="vertical-align: middle;">person_add</span> Add Adviser
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
                                        <th>Employee ID</th>
                                        <th>Photo</th>
                                        <th>Last Name</th>
                                        <th>First Name</th>
                                        <th>Middle Name</th>
                                        <th>Suffix</th>
                                        <th>Gender</th>
                                        <th>Password</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $counter = 1;
                                    if ($advisers_result && $advisers_result->num_rows > 0):
                                        while ($row = $advisers_result->fetch_assoc()):
                                    ?>
                                    <tr>
                                        <td><?= $counter++ ?></td>
                                        <td><?= htmlspecialchars($row['employee_id']) ?></td>
                                        <td>
                                            <?php if(!empty($row['photo'])): ?>
                                                <img src="<?= $upload_dir . htmlspecialchars($row['photo']) ?>" class="adviser-photo-thumb" alt="Adviser Photo">
                                            <?php else: ?>
                                                <span class="text-muted text-sm">No Photo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="fw-medium"><?= htmlspecialchars($row['lastname']) ?></td>
                                        <td><?= htmlspecialchars($row['firstname']) ?></td>
                                        <td><?= htmlspecialchars($row['middlename']) ?></td>
                                        <td><?= htmlspecialchars($row['suffix']) ?></td>
                                        <td><span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full"><?= ucfirst($row['gender']) ?></span></td>
                                        <td class="font-monospace text-sm"><?= htmlspecialchars($row['pass']) ?></td>
                                        <td class="actions-cell">
                                            <button onclick="openEditAdviserModal(
                                                '<?= htmlspecialchars($row['employee_id'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['lastname'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['firstname'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['middlename'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['suffix'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['gender'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($row['photo'], ENT_QUOTES) ?>'
                                            )" class="action-icon-btn edit-icon" title="Edit Adviser">
                                                <span class="material-symbols-outlined">edit</span>
                                            </button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>? This is permanent.');">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <input type="hidden" name="employee_id" value="<?= htmlspecialchars($row['employee_id']) ?>">
                                                <button type="submit" name="delete_adviser" class="action-icon-btn delete-icon" title="Delete Adviser">
                                                    <span class="material-symbols-outlined">delete</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="10" class="text-center text-muted py-5">
                                            <span class="material-symbols-outlined" style="font-size:3rem; opacity:0.5;">group_off</span>
                                            <div class="mt-2">No advisers found.</div>
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
    <!-- The PHP and HTML for modals and the toast are unchanged as they are already well-implemented. -->
    
    <!-- Add Adviser Modal -->
    <div class="modal fade" id="addAdviserModal" tabindex="-1" aria-labelledby="addAdviserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addAdviserModalLabel">
                            <span class="material-symbols-outlined me-2" style="vertical-align: text-bottom;">person_add</span>Add New Adviser
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <label class="form-label fw-bold">Adviser Photo</label>
                            <img id="add_photo_preview" src="#" class="adviser-photo-preview mx-auto d-none" alt="Preview">
                            <input type="file" name="adviser_photo" class="form-control" accept="image/*">
                            <div class="form-text">Optional. Max 2MB. JPG, PNG, GIF.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" name="employee_id" class="form-control" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="lastname" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="firstname" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" name="middlename" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Suffix</label>
                                <input type="text" name="suffix" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="" disabled selected>Select Gender</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password <span class="text-danger">*</span></label>
                            <input type="password" name="pass" class="form-control" required minlength="6">
                            <div class="form-text">Minimum 6 characters (stored as plain text)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_adviser" class="btn btn-primary"><span class="material-symbols-outlined me-1" style="vertical-align: text-bottom;">save</span>Add Adviser</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Adviser Modal -->
    <div class="modal fade" id="editAdviserModal" tabindex="-1" aria-labelledby="editAdviserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" id="old_employee_id" name="old_employee_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editAdviserModalLabel"><span class="material-symbols-outlined me-2" style="vertical-align: text-bottom;">edit</span>Edit Adviser</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <label class="form-label fw-bold">Update Photo</label>
                            <img id="edit_photo_display" src="#" class="adviser-photo-preview mx-auto" alt="Current Photo">
                            <input type="file" name="edit_adviser_photo" class="form-control" accept="image/*">
                            <div class="form-text">Leave empty to keep current photo.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" id="edit_employee_id" name="edit_employee_id" class="form-control" required>
                        </div>
                        <div class="row">
                           <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">Last Name <span class="text-danger">*</span></label>
                                <input type="text" id="edit_lastname" name="edit_lastname" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold">First Name <span class="text-danger">*</span></label>
                                <input type="text" id="edit_firstname" name="edit_firstname" class="form-control" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Middle Name</label>
                                <input type="text" id="edit_middlename" name="edit_middlename" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Suffix</label>
                                <input type="text" id="edit_suffix" name="edit_suffix" class="form-control">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Gender <span class="text-danger">*</span></label>
                            <select id="edit_gender" name="edit_gender" class="form-select" required>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" id="edit_pass" name="edit_pass" class="form-control" minlength="6" placeholder="Leave blank to keep current">
                            <div class="form-text">Stored as plain text.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_adviser" class="btn btn-primary"><span class="material-symbols-outlined me-1" style="vertical-align: text-bottom;">save</span>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import CSV Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">Import Advisers from CSV</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <div class="modal-body">
                        <div class="alert alert-warning" role="alert">
                            <strong>Warning:</strong> Importing will delete all existing sections and advisers before adding the new records.
                        </div>
                        <div class="mb-3">
                            <label for="adviser_csvfile" class="form-label">Select CSV File *</label>
                            <input type="file" name="adviser_csvfile" id="adviser_csvfile" class="form-control" accept=".csv" required>
                            <div class="form-text mt-2">Required columns: <code>employee_id, lastname, firstname, middlename, suffix, gender, pass</code> (or <code>password</code>)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="import_advisers_csv" class="btn btn-primary">Upload & Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Auto-Vanish Alert Toast -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100">
        <div id="liveToast" class="toast align-items-center text-white border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body" id="toastMessage"></div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- JavaScript remains the same -->
    <script>
    $(document).ready(function(){
        // Search functionality
        const searchInput = $('#searchAdviser');
        const clearBtn = $('#clearSearch');
        const tableRows = $('.custom-table tbody tr');
        
        searchInput.on('input', function() {
            const searchTerm = $(this).val().toLowerCase().trim();
            $(this).val().length > 0 ? clearBtn.addClass('show') : clearBtn.removeClass('show');
            let visibleCount = 0;
            
            tableRows.each(function(){
                const row = $(this);
                if (row.find('td[colspan]').length) return; // Skip 'no results' row
                
                if(row.text().toLowerCase().includes(searchTerm)){
                    row.show(); 
                    visibleCount++;
                } else {
                    row.hide();
                }
            });

            $('#noResults').remove();
            if(visibleCount === 0 && tableRows.length > 1 && searchTerm.length > 0) {
                $('.custom-table tbody').append('<tr id="noResults"><td colspan="10" class="text-center text-muted py-4"><span class="material-symbols-outlined">search_off</span> No advisers found matching your search.</td></tr>');
            }
        });
        
        clearBtn.on('click', () => searchInput.val('').trigger('input').focus());
        searchInput.on('keydown', (e) => { if(e.key === 'Escape') clearBtn.click(); });

        // Photo Preview handlers
        const setupPhotoPreview = (inputId, previewId) => {
            $(inputId).change(function(e) {
                if (e.target.files && e.target.files[0]) {
                    const reader = new FileReader();
                    reader.onload = (event) => $(previewId).attr('src', event.target.result).removeClass('d-none');
                    reader.readAsDataURL(e.target.files[0]);
                }
            });
        };
        setupPhotoPreview('input[name="adviser_photo"]', '#add_photo_preview');
        setupPhotoPreview('input[name="edit_adviser_photo"]', '#edit_photo_display');

        // Handle Status Toast
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        if (status) {
            const toastEl = $('#liveToast');
            const toastBody = $('#toastMessage');
            const formattedStatus = status.charAt(0).toUpperCase() + status.slice(1);
            toastBody.text(`Adviser ${formattedStatus} successfully!`);
            
            toastEl.removeClass('bg-success bg-danger').addClass(status === 'deleted' ? 'bg-danger' : 'bg-success');
            
            new bootstrap.Toast(toastEl[0], { delay: 4000 }).show();
            window.history.replaceState({}, document.title, window.location.pathname);
        }
    });

    // Function to open Edit Modal
    function openEditAdviserModal(employee_id, lastname, firstname, middlename, suffix, gender, photo) {
        $('#old_employee_id').val(employee_id);
        $('#edit_employee_id').val(employee_id);
        $('#edit_lastname').val(lastname);
        $('#edit_firstname').val(firstname);
        $('#edit_middlename').val(middlename);
        $('#edit_suffix').val(suffix);
        $('#edit_gender').val(gender);
        $('#edit_pass').val('');
        
        const photoImg = $('#edit_photo_display');
        photoImg.attr('src', (photo && photo.trim() !== '') ? `<?= $upload_dir ?>${photo}` : 'https://via.placeholder.com/120?text=No+Photo');

        new bootstrap.Modal($('#editAdviserModal')[0]).show();
    }
    </script>
</body>
</html>
<?php include 'footer.php'; ?>