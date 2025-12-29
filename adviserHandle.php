<?php
// my_students.php
session_start();

// ✅ Faculty access only
if (!isset($_SESSION['faculty_logged_in'])) {
    header('Location: faculty_login.php');
    exit();
}

require 'conn.php';
$conn->set_charset("utf8mb4");

/* ======================================================
   ✅ HANDLE STUDENT PHOTO UPLOAD (FACULTY)
====================================================== */
if (isset($_POST['upload_student_image'])) {
    $lrn = $_POST['student_lrn'] ?? '';
    
    if (isset($_FILES['student_image']) && $_FILES['student_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/uploads/';
        $ext = strtolower(pathinfo($_FILES['student_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed)) {
            // Remove old image if exists
            $check = $conn->prepare("SELECT profile_image FROM students WHERE lrn = ?");
            $check->bind_param("s", $lrn);
            $check->execute();
            $old = $check->get_result()->fetch_assoc();
            $check->close();

            if (!empty($old['profile_image']) && file_exists($uploadDir . $old['profile_image'])) {
                unlink($uploadDir . $old['profile_image']);
            }

            // Save new image
            $filename = 'student_' . $lrn . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['student_image']['tmp_name'], $uploadDir . $filename)) {
                $stmt = $conn->prepare("UPDATE students SET profile_image = ? WHERE lrn = ?");
                $stmt->bind_param("ss", $filename, $lrn);
                $stmt->execute();
                $stmt->close();
                echo "<script>alert('Photo updated successfully!'); window.location.href='adviserHandle.php';</script>";
                exit();
            }
        }
    }
}

/**
 * ✅ Get faculty information
 */
$faculty_sql = "
    SELECT a.employee_id, CONCAT(a.firstname, ' ', a.lastname) AS faculty_name, a.photo,
    GROUP_CONCAT(CONCAT(s.grade_level, ' - ', s.section_name) SEPARATOR ', ') AS handled_sections
    FROM faculty_login f 
    INNER JOIN advisers a ON a.employee_id = f.employee_id
    LEFT JOIN sections s ON s.employee_id = a.employee_id 
    WHERE f.faculty_id = ? 
    GROUP BY a.employee_id
";

$stmt = $conn->prepare($faculty_sql);
$stmt->bind_param("i", $_SESSION['faculty_id']);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
$stmt->close();

$handled_sections = $faculty['handled_sections'] ?? 'No sections assigned';
$faculty_display_name = $faculty['faculty_name'] ?? 'Teacher';

// Faculty Profile Photo logic
$facultyPhotoPath = 'uploads/advisers/' . ($faculty['photo'] ?? 'profile.svg');
if (!file_exists($facultyPhotoPath) || empty($faculty['photo'])) {
    $facultyPhotoPath = 'img/profile.svg';
}

/**
 * ✅ Fetch students handled by this faculty member
 */
$students_query = "
    SELECT
        e.lrn, e.grade_level, e.section_name, e.school_year,
        st.firstname AS student_firstname, st.lastname AS student_lastname,
        st.profile_image, st.sex
    FROM enrollments e
    INNER JOIN students st ON e.lrn = st.lrn
    INNER JOIN sections sec ON sec.section_name = e.section_name AND sec.grade_level = e.grade_level
    WHERE sec.employee_id = ?
    ORDER BY st.lastname ASC, st.firstname ASC
";

$stmt = $conn->prepare($students_query);
$stmt->bind_param("s", $faculty['employee_id']);
$stmt->execute();
$students = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>My Students - Faculty Dashboard</title>
    
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="css/sb-admin-2.min.css" rel="stylesheet">
    
    <style>
        #accordionSidebar { background-color: #800000 !important; }
        .btn-maroon { background-color: #800000; color: white; border: none; }
        .btn-maroon:hover { background-color: #600000; color: white; }
        
        /* Topbar specific styles to match image */
        .topbar-divider {
            width: 0;
            border-right: 1px solid #e3e6f0;
            height: 2.3rem;
            margin: auto 1rem;
        }
        .faculty-name-top {
            color: #5a5c69;
            font-size: 0.85rem;
            margin-right: 10px;
        }
        .img-profile-custom {
            width: 32px;
            height: 32px;
            object-fit: cover;
            border-radius: 50%;
        }

        /* UPDATED LOGOUT LINK STYLE */
        .logout-link {
            color: #858796; /* Default gray */
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            transition: color 0.2s;
        }
        .logout-link:hover {
            color: #800000; /* Turns Maroon on Hover */
            text-decoration: none;
        }

        /* Social Media Styles */
        .sidebar-social-heading {
            color: rgba(255, 255, 255, 0.4);
            font-weight: 800;
            font-size: .65rem;
            text-transform: uppercase;
            letter-spacing: .05rem;
            padding: 0 1rem;
            margin-top: 1rem;
        }
        .nav-social-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: rgba(255, 255, 255, 0.8) !important;
            text-decoration: none;
            font-weight: 600;
            transition: 0.3s;
        }
        .nav-social-link:hover {
            color: #fff !important;
            background: rgba(255, 255, 255, 0.1);
        }
        .nav-social-link i {
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
        }

        /* Table Styling */
        .table-card {
            background: #fff;
            border-radius: 8px;
            padding: 10px;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.05);
            border: 1px solid #e3e6f0;
        }
        .table-bordered { border: 1px solid #e3e6f0 !important; }
        .table-bordered th, .table-bordered td { border: 1px solid #e3e6f0 !important; }
        .table thead th { background-color: #f8f9fc; color: #858796; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; }
        .student-name-text { color: #5a5c69; font-weight: 500; text-transform: uppercase; }
        .student-img-circle { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; border: 1px solid #eaecf4; }
        .search-container { position: relative; max-width: 300px; }
        .search-container input { border-radius: 20px; padding-left: 35px; border: 1px solid #d1d3e2; }
        .search-container i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #d1d3e2; }
        .badge-maroon { background-color: #800000; color: white; }
        .handled-pill { display: inline-block; padding: .35rem .6rem; border-radius: 999px; background: #f8f9fc; border: 1px solid #e3e6f0; color: #5a5c69; font-size: .8rem; }
    </style>
</head>

<body id="page-top">
    <div id="wrapper">
        <!-- Sidebar -->
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">
            <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center" href="faculty_dashboard.php" style="padding: 1.5rem 1rem; height: auto;">
                <img src="img/logo.jpg" alt="Logo" class="rounded-circle mb-2" style="width: 70px; height: 70px; object-fit: cover;">
                <div class="sidebar-brand-text">FACULTY</div>
            </a>
            <hr class="sidebar-divider my-0">
            
            <li class="nav-item"><a class="nav-link" href="faculty_dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
            
            <hr class="sidebar-divider">
            
            <!-- MY CLASS SECTION -->
            <div class="sidebar-heading">My Class</div>
            <li class="nav-item active"><a class="nav-link" href="adviserHandle.php"><i class="fas fa-users"></i><span>My Students</span></a></li>
            <li class="nav-item"><a class="nav-link" href="FacAttRecord.php"><i class="fas fa-calendar-check"></i><span>Attendance Records</span></a></li>
            <li class="nav-item"><a class="nav-link" href="generate_sf2.php"><i class="fas fa-file-export"></i><span>Generate SF2</span></a></li>

            <hr class="sidebar-divider">
            
            <!-- SOCIAL MEDIA SECTION -->
            <div class="sidebar-social-heading">Social Media</div>
            <li class="nav-item">
                <a class="nav-social-link" href="https://web.facebook.com/DepEdTayoSINHS301394.official/?_rdc=1&_rdr#" target="_blank">
                    <i class="fab fa-facebook-f"></i><span>Facebook</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-social-link" href="https://www.youtube.com/channel/UCDw3mhzSTm_NFk_2dFbhBKg" target="_blank">
                    <i class="fab fa-youtube"></i><span>YouTube</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-social-link" href="https://ph.search.yahoo.com/search" target="_blank">
                    <i class="fab fa-google"></i><span>Google</span>
                </a>
            </li>
            <hr class="sidebar-divider d-none d-md-block">
        </ul>

        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <!-- Topbar -->
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
                    
                    <ul class="navbar-nav ml-auto align-items-center">
                        <div class="topbar-divider"></div>
                        <li class="nav-item d-flex align-items-center">
                            <span class="faculty-name-top">Teacher <?= htmlspecialchars($faculty_display_name) ?></span>
                            <img class="img-profile-custom" src="<?= $facultyPhotoPath ?>" onerror="this.src='img/profile.svg'">
                            
                            <!-- UPDATED LOGOUT LINK TO TRIGGER MODAL -->
                            <li class="nav-item ml-3">
                                <a class="logout-link" href="#" data-toggle="modal" data-target="#logoutModal">
                                    <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2"></i>
                                    Logout
                                </a>
                            </li>
                        </li>
                    </ul>
                </nav>

                <div class="container-fluid">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between mb-4">
                        <div>
                            <h1 class="h3 mb-1 text-gray-800"><i class="fas fa-users"></i> My Students</h1>
                            <span class="handled-pill">Sections: <strong><?= htmlspecialchars($handled_sections) ?></strong></span>
                        </div>
                        <div class="d-flex align-items-center mt-2 mt-md-0">
                            <div class="search-container mr-3"><i class="fas fa-search"></i><input type="text" id="studentSearch" class="form-control form-control-sm" placeholder="Search students..."></div>
                            <span class="badge badge-maroon p-2">Total Students: <?= $students->num_rows ?></span>
                        </div>
                    </div>

                    <div class="table-card">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle" id="studentTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Photo</th>
                                        <th>Student Name</th>
                                        <th>LRN</th>
                                        <th>Grade Level</th>
                                        <th>Section</th>
                                        <th>Sex</th>
                                        <th class="text-center">UPLOAD PHOTO</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php $i = 1; while ($row = $students->fetch_assoc()): 
                                    $s_img = (!empty($row['profile_image']) && file_exists('uploads/'.$row['profile_image'])) ? 'uploads/'.$row['profile_image'] : 'img/profile.svg';
                                    $fullname = $row['student_lastname'] . ', ' . $row['student_firstname'];
                                ?>
                                    <tr>
                                        <td><?= $i++ ?></td>
                                        <td class="text-center"><img src="<?= $s_img ?>" class="student-img-circle"></td>
                                        <td class="student-name-text"><?= htmlspecialchars($fullname) ?></td>
                                        <td><?= htmlspecialchars($row['lrn']) ?></td>
                                        <td><?= htmlspecialchars($row['grade_level']) ?></td>
                                        <td><?= htmlspecialchars($row['section_name']) ?></td>
                                        <td><?= htmlspecialchars($row['sex']) ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-maroon" data-toggle="modal" data-target="#uploadModal" data-lrn="<?= $row['lrn'] ?>" data-name="<?= htmlspecialchars($fullname) ?>">
                                                <i class="fas fa-file-import"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- UPLOAD MODAL -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" enctype="multipart/form-data" class="modal-content">
                <div class="modal-header bg-maroon text-white">
                    <h5 class="modal-title">Update Student Photo</h5>
                    <button class="close text-white" type="button" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="student_lrn" id="uploadLRN">
                    <p>Student: <strong id="studentNameDisplay"></strong></p>
                    <div class="form-group">
                        <label>Select Image</label>
                        <input type="file" name="student_image" class="form-control" accept="image/*" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="upload_student_image" class="btn btn-maroon">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- LOGOUT MODAL -->
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
                    <a class="btn btn-danger" href="faculty_logout.php">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        $('#uploadModal').on('show.bs.modal', function (e) {
            var btn = $(e.relatedTarget);
            $('#uploadLRN').val(btn.data('lrn'));
            $('#studentNameDisplay').text(btn.data('name'));
        });
        $(document).ready(function(){
            $("#studentSearch").on("keyup", function() {
                var value = $(this).val().toLowerCase();
                $("#studentTable tbody tr").filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1)
                });
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>