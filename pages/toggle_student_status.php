<?php
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $new_status = $_POST['status'];
    
    // Validate status
    if (!in_array($new_status, ['active', 'inactive'])) {
        $error = "Invalid status provided.";
    } else {
        // Update student status
        $stmt = $conn->prepare("UPDATE students SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $student_id);
        
        if ($stmt->execute()) {
            $success = true;
            $action = $new_status === 'active' ? 'enabled' : 'disabled';
            $_SESSION['success_message'] = "Student has been successfully $action.";
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Redirect back to view students
if ($success) {
    header("Location: view_students.php?status_updated=1");
} else {
    $_SESSION['error_message'] = $error;
    header("Location: view_students.php?error=1");
}
exit();
?>