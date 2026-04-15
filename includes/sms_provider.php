<?php
/**
 * SMS Provider - Bulk SMS Ghana Integration
 * Handles all SMS sending via Bulk SMS Ghana API
 */

/**
 * Send SMS via Bulk SMS Ghana
 * @param string $phone Phone number (any format, will be normalized)
 * @param string $message SMS message (max 160 chars)
 * @param string $sender_id Sender ID (max 11 chars, default from settings)
 * @return array Response array with keys: success (bool), code (int), message (string), balance (string)
 */
function sendSMS($phone, $message, $sender_id = null) {
    global $conn;
    
    // Validate inputs
    if (!$phone || !$message) {
        return [
            'success' => false,
            'code' => 1008,
            'message' => 'Phone or message is empty'
        ];
    }
    
    // Format phone to E.164
    $formatted_phone = formatPhoneE164($phone);
    if (!$formatted_phone) {
        return [
            'success' => false,
            'code' => 1005,
            'message' => 'Invalid phone number format'
        ];
    }
    
    // Get SMS settings from database
    $api_key = getSMSAPIKey($conn);
    if (!$api_key) {
        return [
            'success' => false,
            'code' => 1004,
            'message' => 'SMS API key not configured'
        ];
    }
    
    // Get sender ID (max 11 chars)
    if (!$sender_id) {
        $sender_id = getSMSSenderID($conn);
    }
    $sender_id = substr($sender_id, 0, 11);
    
    // Truncate message to 160 chars (SMS standard)
    $message = substr($message, 0, 160);
    
    // Build API endpoint
    $endpoint = 'https://clientlogin.bulksmsgh.com/smsapi';
    
    // Prepare payload
    $params = [
        'key' => $api_key,
        'to' => $formatted_phone,
        'msg' => $message,
        'sender_id' => $sender_id
    ];
    
    // Make HTTP request
    $response = bulkSMSGhanaRequest($endpoint, $params);
    
    if ($response === false) {
        return [
            'success' => false,
            'code' => 0,
            'message' => 'Failed to connect to SMS gateway'
        ];
    }
    
    // Parse response code
    $code = (int)trim($response);
    
    // Handle response codes
    switch ($code) {
        case 1000:
            return [
                'success' => true,
                'code' => 1000,
                'message' => 'Message submitted successfully'
            ];
        case 1002:
            return [
                'success' => false,
                'code' => 1002,
                'message' => 'SMS sending failed'
            ];
        case 1003:
            return [
                'success' => false,
                'code' => 1003,
                'message' => 'Insufficient balance in SMS account'
            ];
        case 1004:
            return [
                'success' => false,
                'code' => 1004,
                'message' => 'Invalid API key'
            ];
        case 1005:
            return [
                'success' => false,
                'code' => 1005,
                'message' => 'Invalid phone number'
            ];
        case 1006:
            return [
                'success' => false,
                'code' => 1006,
                'message' => 'Invalid sender ID (max 11 characters)'
            ];
        case 1007:
            return [
                'success' => true,
                'code' => 1007,
                'message' => 'Message scheduled for later delivery'
            ];
        case 1008:
            return [
                'success' => false,
                'code' => 1008,
                'message' => 'Empty message'
            ];
        default:
            return [
                'success' => false,
                'code' => $code,
                'message' => 'Unknown response code: ' . $code
            ];
    }
}

/**
 * Check SMS account balance
 * @return array Response with keys: success (bool), balance (string), message (string)
 */
function checkSMSBalance() {
    global $conn;
    
    $api_key = getSMSAPIKey($conn);
    if (!$api_key) {
        return [
            'success' => false,
            'balance' => 'N/A',
            'message' => 'SMS API key not configured'
        ];
    }
    
    $endpoint = 'https://clientlogin.bulksmsgh.com/api/balance/sms?key=' . urlencode($api_key);
    
    $response = bulkSMSGhanaRequest($endpoint, [], 'GET');
    
    if ($response === false) {
        return [
            'success' => false,
            'balance' => 'N/A',
            'message' => 'Failed to connect to SMS gateway'
        ];
    }
    
    // Parse JSON response
    $data = json_decode($response, true);
    
    if ($data && isset($data['balance'])) {
        // Update balance in settings
        if (isset($conn)) {
            setSystemSetting($conn, 'sms_balance', $data['balance'], 'System');
        }
        
        return [
            'success' => true,
            'balance' => $data['balance'],
            'message' => 'Balance retrieved successfully'
        ];
    }
    
    return [
        'success' => false,
        'balance' => 'N/A',
        'message' => 'Unable to parse balance response'
    ];
}

/**
 * Make HTTP request to Bulk SMS Ghana API
 * @param string $url Full URL or endpoint
 * @param array $params Query/post parameters
 * @param string $method HTTP method (GET or POST)
 * @return string|false Response body or false on failure
 */
function bulkSMSGhanaRequest($url, $params = [], $method = 'GET') {
    // Initialize cURL
    $ch = curl_init();
    
    if ($method === 'GET' && !empty($params)) {
        $url .= '?' . http_build_query($params);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Salba-Montessori-SMS-Client/1.0');
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        error_log("Bulk SMS Ghana API Error: " . $error);
        return false;
    }
    
    return $response;
}

/**
 * Send SMS to multiple recipients (batch)
 * @param array $recipients Array of phone numbers
 * @param string $message SMS message
 * @param string $sender_id Sender ID
 * @return array Array of results for each recipient
 */
function sendSMSBatch($recipients, $message, $sender_id = null) {
    $results = [];
    
    foreach ($recipients as $phone) {
        $result = sendSMS($phone, $message, $sender_id);
        $results[] = [
            'phone' => $phone,
            'result' => $result
        ];
        
        // Add small delay to respect rate limits
        usleep(100000); // 0.1 seconds
    }
    
    return $results;
}
?>
