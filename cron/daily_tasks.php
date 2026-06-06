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
log_cron_activity("Executing Task: Overdue Fee Reminders");
$trigger_overdue = getSystemSetting($conn, 'trigger_overdue_fee', '0');
if ($trigger_overdue === '1') {
    $tpl_id = (int)getSystemSetting($conn, 'template_overdue_fee', '0');
    if ($tpl_id > 0) {
        $overdue_sql = "
            SELECT 
                s.id as student_id,
                s.first_name, s.last_name,
                p.title, p.last_name as p_last_name, p.phone as parent_contact,
                SUM(f.amount - f.amount_paid) as total_overdue
            FROM student_fees f
            JOIN students s ON f.student_id = s.id
            JOIN student_parents sp ON s.id = sp.student_id
            JOIN parents p ON sp.parent_id = p.id
            WHERE f.status = 'overdue'
              AND p.phone IS NOT NULL AND p.phone != ''
            GROUP BY s.id, p.id
            HAVING total_overdue > 0
        ";
        $overdue_res = $conn->query($overdue_sql);
        
        $count_overdue = 0;
        if ($overdue_res && $overdue_res->num_rows > 0) {
            while ($row = $overdue_res->fetch_assoc()) {
                $student_name = trim($row['first_name'] . ' ' . $row['last_name']);
                $parent_title = !empty($row['title']) ? $row['title'] : '';
                $parent_name = trim($parent_title . ' ' . $row['p_last_name']);
                
                $vars = [
                    '{student_name}' => $student_name,
                    '{parent_name}' => $parent_name,
                    '{balance}' => number_format($row['total_overdue'], 2)
                ];
                send_sms_from_template($conn, $tpl_id, $row['parent_contact'], $vars);
                $count_overdue++;
            }
        }
        log_cron_activity("Overdue Fees Task Completed: Sent $count_overdue SMS reminders.");
    } else {
        log_cron_activity("Overdue Fees Task Skipped: Template not configured.");
    }
} else {
    log_cron_activity("Overdue Fees Task Skipped: Trigger is disabled.");
}

// ---------------------------------------------------------
// TASK 3: HAPPY BIRTHDAY WISHES
// ---------------------------------------------------------
log_cron_activity("Executing Task: Happy Birthday Wishes");
$trigger_bday = getSystemSetting($conn, 'trigger_birthday', '0');
if ($trigger_bday === '1') {
    $tpl_id = (int)getSystemSetting($conn, 'template_birthday', '0');
    if ($tpl_id > 0) {
        $today_md = date('m-d');
        $bday_sql = "
            SELECT 
                s.first_name, s.last_name,
                p.title, p.last_name as p_last_name, p.phone as parent_contact
            FROM students s
            JOIN student_parents sp ON s.id = sp.student_id
            JOIN parents p ON sp.parent_id = p.id
            WHERE DATE_FORMAT(s.date_of_birth, '%m-%d') = '$today_md'
              AND s.status = 'active'
              AND p.phone IS NOT NULL AND p.phone != ''
        ";
        $bday_res = $conn->query($bday_sql);
        
        $count_bday = 0;
        if ($bday_res && $bday_res->num_rows > 0) {
            while ($row = $bday_res->fetch_assoc()) {
                $student_name = trim($row['first_name'] . ' ' . $row['last_name']);
                $parent_title = !empty($row['title']) ? $row['title'] : '';
                $parent_name = trim($parent_title . ' ' . $row['p_last_name']);
                
                $vars = [
                    '{student_name}' => $student_name,
                    '{parent_name}' => $parent_name
                ];
                send_sms_from_template($conn, $tpl_id, $row['parent_contact'], $vars);
                $count_bday++;
            }
        }
        log_cron_activity("Birthday Wishes Task Completed: Sent $count_bday SMS wishes.");
    } else {
        log_cron_activity("Birthday Wishes Task Skipped: Template not configured.");
    }
} else {
    log_cron_activity("Birthday Wishes Task Skipped: Trigger is disabled.");
}

log_cron_activity("=== CRON JOB FINISHED ===");
echo "Cron Execution Complete. Check cron_log.txt for details.";
?>
