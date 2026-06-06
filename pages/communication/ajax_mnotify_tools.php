<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'title' => 'Unauthorized', 'message' => 'Admin access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['provider_id'])) {
    
    $id = (int)$_POST['provider_id'];
    $stmt = $conn->prepare("SELECT * FROM sms_providers WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    
    if(!$p) {
        echo json_encode(['success' => false, 'title' => 'Error', 'message' => 'Provider not found.']);
        exit;
    }

    $action = $_POST['action'];
    $api_key = trim($p['api_key']);
    $sender = trim($p['active_sender_id']);

    if (empty($api_key)) {
        echo json_encode(['success' => false, 'title' => 'Missing API Key', 'message' => 'Please edit this provider and add the API Key first.']);
        exit;
    }

    $url = ""; $post = false; $data = []; $title = "API Response";

    if ($p['engine_type'] === 'bulksmsgh' && $action === 'check_balance') {
        $url = "https://clientlogin.bulksmsgh.com/api/smsapibalance?key=" . urlencode($api_key);
        $title = "BulkSMSGH Balance";
    } elseif ($action === 'check_balance') {
        $url = "https://api.mnotify.com/api/balance/sms?key=" . urlencode($api_key);
        $title = "mNotify Balance";
    } elseif ($action === 'register_sender') {
        if (empty($sender)) {
            echo json_encode(['success' => false, 'title' => 'Sender ID Missing', 'message' => 'Please edit this provider and add an Active Sender ID.']);
            exit;
        }
        $purpose = trim($_POST['purpose'] ?? 'For School Alerts');
        $url = "https://api.mnotify.com/api/senderid/register?key=" . urlencode($api_key);
        $post = true; $data = ['sender_name' => $sender, 'purpose' => $purpose];
        $title = "Sender ID Registration";
    } elseif ($action === 'check_sender_status') {
        if (empty($sender)) {
            echo json_encode(['success' => false, 'title' => 'Sender ID Missing', 'message' => 'Active Sender ID is required.']);
            exit;
        }
        $url = "https://api.mnotify.com/api/senderid/status/?key=" . urlencode($api_key);
        $post = true; $data = ['sender_name' => $sender];
        $title = "Sender ID Status";
    }

    if (!empty($url)) {
        $ch = curl_init(); curl_setopt($ch, CURLOPT_URL, $url); curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($post) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $res = curl_exec($ch); $err = curl_error($ch); curl_close($ch);
        
        if ($err) {
            echo json_encode(['success' => false, 'title' => 'Connection Error', 'message' => "Could not connect to provider. Error: " . $err]);
        } else {
            $decoded = json_decode($res, true);
            $msg = "";
            
            if ($action === 'check_balance') {
                if ($p['engine_type'] === 'mnotify' && $decoded && isset($decoded['balance'])) {
                    $msg = "You currently have " . $decoded['balance'] . " SMS units remaining in your mNotify account.";
                    if(isset($decoded['bonus']) && $decoded['bonus'] > 0) $msg .= " (Plus " . $decoded['bonus'] . " bonus units!)";
                } elseif ($p['engine_type'] === 'bulksmsgh') {
                    $msg = "You currently have " . trim(strip_tags($res)) . " SMS units remaining in your BulkSMSGH account.";
                } else {
                    $msg = "Balance: " . trim(strip_tags($res));
                }
            } elseif ($action === 'register_sender') {
                if ($decoded && isset($decoded['summary']['status'])) {
                    $status = strtoupper($decoded['summary']['status']);
                    $name = $decoded['summary']['sender_name'] ?? $sender;
                    $msg = "Sender ID '{$name}' was submitted successfully!\n\nCurrent Status: {$status}";
                    if($status === 'PENDING') $msg .= "\n\nPlease note: It may take up to 24 hours for the telecommunication networks to approve your new Sender ID.";
                } elseif ($decoded && isset($decoded['message'])) {
                    $msg = $decoded['message'];
                    if (isset($decoded['status']) && strtolower($decoded['status']) === 'success') {
                         $msg .= "\n\nPlease note: It may take up to 24 hours for approval.";
                    }
                } elseif ($decoded && isset($decoded['status']) && strtolower($decoded['status']) === 'success') {
                    $msg = "Sender ID was submitted successfully!\n\nPlease note: It may take up to 24 hours for approval.";
                } else {
                    if (is_array($decoded)) {
                        $msg = "";
                        array_walk_recursive($decoded, function($v, $k) use (&$msg) {
                            $msg .= ucfirst(str_replace('_', ' ', $k)) . ": " . $v . "\n";
                        });
                        $msg = trim($msg);
                    } else {
                        $msg = "Response: " . trim(strip_tags($res));
                    }
                }
            } elseif ($action === 'check_sender_status') {
                if ($decoded && isset($decoded['summary']['status'])) {
                    $status = strtoupper($decoded['summary']['status']);
                    $name = $decoded['summary']['sender_name'] ?? $sender;
                    $msg = "The network status for your Sender ID '{$name}' is: {$status}.";
                    if($status === 'PENDING') $msg .= "\n\nIt is still awaiting approval from the networks.";
                    if($status === 'REJECTED') $msg .= "\n\nUnfortunately, it was rejected. Please contact mNotify support for assistance.";
                } elseif ($decoded && isset($decoded['message'])) {
                    $msg = $decoded['message'];
                } else {
                    if (is_array($decoded)) {
                        $msg = "";
                        array_walk_recursive($decoded, function($v, $k) use (&$msg) {
                            $msg .= ucfirst(str_replace('_', ' ', $k)) . ": " . $v . "\n";
                        });
                        $msg = trim($msg);
                    } else {
                        $msg = "Status Response: " . trim(strip_tags($res));
                    }
                }
            } else {
                $msg = $res;
            }

            echo json_encode(['success' => true, 'title' => $title, 'message' => $msg]);
        }
        exit;
    }
}
?>
