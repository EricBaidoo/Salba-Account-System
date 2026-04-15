<?php
/**
 * Communication Module Functions
 * Handles recipient fetching, message queuing, and delivery logging
 */

// ============================================================================
// RECIPIENT FETCHING FUNCTIONS
// ============================================================================

/**
 * Get recipients based on audience type
 * @param mysqli $conn Database connection
 * @param string $audience_type (all, staff, students, parents, class, custom)
 * @param array $params Additional params for custom targeting (e.g., ['class_name' => 'Basic 1'])
 * @return array Array of recipients with phone, email, name, user_id
 */
function getRecipientsByAudience($conn, $audience_type, $params = []) {
    switch ($audience_type) {
        case 'staff':
            return getStaffRecipients($conn);
        case 'parents':
            return getParentRecipients($conn);
        case 'class':
            return getClassRecipients($conn, $params['class_name'] ?? null);
        case 'students':
            return getStudentRecipients($conn);
        case 'all':
            return array_merge(
                getStaffRecipients($conn),
                getParentRecipients($conn)
            );
        default:
            return [];
    }
}

/**
 * Get active staff with phone or email
 * @param mysqli $conn Database connection
 * @return array Array of staff recipients
 */
function getStaffRecipients($conn) {
    $recipients = [];
    $stmt = $conn->prepare("
        SELECT 
            sp.id,
            sp.telephone_number as phone, 
            sp.email, 
            sp.full_name as name, 
            sp.user_id,
            'staff' as recipient_type
        FROM staff_profiles sp
        WHERE sp.employment_status = 'active' 
        AND (sp.telephone_number IS NOT NULL OR sp.email IS NOT NULL)
        ORDER BY sp.full_name ASC
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recipients[] = $row;
        }
        $stmt->close();
    }
    return $recipients;
}

/**
 * Get parent recipients
 * @param mysqli $conn Database connection
 * @return array Array of parent recipients
 */
function getParentRecipients($conn) {
    $recipients = [];
    $stmt = $conn->prepare("
        SELECT DISTINCT
            p.id,
            p.phone, 
            p.email, 
            CONCAT(p.first_name, ' ', p.last_name) as name, 
            NULL as user_id,
            p.relationship,
            'parent' as recipient_type
        FROM parents p
        WHERE (p.phone IS NOT NULL OR p.email IS NOT NULL)
        ORDER BY p.first_name ASC
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recipients[] = $row;
        }
        $stmt->close();
    }
    return $recipients;
}

/**
 * Get students (active only)
 * @param mysqli $conn Database connection
 * @return array Array of student records
 */
function getStudentRecipients($conn) {
    $recipients = [];
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.parent_contact as phone,
            NULL as email,
            CONCAT(s.first_name, ' ', s.last_name) as name,
            NULL as user_id,
            s.class,
            'student' as recipient_type
        FROM students s
        WHERE s.status = 'active'
        AND s.parent_contact IS NOT NULL
        ORDER BY s.first_name ASC
    ");
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recipients[] = $row;
        }
        $stmt->close();
    }
    return $recipients;
}

/**
 * Get recipients by class
 * @param mysqli $conn Database connection
 * @param string $class_name Class name
 * @return array Array of recipients in that class
 */
function getClassRecipients($conn, $class_name) {
    $recipients = [];
    
    if (!$class_name) {
        return $recipients;
    }
    
    // Get students in this class
    $stmt = $conn->prepare("
        SELECT 
            s.id,
            s.parent_contact as phone,
            NULL as email,
            CONCAT(s.first_name, ' ', s.last_name) as name,
            NULL as user_id,
            s.class,
            'student' as recipient_type
        FROM students s
        WHERE s.class = ? AND s.status = 'active'
        ORDER BY s.first_name ASC
    ");
    
    if ($stmt) {
        $stmt->bind_param('s', $class_name);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recipients[] = $row;
        }
        $stmt->close();
    }
    
    // Get parents of students in this class
    $stmt = $conn->prepare("
        SELECT DISTINCT
            p.id,
            p.phone, 
            p.email, 
            CONCAT(p.first_name, ' ', p.last_name) as name, 
            NULL as user_id,
            p.relationship,
            'parent' as recipient_type
        FROM parents p
        JOIN student_parents sp ON sp.parent_id = p.id
        JOIN students s ON s.id = sp.student_id
        WHERE s.class = ? AND (p.phone IS NOT NULL OR p.email IS NOT NULL)
        ORDER BY p.first_name ASC
    ");
    
    if ($stmt) {
        $stmt->bind_param('s', $class_name);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $recipients[] = $row;
        }
        $stmt->close();
    }
    
    return $recipients;
}

// ============================================================================
// MESSAGE & DELIVERY LOGGING FUNCTIONS
// ============================================================================

/**
 * Queue a message for sending
 * @param mysqli $conn Database connection
 * @param array $data Message data (sender_id, recipient_id, subject, body, channel, audience_type, scheduled_at)
 * @return int Message ID or false
 */
