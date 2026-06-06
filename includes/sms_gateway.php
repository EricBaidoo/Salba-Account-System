<?php
/**
 * Core SMS Gateway Engine for Salba Montessori
 * Handles intelligent dispatching of SMS via Native or Custom providers
 */

/**
 * Sends an SMS message using the active provider
 * 
 * @param string $phone The recipient's phone number
 * @param string $message The body of the text message
 * @param string $custom_sender_id (Optional) Override the gateway's default sender ID
 * @return array ['success' => true/false, 'error' => string, 'response' => raw_api_response]
 */
function send_sms($phone, $message, $custom_sender_id = null) {
    global $conn;
    
    // Create logs table if it doesn't exist
    $conn->query("CREATE TABLE IF NOT EXISTS sms_logs (
        id INT AUTO_INCREMENT PRIMARY KEY, 
        recipient_phone VARCHAR(50), 
        message_body TEXT, 
        sender_id VARCHAR(50), 
        provider VARCHAR(50),
        status VARCHAR(50), 
        api_response TEXT, 
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    if (empty($phone) || empty($message)) {
        return ['success' => false, 'error' => 'Phone and message are required.'];
    }

    // 1. Get active provider
    $res = $conn->query("SELECT * FROM sms_providers WHERE is_active = 1 LIMIT 1");
    if($res->num_rows === 0) return ['success' => false, 'error' => 'No active SMS Gateway found. Please activate a gateway in Settings.'];
    $provider = $res->fetch_assoc();
    
    $api_key = $provider['api_key'];
    $sender = !empty($custom_sender_id) ? $custom_sender_id : $provider['active_sender_id'];
    if(empty($sender)) $sender = "SALBA"; // Hard fallback
    
    // 2. Clean phone number (e.g. 024 becomes 23324, strip spaces)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if(strpos($phone, '0') === 0) {
        $phone = '233' . substr($phone, 1);
    }
    
    $engine = $provider['engine_type'];
    
    // ----------------------------------------------------
    // NATIVE MNOTIFY ENGINE
    // ----------------------------------------------------
    if ($engine === 'mnotify') {
        if(empty($api_key)) return ['success' => false, 'error' => 'mNotify API Key is missing.'];
        
        $url = "https://api.mnotify.com/api/sms/quick?key=" . urlencode($api_key);
        $data = [
            'recipient' => [$phone],
            'sender' => $sender,
            'message' => $message,
            'is_schedule' => false,
            'schedule_date' => ''
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) { $is_success = false; $err_msg = 'cURL Error: ' . $err; }
        else {
            $res_data = json_decode($response, true);
            if(isset($res_data['status']) && $res_data['status'] == 'success') { $is_success = true; $err_msg = ''; } 
            else { $is_success = false; $err_msg = 'mNotify API Error'; }
        }
        $stmt = $conn->prepare("INSERT INTO sms_logs (recipient_phone, message_body, sender_id, provider, status, api_response) VALUES (?, ?, ?, 'mnotify', ?, ?)");
        $st_str = $is_success ? 'success' : 'failed';
        $stmt->bind_param("sssss", $phone, $message, $sender, $st_str, $response); $stmt->execute();
        
        return ['success' => $is_success, 'error' => $err_msg, 'response' => $response];
    }
    
    // ----------------------------------------------------
    // NATIVE BULKSMSGH ENGINE
    // ----------------------------------------------------
    elseif ($engine === 'bulksmsgh') {
        if(empty($api_key)) return ['success' => false, 'error' => 'BulkSMSGH API Key is missing.'];
        
        $url = "https://clientlogin.bulksmsgh.com/smsapi";
        $url .= "?key=" . urlencode($api_key);
        $url .= "&to=" . urlencode($phone);
        $url .= "&msg=" . urlencode($message);
        $url .= "&sender_id=" . urlencode($sender);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) { $is_success = false; $err_msg = 'cURL Error: ' . $err; }
        else {
            if (strpos(strtolower($response), 'error') !== false || strpos(strtolower($response), 'failed') !== false) { $is_success = false; $err_msg = 'BulkSMSGH API Error'; }
            else { $is_success = true; $err_msg = ''; }
        }
        $stmt = $conn->prepare("INSERT INTO sms_logs (recipient_phone, message_body, sender_id, provider, status, api_response) VALUES (?, ?, ?, 'bulksmsgh', ?, ?)");
        $st_str = $is_success ? 'success' : 'failed';
        $stmt->bind_param("sssss", $phone, $message, $sender, $st_str, $response); $stmt->execute();
        
        return ['success' => $is_success, 'error' => $err_msg, 'response' => $response];
    }
    
    // ----------------------------------------------------
    // CUSTOM BLUEPRINT ENGINE
    // ----------------------------------------------------
    else {
        $url = $provider['endpoint_url'];
        if(empty($url)) return ['success' => false, 'error' => 'Custom Endpoint URL is missing.'];
        
        $method = strtoupper($provider['http_method'] ?? 'POST');
        $payload_type = $provider['payload_type'] ?? 'json';
        $auth_header = trim($provider['auth_header'] ?? '');
        
        $p_rec = !empty($provider['param_recipient']) ? $provider['param_recipient'] : 'to';
        $p_msg = !empty($provider['param_message']) ? $provider['param_message'] : 'msg';
        $p_snd = !empty($provider['param_sender']) ? $provider['param_sender'] : 'sender_id';
        $success_keyword = !empty($provider['success_keyword']) ? $provider['success_keyword'] : 'success';
        
        $data = [
            $p_rec => $phone,
            $p_msg => $message,
            $p_snd => $sender
        ];
        
        $headers = [];
        if(!empty($auth_header)) {
            // Allow injecting {api_key} into Auth header
            $auth_header = str_replace('{api_key}', $api_key, $auth_header);
            $headers[] = $auth_header;
        }
        
        // Inject API key into URL if {api_key} is present
        $url = str_replace('{api_key}', urlencode($api_key), $url);
        
        $ch = curl_init();
        
        if ($method === 'GET') {
            $qs = http_build_query($data);
            if(strpos($url, '?') !== false) $url .= '&' . $qs; else $url .= '?' . $qs;
            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            
            if ($payload_type === 'json') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                $headers[] = 'Content-Type: application/json';
                $headers[] = 'Accept: application/json';
            } else { // form-urlencoded
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if(!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $response = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        
        if ($err) { $is_success = false; $err_msg = 'cURL Error: ' . $err; }
        else {
            if (strpos(strtolower($response), strtolower($success_keyword)) !== false) { $is_success = true; $err_msg = ''; } 
            else { $is_success = false; $err_msg = "Missing success keyword '{$success_keyword}' in response."; }
        }
        $stmt = $conn->prepare("INSERT INTO sms_logs (recipient_phone, message_body, sender_id, provider, status, api_response) VALUES (?, ?, ?, 'custom', ?, ?)");
        $st_str = $is_success ? 'success' : 'failed';
        $stmt->bind_param("sssss", $phone, $message, $sender, $st_str, $response); $stmt->execute();
        
        return ['success' => $is_success, 'error' => $err_msg, 'response' => $response];
    }
}

/**
 * Compiles a template and sends it automatically
 */
function send_sms_from_template($template_id, $phone, $variables = []) {
    global $conn;
    $id = (int)$template_id;
    if ($id <= 0) return ['success' => false, 'error' => 'Invalid Template ID provided to engine.'];
    
    $res = $conn->query("SELECT * FROM sms_templates WHERE id = $id");
    if($res->num_rows === 0) return ['success' => false, 'error' => "Template ID {$id} not found."];
    $tpl = $res->fetch_assoc();
    
    $message = $tpl['message_body'];
    // Inject dynamic variables
    foreach($variables as $key => $val) {
        $message = str_replace("{" . $key . "}", $val, $message);
    }
    
    return send_sms($phone, $message, $tpl['sender_id']);
}

?>
