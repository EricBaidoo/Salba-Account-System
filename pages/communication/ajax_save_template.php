<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Delete Template
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM sms_templates WHERE id = ?");
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        echo json_encode(['success' => $res]);
        exit;
    }

    // Add New Template
    if (isset($_POST['template_name']) && isset($_POST['message_body'])) {
        $name = $_POST['template_name'];
        $body = $_POST['message_body'];

        $stmt = $conn->prepare("INSERT INTO sms_templates (template_name, message_body) VALUES (?, ?)");
        $stmt->bind_param("ss", $name, $body);
        $res = $stmt->execute();
        echo json_encode(['success' => $res]);
        exit;
    }
}

echo json_encode(['success' => false]);
