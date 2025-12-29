<?php
// faculty_logout.php
session_start();

// Only unset FACULTY-specific session variables
// This preserves admin sessions if user is logged into both
unset($_SESSION['faculty_logged_in']);
unset($_SESSION['faculty_id']);
unset($_SESSION['faculty_employee_id']);
unset($_SESSION['faculty_name']);

// Optional: Add a logout success message
$_SESSION['logout_message'] = 'You have been successfully logged out.';

// Redirect to the faculty login page
header("Location: faculty_login.php");
exit();
?>