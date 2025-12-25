<?php
session_start();
require 'conn.php';

// ❌ Block unauthorized users
if (!isset($_SESSION['faculty_logged_in'])) {
    header("Location: faculty_login.php");
    exit();
}

// Optional: get faculty info
$sql = "
    SELECT 
        a.firstname,
        a.lastname
    FROM advisers a
    INNER JOIN faculty_login f 
        ON a.employee_id = f.employee_id
    WHERE f.faculty_id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['faculty_id']);
$stmt->execute();
$faculty = $stmt->get_result()->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Faculty Dashboard</title>
</head>
<body>

<h2>Welcome, <?php echo $faculty['firstname']; ?></h2>
<p>Department: <?php echo $faculty['department']; ?></p>
<p>Position: <?php echo $faculty['position']; ?></p>

<a href="faculty_logout.php">Logout</a>

<!-- <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Faculty Dashboard</title>
        
    <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,200i,300,300i,400,400i,600,600i,700,700i,800,800i,900,900i" rel="stylesheet">

    <link href="css/sb-admin-2.min.css" rel="stylesheet">
   
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
    body {
            font-weight: 700 !important; /* 700 is the numeric value for 'bold' */
        }

        /* Custom styles for sidebar toggling */
        #accordionSidebar {
            transition: width 0.3s ease-in-out, margin 0.3s ease-in-out;
            background-color: rgb(7, 29, 230);
        }

        #accordionSidebar.toggled {
            width: 0 !important;
            overflow: hidden;
            margin-left: -225px;
        }

        #content-wrapper.toggled {
            margin-left: 0;
        }

        #content-wrapper {
            transition: margin 0.3s ease-in-out;
        }

        #sidebarToggle {
            background-color: transparent;
            color: rgba(255, 255, 255, 0.8);
            border: none;
            outline: none;
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #sidebarToggle:hover {
            color: white;
        }

        #sidebarToggle i {
            transition: transform 0.3s ease-in-out;
        }

        #accordionSidebar.toggled #sidebarToggle i {
            transform: rotate(180deg);
        }

        /* Social media links styling */
        .social-links .nav-link {
            padding: 0.5rem 1rem;
        }
        
        /* Custom button color for Maroon */
        .btn-maroon {
            color: #fff;
            background-color: #800000; /* Maroon */
            border-color: #800000;
        }

        .btn-maroon:hover {
            color: #fff;
            background-color: #660000; /* Darker Maroon on hover */
            border-color: #590000;
        }
        
        /* Navigation item styling */
        .nav-item .nav-link {
            display: flex;
            align-items: center;
        }
        
        .nav-item .nav-link i {
            margin-right: 0.5rem;
        }

        /* Adjustments for direct links in topbar */
        .topbar .navbar-nav .nav-item .nav-link {
            display: flex;
            align-items: center;
            height: 100%; /* Ensure full height for vertical alignment */
            padding-right: 0.75rem; /* Match existing padding if needed */
            padding-left: 0.75rem; /* Match existing padding if needed */
        }

        .topbar .navbar-nav .nav-item .nav-link .img-profile {
            height: 2rem; /* Adjust image size for inline display */
            width: 2rem;
        }
        .topbar .navbar-nav .nav-item .nav-link i {
            margin-right: 0.5rem; /* Spacing for icons */
        }

    </style>
</head>
<body id="page-top">

    <div id="wrapper">

        
        <ul class="navbar-nav sidebar sidebar-dark accordion" id="accordionSidebar">

           
            <a class="sidebar-brand d-flex flex-column align-items-center justify-content-center" href="admin_dashboard.php" style="padding: 1.5rem 1rem; height: auto;">
                <img src="img/logo.jpg" alt="School Logo" class="rounded-circle mb-2" style="width: 85px; height: 85px; object-fit: cover;">
                <div class="sidebar-brand-text" style="font-weight: 500; font-size: 1rem;">FACULTY</div>
            </a>

            
            <hr class="sidebar-divider my-0">

          
            <li class="nav-item active">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="fas fa-fw fa-tachometer-alt"></i>
                    <span>My Students</span>
                </a>
            </li>

           
            <hr class="sidebar-divider">

          
            <div class="sidebar-heading">
                Teachers Management
            </div>

           
            <li class="nav-item">
                <a class="nav-link" href="add_adviser.php">
                    <i class="fa fa-chalkboard-teacher"></i>
                    <span>View Attendance</span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link" href="section_student.php">
                    <i class="fa fa-book-reader"></i>
                    <span>Generate SF2 Report</span>
                </a>
            </li>

            
            <hr class="sidebar-divider d-none d-md-block">

        </ul>
        <div id="content-wrapper" class="d-flex flex-column">

          
            <div id="content">

                
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">

                   
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>

                    <ul class="navbar-nav ml-auto">

                        <li class="nav-item dropdown no-arrow d-sm-none">
                            <a class="nav-link dropdown-toggle" href="#" id="searchDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="fas fa-search fa-fw"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right p-3 shadow animated--grow-in" aria-labelledby="searchDropdown">
                                <form class="form-inline mr-auto w-100 navbar-search">
                                    <div class="input-group">
                                        <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search">
                                        <div class="input-group-append">
                                            <button class="btn btn-primary" type="button">
                                                <i class="fas fa-search fa-sm"></i>
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </li>

                        <div class="topbar-divider d-none d-sm-block"></div>

                        
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($userEmail) ?></span>
                                <img class="img-profile rounded-circle" src="img/profile.svg">
                            </a>
                        </li>
                        
                       
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-toggle="modal" data-target="#logoutModal">
                                <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                <span class="d-none d-lg-inline text-gray-600 small">Logout</span>
                            </a>
                        </li>

                    </ul>

                </nav>



</body>
</html> -->
</body>
</html>