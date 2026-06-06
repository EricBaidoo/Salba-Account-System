<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['parent_id'])) {
    $parent_id = (int)$_POST['parent_id'];
    
    // Begin Transaction
    $conn->begin_transaction();
    try {
        // Delete links
        $stmt1 = $conn->prepare("DELETE FROM student_parents WHERE parent_id = ?");
        $stmt1->bind_param("i", $parent_id);
        $stmt1->execute();
        $stmt1->close();
        
        // Delete parent
        $stmt2 = $conn->prepare("DELETE FROM parents WHERE id = ?");
        $stmt2->bind_param("i", $parent_id);
        $stmt2->execute();
        $stmt2->close();
        
        $conn->commit();
        $_SESSION['success_msg'] = "Parent successfully deleted.";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error_msg'] = "Error deleting parent: " . $e->getMessage();
    }
}

header('Location: view_parents.php');
exit;
