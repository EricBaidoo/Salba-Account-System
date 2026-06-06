<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    echo "Unauthorized";
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $api_key = trim($_POST['api_key'] ?? '');

    if (empty($api_key)) {
        echo "Error: API Key is missing. Please type it in and try again.";
        exit;
    }

    if ($action === 'check_balance') {
        // BulkSMSGH has two balance endpoints, we'll try the first one given
        $url = "https://clientlogin.bulksmsgh.com/api/smsapibalance?key=" . urlencode($api_key);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // BulkSMSGH uses GET, so no special headers typically required, but we can add JSON just in case
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        $result = curl_exec($ch);
        curl_close($ch);
        echo $result ? $result : "No response from BulkSMSGH.";
        exit;
    }
}
