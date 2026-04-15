<?php
session_start();
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';

// Only admins can perform these actions
if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);

if (!$id) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid staff ID.']);
    exit;
}

$response = ['success' => false, 'message' => 'Invalid action.'];

switch ($action) {
    case 'deactivate':
        // Set employment_status to inactive
        $stmt = $conn->prepare("UPDATE staff_profiles SET employment_status = 'inactive' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Also deactivate the user account if it exists
            $stmt2 = $conn->prepare("UPDATE users SET is_active = 0 WHERE staff_id = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $response = ['success' => true, 'message' => 'Staff deactivated successfully.'];
        } else {
            $response = ['success' => false, 'message' => 'Database error during deactivation.'];
        }
        break;

    case 'activate':
        // Set employment_status to active
        $stmt = $conn->prepare("UPDATE staff_profiles SET employment_status = 'active' WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            // Also activate the user account if it exists
            $stmt2 = $conn->prepare("UPDATE users SET is_active = 1 WHERE staff_id = ?");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $response = ['success' => true, 'message' => 'Staff activated successfully.'];
        } else {
            $response = ['success' => false, 'message' => 'Database error during activation.'];
        }
        break;

    case 'delete':
        // As per decision "1" (Hard Delete), we remove the record.
        // But first, we should probably delete the user account too.
        $stmt_user = $conn->prepare("DELETE FROM users WHERE staff_id = ?");
        $stmt_user->bind_param("i", $id);
        $stmt_user->execute();

        $stmt = $conn->prepare("DELETE FROM staff_profiles WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $response = ['success' => true, 'message' => 'Staff record deleted permanently.'];
        } else {
            $response = ['success' => false, 'message' => 'Database error during deletion. อาจมีข้อมูลอ้างอิงในตารางอื่น.'];
        }
        break;
}

header('Content-Type: application/json');
echo json_encode($response);
exit;
