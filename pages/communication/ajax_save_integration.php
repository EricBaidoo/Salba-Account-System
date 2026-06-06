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
    
    // Activate Provider
    if (isset($_POST['activate_provider'])) {
        $provider = $_POST['activate_provider'];
        // Deactivate all first
        $conn->query("UPDATE sms_integrations SET is_active = 0");
        // Activate the selected one
        $stmt = $conn->prepare("UPDATE sms_integrations SET is_active = 1 WHERE provider_name = ?");
        $stmt->bind_param("s", $provider);
        $res = $stmt->execute();
        echo json_encode(['success' => $res]);
        exit;
    }

    // Save Provider Settings
    if (isset($_POST['provider_name'])) {
        $provider = $_POST['provider_name'];
        $api_key = $_POST['api_key'] ?? '';
        $sender_id = $_POST['active_sender_id'] ?? '';
        
        $stmt = $conn->prepare("UPDATE sms_integrations SET api_key = ?, active_sender_id = ? WHERE provider_name = ?");
        $stmt->bind_param("sss", $api_key, $sender_id, $provider);
        $res = $stmt->execute();
        echo json_encode(['success' => $res]);
        exit;
    }
}

echo json_encode(['success' => false]);