function queueMessage($conn, $data) {
    $stmt = $conn->prepare("
        INSERT INTO messages 
        (sender, recipient, subject, body, channel, status, scheduled_at) 
        VALUES (?, ?, ?, ?, ?, 'queued', ?)
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $sender = $data['sender'] ?? $_SESSION['username'] ?? 'System';
    $recipient = $data['recipient'] ?? '';
    $subject = $data['subject'] ?? '';
    $body = $data['body'] ?? '';
    $channel = $data['channel'] ?? 'in_app';
    $scheduled_at = $data['scheduled_at'] ?? null;
    
    $stmt->bind_param('ssssss', $sender, $recipient, $subject, $body, $channel, $scheduled_at);
    
    if ($stmt->execute()) {
        $message_id = $conn->insert_id;
        $stmt->close();
        return $message_id;
    }
    
    $stmt->close();
    return false;
}

/**
 * Log message delivery attempt
 * @param mysqli $conn Database connection
 * @param int $message_id Message ID
 * @param string $recipient_phone Recipient phone
 * @param string $recipient_email Recipient email
 * @param string $channel Channel used (sms, email, etc)
 * @param string $status Delivery status (sent, failed, queued)
 * @param string $error_message Error message if failed
 * @return bool Success/failure
 */
function logDelivery($conn, $message_id, $recipient_phone, $recipient_email, $channel, $status, $error_message = null) {
    $stmt = $conn->prepare("
        INSERT INTO message_delivery_log 
        (message_id, recipient_phone, recipient_email, channel, status, error_message, attempt_count) 
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('isssss', $message_id, $recipient_phone, $recipient_email, $channel, $status, $error_message);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Update message delivery status
 * @param mysqli $conn Database connection
 * @param int $delivery_log_id Delivery log ID
 * @param string $status New status
 * @param string $error_message Optional error message
 * @return bool Success/failure
 */
function updateDeliveryStatus($conn, $delivery_log_id, $status, $error_message = null) {
    $stmt = $conn->prepare("
        UPDATE message_delivery_log 
        SET status = ?, error_message = ?, sent_at = NOW() 
        WHERE id = ?
    ");
    
    if (!$stmt) {
        return false;
    }
    
    $stmt->bind_param('ssi', $status, $error_message, $delivery_log_id);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Get all failed delivery attempts for retry
 * @param mysqli $conn Database connection
 * @param int $max_attempts Maximum retry attempts (default 3)
 * @return array Array of failed deliveries
 */
function getFailedDeliveries($conn, $max_attempts = 3) {
    $failed = [];
    $stmt = $conn->prepare("
        SELECT * FROM message_delivery_log
        WHERE status = 'failed' AND attempt_count < ?
        ORDER BY created_at ASC
        LIMIT 100
    ");
    
    if ($stmt) {
        $stmt->bind_param('i', $max_attempts);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $failed[] = $row;
        }
        $stmt->close();
    }
    
    return $failed;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Validate and format phone number to E.164 format
 * Assumes Ghana phone numbers: format as +233XXXXXXXXX
 * @param string $phone Phone number (various formats accepted)
 * @return string|bool Formatted phone or false if invalid
 */
function formatPhoneE164($phone) {
    if (!$phone) return false;
    
    // Remove all non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // If starts with 0, replace with 233
    if (substr($phone, 0, 1) === '0') {
        $phone = '233' . substr($phone, 1);
    }
    
    // If doesn't start with 233, assume it does
    if (substr($phone, 0, 3) !== '233') {
        $phone = '233' . $phone;
    }
    
    // Add + prefix
    $phone = '+' . $phone;
    
    // Validate length (Ghana: +233XXXXXXXXX = 13 chars)
    if (strlen($phone) !== 13) {
        return false;
    }
    
    return $phone;
}

/**
 * Get SMS balance from system settings
 * @param mysqli $conn Database connection
 * @return string SMS balance or 'Unknown'
 */
function getSMSBalance($conn) {
    return getSystemSetting($conn, 'sms_balance', 'N/A');
}

/**
 * Check if SMS is enabled
 * @param mysqli $conn Database connection
 * @return bool Whether SMS is enabled
 */
function isSMSEnabled($conn) {
    $enabled = getSystemSetting($conn, 'sms_enable', '0');
    return $enabled === '1' || $enabled === true;
}

/**
 * Check if Email is enabled
 * @param mysqli $conn Database connection
 * @return bool Whether Email is enabled
 */
function isEmailEnabled($conn) {
    $enabled = getSystemSetting($conn, 'email_enable', '0');
    return $enabled === '1' || $enabled === true;
}

/**
 * Get SMS API Key
 * @param mysqli $conn Database connection
 * @return string SMS API Key or empty string
 */
function getSMSAPIKey($conn) {
    return getSystemSetting($conn, 'sms_api_key', '');
}

/**
 * Get SMS Sender ID
 * @param mysqli $conn Database connection
 * @return string SMS Sender ID (max 11 chars)
 */
function getSMSSenderID($conn) {
    $sender_id = getSystemSetting($conn, 'sms_sender_id', 'SALBA');
    return substr($sender_id, 0, 11); // Bulk SMS Ghana limit
}

/**
 * Get Email Provider
 * @param mysqli $conn Database connection
 * @return string Email provider (sendgrid, smtp, etc)
 */
function getEmailProvider($conn) {
    return getSystemSetting($conn, 'email_provider', 'smtp');
}

/**
 * Get Email API Key
 * @param mysqli $conn Database connection
 * @return string Email API Key or empty string
 */
function getEmailAPIKey($conn) {
    return getSystemSetting($conn, 'email_api_key', '');
}

/**
 * Get Email From Address
 * @param mysqli $conn Database connection
 * @return string Email from address
 */
function getEmailFromAddress($conn) {
    return getSystemSetting($conn, 'email_from_address', 'noreply@salbamontessori.edu.gh');
}

/**
 * Get Email From Name
 * @param mysqli $conn Database connection
 * @return string Email from name
 */
function getEmailFromName($conn) {
    return getSystemSetting($conn, 'email_from_name', 'Salba Montessori');
}
?>
