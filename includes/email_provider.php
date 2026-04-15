<?php
/**
 * Email Provider - SendGrid & SMTP Support
 * Handles email sending via different providers
 */

/**
 * Send email via configured provider
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $body Email body (HTML or plain text)
 * @param string|null $from Sender email (uses default if null)
 * @param array $options Additional options (is_html, cc, bcc, attachments)
 * @return array Response with keys: success (bool), message (string)
 */
function sendEmail($to, $subject, $body, $from = null, $options = []) {
    global $conn;
    
    // Validate email
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [
            'success' => false,
            'message' => 'Invalid recipient email address'
        ];
    }
    
    // Get default from address if not provided
    if (!$from) {
        $from = getEmailFromAddress($conn);
    }
    
    // Get configured provider
    $provider = getEmailProvider($conn);
    
    switch ($provider) {
        case 'sendgrid':
            return sendEmailViaSendGrid($to, $subject, $body, $from, $options);
        case 'smtp':
        default:
            return sendEmailViaSMTP($to, $subject, $body, $from, $options);
    }
}

/**
 * Send email via SendGrid
 * @param string $to Recipient email
 * @param string $subject Subject
 * @param string $body Email body
 * @param string $from Sender email
 * @param array $options Additional options
 * @return array Response
 */
function sendEmailViaSendGrid($to, $subject, $body, $from, $options = []) {
    global $conn;
    
    $api_key = getEmailAPIKey($conn);
    if (!$api_key) {
        return [
            'success' => false,
            'message' => 'SendGrid API key not configured'
        ];
    }
    
    $is_html = $options['is_html'] ?? true;
    $from_name = getEmailFromName($conn);
    
    // Prepare email data
    $email_data = [
        'personalizations' => [
            [
                'to' => [
                    ['email' => $to]
                ]
            ]
        ],
        'from' => [
            'email' => $from,
            'name' => $from_name
        ],
        'subject' => $subject,
        'content' => [
            [
                'type' => $is_html ? 'text/html' : 'text/plain',
                'value' => $body
            ]
        ]
    ];
    
    // Add CC/BCC if provided
    if (!empty($options['cc'])) {
        $email_data['personalizations'][0]['cc'] = [['email' => $options['cc']]];
    }
    if (!empty($options['bcc'])) {
        $email_data['personalizations'][0]['bcc'] = [['email' => $options['bcc']]];
    }
    
    // Make request to SendGrid API
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($email_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'message' => 'SendGrid API error: ' . $error
        ];
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'SendGrid API returned code: ' . $http_code
    ];
}

/**
 * Send email via SMTP (PHP built-in mail function)
 * @param string $to Recipient email
 * @param string $subject Subject
 * @param string $body Email body
 * @param string $from Sender email
 * @param array $options Additional options
 * @return array Response
 */
function sendEmailViaSMTP($to, $subject, $body, $from, $options = []) {
    $is_html = $options['is_html'] ?? true;
    
    // Build headers
    $headers = "From: " . $from . "\r\n";
    $headers .= "Reply-To: " . $from . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: " . ($is_html ? "text/html" : "text/plain") . "; charset=UTF-8\r\n";
    
    // Add CC if provided
    if (!empty($options['cc'])) {
        $headers .= "Cc: " . $options['cc'] . "\r\n";
    }
    
    // Add BCC if provided
    if (!empty($options['bcc'])) {
        $headers .= "Bcc: " . $options['bcc'] . "\r\n";
    }
    
    // Send email
    $result = mail($to, $subject, $body, $headers);
    
    if ($result) {
        return [
            'success' => true,
            'message' => 'Email queued for delivery'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'Failed to queue email for delivery'
    ];
}

/**
 * Send email to multiple recipients (batch)
 * @param array $recipients Array of email addresses
 * @param string $subject Subject
 * @param string $body Body
 * @param string|null $from Sender
 * @param array $options Additional options
 * @return array Array of results
 */
function sendEmailBatch($recipients, $subject, $body, $from = null, $options = []) {
    $results = [];
    
    foreach ($recipients as $email) {
        $result = sendEmail($email, $subject, $body, $from, $options);
        $results[] = [
            'email' => $email,
            'result' => $result
        ];
    }
    
    return $results;
}

/**
 * Test email connection
 * @return array Test result
 */
function testEmailConnection() {
    global $conn;
    
    $provider = getEmailProvider($conn);
    
    if ($provider === 'sendgrid') {
        return testSendGridConnection();
    } else {
        return testSMTPConnection();
    }
}

/**
 * Test SendGrid API connection
 * @return array Test result
 */
function testSendGridConnection() {
    global $conn;
    
    $api_key = getEmailAPIKey($conn);
    if (!$api_key) {
        return [
            'success' => false,
            'message' => 'SendGrid API key not configured'
        ];
    }
    
    // Try to get account stats
    $ch = curl_init('https://api.sendgrid.com/v3/mail/send');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'OPTIONS');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'message' => 'Connection error: ' . $error
        ];
    }
    
    if ($http_code >= 200 && $http_code < 300) {
        return [
            'success' => true,
            'message' => 'SendGrid API connection successful'
        ];
    }
    
    if ($http_code === 401) {
        return [
            'success' => false,
            'message' => 'Invalid SendGrid API key'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'SendGrid API returned code: ' . $http_code
    ];
}

/**
 * Test SMTP connection
 * @return array Test result
 */
function testSMTPConnection() {
    // SMTP testing via PHP mail function is limited
    // We'll just verify the function exists
    if (function_exists('mail')) {
        return [
            'success' => true,
            'message' => 'SMTP mail function is available'
        ];
    }
    
    return [
        'success' => false,
        'message' => 'PHP mail function is not available'
    ];
}
?>
