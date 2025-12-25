<?php
session_start();
require 'conn.php'; // Ensure this points to your database connection file

// Redirect if already logged in
if (isset($_SESSION['faculty_logged_in']) && $_SESSION['faculty_logged_in'] === true) {
    header("Location: faculty_dashboard.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // --- FIX IS HERE: Use '??' to prevent "Undefined array key" error ---
    $employee_id = trim($_POST['employee_id'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($employee_id) || empty($password)) {
        $error = "Please enter both Employee ID and Password.";
    } else {
        // Prepare query to join advisers and faculty_login
        $sql = "
            SELECT 
                a.employee_id,
                a.firstname,
                a.lastname,
                a.pass,
                f.faculty_id,
                f.status
            FROM advisers a
            LEFT JOIN faculty_login f 
                ON a.employee_id = f.employee_id
            WHERE a.employee_id = ?
            LIMIT 1
        ";

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $employee_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Check if account is active
                $status = $user['status'] ?? 'active'; 

                if ($status === 'inactive') {
                    $error = "Your account is currently inactive. Please contact the administrator.";
                } 
                // Verify Password (Plain text check based on your database design)
                elseif ($password === $user['pass']) {
                    
                    // LOGIN SUCCESS
                    session_regenerate_id(true); // Prevent session fixation
                    $_SESSION['faculty_logged_in'] = true;
                    $_SESSION['faculty_id'] = $user['faculty_id'];
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['fullname'] = $user['firstname'] . ' ' . $user['lastname'];

                    // Update Last Login Timestamp
                    $update_sql = "UPDATE faculty_login SET last_login = NOW() WHERE employee_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("s", $user['employee_id']);
                    $update_stmt->execute();
                    $update_stmt->close();

                    header("Location: faculty_dashboard.php");
                    exit();
                } else {
                    $error = "Invalid Password.";
                }
            } else {
                $error = "Employee ID not found.";
            }
            $stmt->close();
        } else {
            $error = "Database error. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Faculty Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="vendor/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">

  <style>
    body {
      margin: 0;
      font-family: 'Nunito', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f8f9fc;
    }

    .login-box {
      padding: 40px 30px;
      width: 100%;
      max-width: 400px;
      background-color: #ffffff;
      border-radius: 15px;
      box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
      position: relative;
      z-index: 2;
    }

    .login-box img.logo {
      width: 100px;
      height: 100px;
      margin-bottom: 20px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    .form-control {
      border-radius: 10px;
      padding: 12px;
      font-size: 0.95rem;
    }

    .btn-login {
      padding: 12px;
      font-size: 16px;
      font-weight: 600;
      border-radius: 10px;
      background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
      border: none;
      transition: all 0.3s ease;
    }

    .btn-login:hover {
      background: linear-gradient(135deg, #224abe 0%, #4e73df 100%);
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
    }

    .img-side {
      position: relative;
      overflow: hidden;
    }

    .img-side img {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    
    .overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.3);
    }

    @media (max-width: 767px) {
      .img-side {
        display: none;
      }
      .login-container {
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fc;
      }
    }
  </style>
</head>

<body>
  
  <div class="container-fluid vh-100">
    <div class="row h-100">
      
      <!-- Image Side (Hidden on Mobile) -->
      <div class="col-md-7 col-lg-8 p-0 img-side">
        <!-- Update the src below to your actual background image -->
        <img src="img/school_bg.jpg" alt="School Background" onerror="this.src='https://source.unsplash.com/1600x900/?school,library'" />
        <div class="overlay"></div>
      </div>

      <!-- Login Form Side -->
      <div class="col-md-5 col-lg-4 d-flex justify-content-center align-items-center bg-light login-container">
        <div class="login-box text-center">
          <!-- Update logo src -->
          <img src="img/depedlogo.jpg" alt="Logo" class="logo" onerror="this.style.display='none'"/>
          
          <h3 class="text-gray-900 mb-4 font-weight-bold">Faculty Login</h3>
          
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert" style="font-size: 0.9rem;">
                <i class="fas fa-exclamation-circle me-1"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
          <?php endif; ?>

          <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="mb-3 text-start">
                <label for="employee_id" class="form-label small text-muted font-weight-bold">Employee ID</label>
                <!-- IMPORTANT: The name attribute here must match $_POST['employee_id'] -->
                <input type="text" name="employee_id" id="employee_id" class="form-control" placeholder="Enter your ID" required autofocus />
            </div>
            
            <div class="mb-4 text-start">
                <label for="password" class="form-label small text-muted font-weight-bold">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required />
            </div>

            <button type="submit" class="btn btn-primary w-100 btn-login text-white mb-3">
                Login
            </button>
            
            <hr>
            <div class="text-center">
                <a class="small text-decoration-none" href="forgot_password.php">Forgot Password?</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>