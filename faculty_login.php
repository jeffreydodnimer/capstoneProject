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
                    echo "<script>
                        alert('Your account is currently inactive. Please contact the administrator.');
                        window.location.href='faculty_login.php';
                    </script>";
                    exit();
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
                    if ($update_stmt = $conn->prepare($update_sql)) {
                        $update_stmt->bind_param("s", $user['employee_id']);
                        $update_stmt->execute();
                        $update_stmt->close();
                    }

                    echo "<script>
                        alert('Login Successful!');
                        window.location.href = 'faculty_dashboard.php';
                    </script>";
                    exit();
                } else {
                    echo "<script>
                        alert('Access Denied! Employee ID or Password incorrect.');
                        window.location.href='faculty_login.php';
                    </script>";
                    exit();
                }
            } else {
                echo "<script>
                    alert('Access Denied! Employee ID or Password incorrect.');
                    window.location.href='faculty_login.php';
                </script>";
                exit();
            }
            $stmt->close();
        } else {
            echo "<script>
                alert('Database error. Please try again later.');
                window.location.href='faculty_login.php';
            </script>";
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Faculty Login</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet" />

  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .login-box {
      padding: 50px 40px;
      max-width: 420px;
      height: auto; 
      min-height: 80%;
      width: 100%;
      background-color: rgb(191, 212, 233);
      border-radius: 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      transition: transform 0.3s ease-in-out;
    }

    .login-box:hover {
      transform: scale(1.02);
    }

    .login-box img.logo {
      width: 120px;
      height: 120px;
      margin-top: 30px;
      margin-bottom: 20px;
      border-radius: 50%;
      object-fit: cover;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    form input[type="text"],
    form input[type="password"] {
      width: 100%;
      padding: 10px;
      margin: 8px 0;
      border-radius: 5px;
      border: 1px solid #ccc;
    }

    form input[type="submit"],
    form button[type="submit"] {
      width: 100%;
      padding: 10px;
      margin-top: 10px;
      font-size: 16px;
      font-weight: 500;
      border-radius: 30px;
      background: linear-gradient(135deg, #DC143C, #B22222);
      color: white;
      border: none;
      transition: background 0.3s ease-in-out;
    }

    form input[type="submit"]:hover,
    form button[type="submit"]:hover {
      background: linear-gradient(135deg, #B22222, #8B0000);
    }

    .object-fit-cover {
      object-fit: cover;
    }

    @media (max-width: 767px) {
      .img-side {
        display: none;
      }

      .login-box {
        padding: 30px 20px;
        border-radius: 10px;
      }
    }
  </style>
</head>

<body>
  <div class="container-fluid vh-100">
    <div class="row h-100">
      <!-- Left: Image -->
      <div class="col-md-8 p-0 img-side">
        <img src="img/pic1.jpg" alt="Visual" class="img-fluid vh-100 w-100 object-fit-cover" />
      </div>

      <!-- Right: Faculty Login -->
      <div class="col-md-4 d-flex justify-content-center align-items-center bg-light">
        <div class="login-box text-center">
          <img src="img/logo.jpg" alt="Logo" class="logo" />
          <h2 style="font-size: 25px; font-weight: 600; margin-bottom: 30px;">Faculty Login</h2>
          
          <form action="" method="POST">
            <input type="text" name="employee_id" class="form-control mb-3" placeholder="Enter Employee ID" required />
            <input type="password" name="password" class="form-control mb-2" placeholder="Enter Password" required />
            <br>
            
            <!-- Login Button -->
            <button type="submit" name="login" class="btn w-100">Login</button>
            
            <!-- Separator Line -->
            <hr class="my-4">
            
            <!-- Forgot Password Link -->
            <a href="index.php" class="text-decoration-none">Back</a>
            
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>