<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $conn->query("ALTER TABLE sms_providers ADD COLUMN balance_endpoint_url VARCHAR(255) NULL");
} catch(Exception $e) {
    // Silently ignore if column already exists
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Delete Provider
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $stmt = $conn->prepare("DELETE FROM sms_providers WHERE id = ?");
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        echo json_encode(['success' => $res]);
        exit;
    }

    // Activate Provider
    if (isset($_POST['activate_id'])) {
        $id = (int)$_POST['activate_id'];
        $conn->query("UPDATE sms_providers SET is_active = 0");
        $stmt = $conn->prepare("UPDATE sms_providers SET is_active = 1 WHERE id = ?");
        $stmt->bind_param("i", $id);
        $res = $stmt->execute();
        echo json_encode(['success' => $res]);
        exit;
    }

    // Save Provider (Create or Edit)
    if (isset($_POST['name']) && isset($_POST['engine_type'])) {
        $id = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
        
        $name = $_POST['name'];
        $engine = $_POST['engine_type'];
        
        $api_key = $_POST['api_key'] ?? null;
        $sender = $_POST['active_sender_id'] ?? null;
        
        $url = $_POST['endpoint_url'] ?? null;
        $b_url = $_POST['balance_endpoint_url'] ?? null;
        $method = $_POST['http_method'] ?? 'POST';
        $payload = $_POST['payload_type'] ?? 'json';
        $auth = $_POST['auth_header'] ?? null;
        $p_rec = $_POST['param_recipient'] ?? 'to';
        $p_msg = $_POST['param_message'] ?? 'msg';
        $p_snd = $_POST['param_sender'] ?? 'sender_id';

        if ($id > 0) {
            $stmt = $conn->prepare("UPDATE sms_providers SET name=?, engine_type=?, endpoint_url=?, balance_endpoint_url=?, http_method=?, payload_type=?, auth_header=?, param_recipient=?, param_message=?, param_sender=?, api_key=?, active_sender_id=? WHERE id=?");
            $stmt->bind_param("ssssssssssssi", $name, $engine, $url, $b_url, $method, $payload, $auth, $p_rec, $p_msg, $p_snd, $api_key, $sender, $id);
        } else {
            $stmt = $conn->prepare("INSERT INTO sms_providers (name, engine_type, endpoint_url, balance_endpoint_url, http_method, payload_type, auth_header, param_recipient, param_message, param_sender, api_key, active_sender_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssssssss", $name, $engine, $url, $b_url, $method, $payload, $auth, $p_rec, $p_msg, $p_snd, $api_key, $sender);
        }
        
        $res = $stmt->execute();
        echo json_encode(['success' => $res]);
        exit;
    }
}

echo json_encode(['success' => false]);
