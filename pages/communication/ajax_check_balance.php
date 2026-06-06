<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    echo "Unauthorized";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['provider_id'])) {
    $id = (int)$_POST['provider_id'];
    
    $stmt = $conn->prepare("SELECT * FROM sms_providers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $provider = $stmt->get_result()->fetch_assoc();

    if (!$provider || empty($provider['balance_endpoint_url'])) {
        echo "No balance URL configured for this provider.";
        exit;
    }

    $url = $provider['balance_endpoint_url'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    
    // Some providers might require the Auth header for balance checks too
    if (!empty($provider['auth_header'])) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [$provider['auth_header'], "Content-Type: application/json"]);
    } else {
        // BulkSMSGH uses GET, but maybe it needs JSON headers? 
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    }

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        echo "CURL Error: " . $err;
    } else {
        // Just return the raw response from the gateway so the admin can read it
        echo $result;
    }
}
