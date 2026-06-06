<?php
/**
 * Master Daily Cron Script
 * Designed to be executed once per day via Hostinger hPanel Cron Jobs.
 * 
 * Hostinger Command:
 * curl -s "https://yourdomain.com/cron/daily_tasks.php?token=SECRET_SALBA_2026"
 */

// 1. SECURITY CHECK
$cron_token = "SECRET_SALBA_2026"; // Change this in production
if (!isset($_GET['token']) || $_GET['token'] !== $cron_token) {
    http_response_code(403);
    die("Forbidden: Invalid Cron Token.");
}

// Ensure it's not run multiple times quickly by locking or just allowing it
set_time_limit(0); // Allow long execution time

// Include dependencies
// Adjust paths assuming script is in /cron/
include '../includes/db_connect.php';
include '../includes/system_settings.php';
include '../includes/sms_gateway.php';

// Helper function to log cron activity
function log_cron_activity($message) {
    $log_file = __DIR__ . '/cron_log.txt';
    $timestamp = date("Y-m-d H:i:s");
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
}

log_cron_activity("=== CRON JOB STARTED ===");

// ---------------------------------------------------------
// TASK 1: LOW SMS BALANCE ALERT
// ---------------------------------------------------------
log_cron_activity("Executing Task: Low SMS Balance Check...");
$admin_phone = getSystemSetting($conn, 'admin_phone', ''); // You need to set this in settings
$low_balance_threshold = 50;

if (!empty($admin_phone)) {
    // Check active provider
    $res = $conn->query("SELECT * FROM sms_providers WHERE is_active = 1 LIMIT 1");
    if ($res->num_rows > 0) {
        $provider = $res->fetch_assoc();
        $api_key = $provider['api_key'];
        
        if ($provider['engine_type'] === 'mnotify') {
            $url = "https://api.mnotify.com/api/balance/sms?key=" . urlencode($api_key);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            curl_close($ch);
            
            $data = json_decode($response, true);
            if (isset($data['balance']) && is_numeric($data['balance'])) {
                $balance = (int)$data['balance'];
                if ($balance <= $low_balance_threshold) {
                    $alert_msg = "URGENT: Salba Montessori SMS Balance is critically low ($balance units). Please top up immediately to prevent automated messages from failing.";
                    send_sms($admin_phone, $alert_msg);
                    log_cron_activity("Low Balance Alert Sent! (Balance: $balance)");
                } else {
                    log_cron_activity("Balance is healthy ($balance). No alert sent.");
                }
            }
        }
    }
} else {
    log_cron_activity("Skipped: Admin Phone Number not configured in System Settings.");
}

// ---------------------------------------------------------
// TASK 2: OVERDUE FEE REMINDERS
// ---------------------------------------------------------
log_cron_activity("Executing Task: Overdue Fee Reminders...");
$trigger_overdue = getSystemSetting($conn, 'trigger_overdue', '0');
if ($trigger_overdue === '1') {
    $tpl_id = (int)getSystemSetting($conn, 'template_overdue', '0');
    if ($tpl_id > 0) {
        $count = 0;
        // Find overdue fees. We group by student_id to send one summary SMS instead of one per fee.
        $stmt = $conn->query("
            SELECT sf.student_id, SUM(sf.amount - sf.amount_paid) as bal, s.first_name, s.last_name, p.title, p.last_name as p_last_name, p.phone
            FROM student_fees sf
            JOIN students s ON sf.student_id = s.id
            LEFT JOIN student_parents sp ON s.id = sp.student_id 
            LEFT JOIN parents p ON sp.parent_id = p.id AND p.is_primary = 1
            WHERE sf.status = 'overdue' OR (sf.status IN ('pending', 'due') AND sf.due_date < CURDATE() AND (sf.amount - sf.amount_paid) > 0)
            GROUP BY sf.student_id
        ");
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                if (!empty($row['phone'])) {
                    $vars = [
                        '{student_name}' => trim($row['first_name'] . ' ' . $row['last_name']),
                        '{parent_name}' => trim(($row['title'] ? $row['title'] . ' ' : '') . $row['p_last_name']),
                        '{balance}' => number_format($row['bal'], 2),
                        '{amount}' => '0.00', // not applicable here
                        '{term}' => getSystemSetting($conn, 'current_semester', '')
                    ];
                    send_sms_from_template($conn, $tpl_id, $row['phone'], $vars);
                    $count++;
                }
            }
        }
        log_cron_activity("Overdue Reminders sent: $count");
    } else {
        log_cron_activity("Skipped: No template selected for Overdue Fees.");
    }
} else {
    log_cron_activity("Skipped: Overdue Fees trigger is disabled.");
}

// ---------------------------------------------------------
// TASK 3: BIRTHDAY WISHES
// ---------------------------------------------------------
log_cron_activity("Executing Task: Birthday Wishes...");
$trigger_bday = getSystemSetting($conn, 'trigger_birthday', '0');
if ($trigger_bday === '1') {
    $tpl_id = (int)getSystemSetting($conn, 'template_birthday', '0');
    if ($tpl_id > 0) {
        $count = 0;
        $stmt = $conn->query("
            SELECT s.first_name, s.last_name, p.title, p.last_name as p_last_name, p.phone
            FROM students s
            LEFT JOIN student_parents sp ON s.id = sp.student_id 
            LEFT JOIN parents p ON sp.parent_id = p.id AND p.is_primary = 1
            WHERE MONTH(s.date_of_birth) = MONTH(CURDATE()) AND DAY(s.date_of_birth) = DAY(CURDATE()) AND s.status = 'active'
        ");
        if ($stmt) {
            while ($row = $stmt->fetch_assoc()) {
                if (!empty($row['phone'])) {
                    $vars = [
                        '{student_name}' => trim($row['first_name'] . ' ' . $row['last_name']),
                        '{parent_name}' => trim(($row['title'] ? $row['title'] . ' ' : '') . $row['p_last_name']),
                        '{balance}' => '0.00',
                        '{amount}' => '0.00',
                        '{term}' => ''
                    ];
                    send_sms_from_template($conn, $tpl_id, $row['phone'], $vars);
                    $count++;
                }
            }
        }
        log_cron_activity("Birthday Wishes sent: $count");
    } else {
        log_cron_activity("Skipped: No template selected for Birthdays.");
    }
} else {
    log_cron_activity("Skipped: Birthday Wishes trigger is disabled.");
}

log_cron_activity("=== CRON JOB FINISHED ===");
echo "Cron Execution Complete. Check cron_log.txt for details.";
?>
