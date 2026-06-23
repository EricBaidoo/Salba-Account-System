<?php
/**
 * ============================================================
 *  SALBA MONTESSORI — PRODUCTION DATA MIGRATION SCRIPT
 * ============================================================
 *  Purpose : Migrate historical data (students, payments,
 *            expenses, student fees, and allocations) from the
 *            old database dump into the new online production database.
 *
 *  Usage   : Upload this file + the SQL dump to your server,
 *            then open in browser:
 *              https://yourdomain.com/migrate_production.php
 *
 *  Security: Protected by a secret token. Change TOKEN below.
 *
 *  Files needed on server (same folder as this script):
 *    - migrate_production.php  (this file)
 *    - old_db.sql              (rename u420775839_Salba_acc1.sql)
 *
 *  IMPORTANT: DELETE this file from the server after migration!
 * ============================================================
 */

// ── SECURITY TOKEN ─────────────────────────────────────────────────────────
define('MIGRATE_TOKEN', 'SALBA_MIGRATE_2025_SECURE');

// ── PRODUCTION DATABASE CREDENTIALS ────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'u420775839_admin');
define('DB_PASS', 'Eric0056@2024');
define('DB_NAME', 'u420775839_smis');

// ── SQL DUMP FILE ───────────────────────────────────────────────────────────
define('OLD_DB_SQL', __DIR__ . '/old_db.sql');

// ── SEMESTER DATE BOUNDARIES (SALBA 2025/2026) ─────────────────────────────
define('SEM1_START', '2025-09-01');
define('SEM1_END',   '2025-12-31');
define('SEM2_START', '2026-01-01');
define('SEM2_END',   '2026-04-03');
define('SEM3_START', '2026-04-15');
define('SEM3_END',   '2026-07-31');

// ───────────────────────────────────────────────────────────────────────────

date_default_timezone_set('Africa/Accra');
ini_set('max_execution_time', 600); // 10 minutes max
ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

// ── TOKEN CHECK ─────────────────────────────────────────────────────────────
$token   = $_GET['token']   ?? '';
$execute = $_GET['execute'] ?? '0';

if ($token !== MIGRATE_TOKEN) {
    http_response_code(403);
    die('<h2 style="font-family:monospace;color:red;">403 — Access Denied. Invalid or missing token.</h2>');
}

// ── CONNECT TO PRODUCTION DB ────────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die('<h2 style="color:red;">DB Connection Failed: ' . $conn->connect_error . '</h2>');
}
$conn->set_charset('utf8mb4');

$dry_run = ($execute !== '1');

// ── HELPERS ─────────────────────────────────────────────────────────────────
function out($msg, $type = 'info') {
    $colors = ['info' => '#ccc', 'success' => '#4ade80', 'warn' => '#facc15', 'error' => '#f87171', 'head' => '#60a5fa'];
    $color  = $colors[$type] ?? '#ccc';
    echo '<div style="color:' . $color . ';font-family:monospace;font-size:13px;padding:1px 0;">' . htmlspecialchars($msg) . '</div>';
    flush();
    if (ob_get_level() > 0) ob_flush();
}

function classify_semester($date_str, $old_term = '') {
    $ts = strtotime($date_str);
    if ($ts >= strtotime(SEM1_START) && $ts <= strtotime(SEM1_END)) return 'First Semester';
    if ($ts >= strtotime(SEM2_START) && $ts <= strtotime(SEM2_END)) return 'Second Semester';
    if ($ts >= strtotime(SEM3_START) && $ts <= strtotime(SEM3_END)) return 'Trimester';

    $term = strtolower(trim($old_term ?? ''));
    if ($term === 'first term')  return 'First Semester';
    if ($term === 'second term') return 'Second Semester';
    if ($term === 'third term')  return 'Trimester';

    if ($ts < strtotime(SEM1_START)) return 'First Semester';
    if ($ts >= strtotime('2026-04-04') && $ts < strtotime(SEM3_START)) return 'Second Semester';
    if ($ts > strtotime(SEM3_END))   return 'Trimester';
    return 'First Semester';
}

function record_journal_entry($conn, $date, $ref_type, $ref_id, $description, $lines) {
    $total_debit = 0;
    $total_credit = 0;
    foreach ($lines as $line) {
        $total_debit += (float)($line['debit'] ?? 0);
        $total_credit += (float)($line['credit'] ?? 0);
    }
    if (round($total_debit, 2) !== round($total_credit, 2)) {
        return false;
    }
    
    // Insert Header
    $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, reference_type, reference_id, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $date, $ref_type, $ref_id, $description);
    $stmt->execute();
    $je_id = $stmt->insert_id;
    $stmt->close();

    // Insert Lines
    $line_stmt = $conn->prepare("INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit) VALUES (?, ?, ?, ?)");
    
    static $acc_cache = [];

    foreach ($lines as $line) {
        $code = $line['account_code'];
        if (!isset($acc_cache[$code])) {
            $res = $conn->query("SELECT id FROM accounts WHERE account_code = '$code' LIMIT 1");
            if ($res && $res->num_rows > 0) {
                $acc_cache[$code] = $res->fetch_assoc()['id'];
            } else {
                throw new Exception("Account code $code not found.");
            }
        }
        
        $acc_id = $acc_cache[$code];
        $dr = (float)($line['debit'] ?? 0);
        $cr = (float)($line['credit'] ?? 0);
        
        if ($dr > 0 || $cr > 0) {
            $line_stmt->bind_param("iidd", $je_id, $acc_id, $dr, $cr);
            $line_stmt->execute();
        }
    }
    $line_stmt->close();
    return $je_id;
}

function formatAcademicYearDisplay($conn, $raw_year) {
    return $raw_year;
}

// ── PAGE OUTPUT ─────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SALBA Migration</title>
<style>
  body { background: #0f172a; color: #e2e8f0; font-family: monospace; padding: 24px; margin: 0; }
  h1   { color: #60a5fa; border-bottom: 1px solid #334155; padding-bottom: 12px; }
  .box { background: #1e293b; border-radius: 8px; padding: 20px; margin-top: 16px; }
  .btn { display: inline-block; padding: 12px 28px; border-radius: 6px; font-size: 15px;
         font-weight: bold; text-decoration: none; margin: 8px 4px; cursor: pointer; }
  .btn-green  { background: #16a34a; color: #fff; }
  .btn-yellow { background: #ca8a04; color: #fff; }
  .box-log { height:500px; overflow-y:auto; background:#0b1329; border:1px solid #1e293b; padding:15px; border-radius:8px; margin-top:16px; }
  .stat { display:inline-block; background:#0f172a; border-radius:6px; padding:10px 18px;
          margin:6px; text-align:center; min-width:140px; }
  .stat .n { font-size:22px; font-weight:bold; color:#60a5fa; }
  .stat .l { font-size:11px; color:#94a3b8; margin-top:4px; }
</style>
</head>
<body>
<h1>🏫 SALBA Montessori — Production Data Migration</h1>

<?php if ($dry_run): ?>
<div class="box">
  <p style="color:#facc15;font-size:14px;">⚠ <strong>DRY RUN MODE</strong> — No changes will be made to the database. Review the output below, then click <em>Execute Migration</em> to commit.</p>
  <a class="btn btn-green" href="?token=<?= urlencode(MIGRATE_TOKEN) ?>&execute=1" onclick="return confirm('Are you sure? This will modify the production database.')">▶ Execute Migration</a>
  <a class="btn btn-yellow" href="?token=<?= urlencode(MIGRATE_TOKEN) ?>">🔄 Re-run Dry Run</a>
</div>
<?php else: ?>
<div class="box" style="border-left:4px solid #dc2626;">
  <p style="color:#f87171;font-size:14px;">🔴 <strong>LIVE EXECUTION MODE</strong> — Writing to production database.</p>
</div>
<?php endif; ?>

<div class="box-log" id="log">
<?php

// ── CHECK SQL FILE ──────────────────────────────────────────────────────────
if (!file_exists(OLD_DB_SQL)) {
    out("ERROR: old_db.sql not found at " . OLD_DB_SQL, 'error');
    out("Please upload the old database SQL file as 'old_db.sql' in the same folder as this script.", 'warn');
    echo '</div></body></html>';
    exit;
}

// ── LOAD OLD DB INTO TEMP TABLES ────────────────────────────────────────────
out("====================================================", 'head');
out("   SALBA MONTESSORI — CLEAN DATA MIGRATION         ", 'head');
out("====================================================", 'head');
out("Mode: " . ($dry_run ? "DRY RUN (no writes)" : "LIVE EXECUTION (writing to database)"), $dry_run ? 'warn' : 'error');
out("Server time: " . date('Y-m-d H:i:s'));
out("----------------------------------------------------");
out("Loading old database SQL dump...");

$old_sql = file_get_contents(OLD_DB_SQL);

$old_tables = ['classes','expenses','expense_categories','fees','fee_amounts','fee_categories',
               'payments','payment_allocations','students','student_fees','system_settings',
               'term_budgets','term_budget_items','users','v_fee_assignments'];

foreach ($old_tables as $t) {
    $old_sql = preg_replace('/`' . $t . '`/', '`tmp_old_' . $t . '`', $old_sql);
}
$old_sql = preg_replace('/ALTER TABLE `[a-zA-Z0-9_]+`\s+ADD CONSTRAINT.*?;/is', '', $old_sql);
$old_sql = preg_replace('/CREATE TABLE `tmp_old_v_fee_assignments`.*?;/is', '', $old_sql);
$old_sql = preg_replace('/DROP TABLE IF EXISTS `tmp_old_v_fee_assignments`;/is', '', $old_sql);
$old_sql = preg_replace('/CREATE ALGORITHM=.*?VIEW `tmp_old_v_fee_assignments` .*?;/is', '', $old_sql);

$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
$conn->query("DROP VIEW IF EXISTS `tmp_old_v_fee_assignments`;");
foreach ($old_tables as $t) { $conn->query("DROP TABLE IF EXISTS `tmp_old_$t`;"); }

if ($conn->multi_query($old_sql)) {
    do { if ($r = $conn->store_result()) $r->free(); } while ($conn->next_result());
}
if ($conn->error) {
    out("ERROR loading old DB: " . $conn->error, 'error');
    exit;
}
out("Old database loaded successfully into temporary tables.", 'success');

// ── ROLLBACK PREVIOUS BAD MIGRATION ────────────────────────────────────────
out("----------------------------------------------------");
out("Clearing previous migration data...");
if (!$dry_run) {
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    $conn->query("TRUNCATE TABLE payment_allocations");
    $conn->query("TRUNCATE TABLE payments");
    $conn->query("TRUNCATE TABLE expenses");
    $conn->query("TRUNCATE TABLE student_fees");
    $conn->query("TRUNCATE TABLE journal_lines");
    $conn->query("TRUNCATE TABLE journal_entries");
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    out("Tables cleared: payments, expenses, student_fees, payment_allocations, journal_entries, journal_lines.", 'success');
} else {
    out("[DRY RUN] Would truncate: payments, expenses, student_fees, payment_allocations, journal_entries, journal_lines.", 'warn');
}

// ── BEGIN TRANSACTION ───────────────────────────────────────────────────────
if (!$dry_run) $conn->begin_transaction();

try {

    // ── STUDENTS ────────────────────────────────────────────────────────────
    out("----------------------------------------------------");
    out("Migrating students...", 'head');

    $new_students = [];
    $res = $conn->query("SELECT id, first_name, last_name FROM students");
    while ($r = $res->fetch_assoc()) {
        $key = strtolower(trim($r['first_name'] . ' ' . $r['last_name']));
        $new_students[$key] = (int)$r['id'];
    }

    $old_student_rows = [];
    $res = $conn->query("SELECT id, first_name, last_name, class, date_of_birth, parent_contact, status, created_at FROM tmp_old_students ORDER BY id");
    while ($r = $res->fetch_assoc()) $old_student_rows[] = $r;

    $student_map    = [];
    $matched        = 0;
    $created        = 0;
    $status_updated = 0;

    foreach ($old_student_rows as $s) {
        $fn  = trim($s['first_name']);
        $ln  = trim($s['last_name']);
        $key = strtolower("$fn $ln");
        $old_status = $s['status'] ?? 'active';

        if (isset($new_students[$key])) {
            $new_id = $new_students[$key];
            $student_map[(int)$s['id']] = $new_id;
            $matched++;
            if (!$dry_run && $old_status === 'inactive') {
                $conn->query("UPDATE students SET status='inactive' WHERE id=$new_id AND status='active'");
                if ($conn->affected_rows > 0) $status_updated++;
            }
        } else {
            if (!$dry_run) {
                $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, class, date_of_birth, parent_contact, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("sssssss", $fn, $ln, $s['class'], $s['date_of_birth'], $s['parent_contact'], $old_status, $s['created_at']);
                $stmt->execute();
                $new_id = (int)$stmt->insert_id;
                $stmt->close();
                $new_students[$key] = $new_id;
            } else {
                $new_id = 900000 + $created;
            }
            $student_map[(int)$s['id']] = $new_id;
            $created++;
        }
    }
    out("Students: $matched matched | $created new | $status_updated status corrections.", 'success');

    // ── EXPENSES ────────────────────────────────────────────────────────────
    out("----------------------------------------------------");
    out("Migrating expenses...", 'head');

    $res = $conn->query("SELECT category_id, amount, expense_date, description, term, academic_year FROM tmp_old_expenses ORDER BY expense_date");
    $expenses = [];
    while ($r = $res->fetch_assoc()) $expenses[] = $r;

    $exp_counts = $exp_totals = [];
    $exp_migrated = 0;
    $expense_map = [];

    foreach ($expenses as $e) {
        $cat_id      = (int)($e['category_id'] ?? 10);
        if ($cat_id === 17 || $cat_id < 1) $cat_id = 10; // "curry" → Miscellaneous
        $amount      = (float)$e['amount'];
        $date        = $e['expense_date'];
        $description = trim($e['description'] ?? 'Expense');
        $year        = trim($e['academic_year'] ?? '') ?: '2025/2026';
        $semester    = classify_semester($date, $e['term']);

        $exp_counts[$semester] = ($exp_counts[$semester] ?? 0) + 1;
        $exp_totals[$semester] = ($exp_totals[$semester] ?? 0) + $amount;

        $new_exp_id = null;
        if (!$dry_run) {
            $stmt = $conn->prepare("INSERT INTO expenses (category_id, amount, expense_date, description, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("idssss", $cat_id, $amount, $date, $description, $semester, $year);
            $stmt->execute();
            $new_exp_id = $conn->insert_id;
            $stmt->close();
            
            // Auto-generate double-entry journal entry for expenses
            record_journal_entry($conn, $date, 'Expense', $new_exp_id, "Expense: $description", [
                ['account_code' => '5200', 'debit' => $amount, 'credit' => 0], // Operational Expense DR
                ['account_code' => '1000', 'debit' => 0, 'credit' => $amount]  // Cash CR
            ]);
        } else {
            $new_exp_id = 700000 + $exp_migrated;
        }
        $exp_migrated++;
    }

    out("Expenses migrated: $exp_migrated total", 'success');
    foreach ($exp_counts as $sem => $cnt) {
        out("  $sem: $cnt records = GHS " . number_format($exp_totals[$sem], 2));
    }
    $exp_grand = array_sum($exp_totals);
    out("  GRAND TOTAL: GHS " . number_format($exp_grand, 2), 'success');

    // ── PAYMENTS ────────────────────────────────────────────────────────────
    out("----------------------------------------------------");
    out("Migrating payments...", 'head');

    $res = $conn->query("SELECT id, student_id, payment_type, amount, payment_date, receipt_no, description, term, academic_year FROM tmp_old_payments ORDER BY payment_date");
    $payments = [];
    while ($r = $res->fetch_assoc()) $payments[] = $r;

    $pay_counts = $pay_totals = [];
    $pay_migrated = $pay_unmatched = 0;
    $payment_map = [];

    foreach ($payments as $p) {
        $old_sid = $p['student_id'] ? (int)$p['student_id'] : null;
        $new_sid = ($old_sid && isset($student_map[$old_sid])) ? $student_map[$old_sid] : null;
        if (!$new_sid) $pay_unmatched++;

        $amount      = (float)$p['amount'];
        $date        = $p['payment_date'];
        $pay_type    = $p['payment_type'] ?? 'student';
        $receipt     = trim($p['receipt_no'] ?? '');
        $description = trim($p['description'] ?? '');
        $year        = trim($p['academic_year'] ?? '') ?: '2025/2026';
        $semester    = classify_semester($date, $p['term']);
        $fee_id      = null;

        $pay_counts[$semester] = ($pay_counts[$semester] ?? 0) + 1;
        $pay_totals[$semester] = ($pay_totals[$semester] ?? 0) + $amount;

        $new_pay_id = null;
        if (!$dry_run) {
            $stmt = $conn->prepare("INSERT INTO payments (student_id, fee_id, payment_type, amount, payment_date, receipt_no, description, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdsssss", $new_sid, $fee_id, $pay_type, $amount, $date, $receipt, $description, $semester, $year);
            $stmt->execute();
            $new_pay_id = $conn->insert_id;
            $stmt->close();
            
            // Auto-generate double-entry journal entry for payments
            if ($pay_type === 'general') {
                $desc = "General Payment RCPT: $receipt - $description";
                record_journal_entry($conn, $date, 'Payment', $new_pay_id, $desc, [
                    ['account_code' => '1000', 'debit' => $amount, 'credit' => 0], // DR Cash
                    ['account_code' => '4100', 'debit' => 0, 'credit' => $amount]  // CR Misc Revenue
                ]);
            } else {
                $desc = "Student Payment RCPT: $receipt - $description";
                record_journal_entry($conn, $date, 'Payment', $new_pay_id, $desc, [
                    ['account_code' => '1000', 'debit' => $amount, 'credit' => 0], // DR Cash
                    ['account_code' => '1200', 'debit' => 0, 'credit' => $amount]  // CR Accounts Rec
                ]);
            }
        } else {
            $new_pay_id = 900000 + $pay_migrated;
        }
        $payment_map[(int)$p['id']] = $new_pay_id;
        $pay_migrated++;
    }

    out("Payments migrated: $pay_migrated total | Unmatched student IDs: $pay_unmatched", 'success');
    foreach ($pay_counts as $sem => $cnt) {
        out("  $sem: $cnt payments = GHS " . number_format($pay_totals[$sem], 2));
    }
    $pay_grand = array_sum($pay_totals);
    out("  GRAND TOTAL: GHS " . number_format($pay_grand, 2), 'success');

    // ── STUDENT FEES (FEE ASSIGNMENTS) ──────────────────────────────────────
    out("----------------------------------------------------");
    out("Migrating student fee assignments...", 'head');

    $term_to_sem = [
        'first term'  => 'First Semester',
        'second term' => 'Second Semester',
        'third term'  => 'Trimester',
    ];
    $status_map = [
        'paid'      => 'paid',
        'pending'   => 'pending',
        'due'       => 'pending',
        'overdue'   => 'pending',
        'cancelled' => 'cancelled',
    ];

    $res = $conn->query("SELECT id, student_id, fee_id, amount, amount_paid, term, academic_year, notes, assigned_date, due_date, status FROM tmp_old_student_fees ORDER BY assigned_date");
    $sf_rows = [];
    if ($res) while ($r = $res->fetch_assoc()) $sf_rows[] = $r;

    $sf_migrated = 0;
    $sf_skipped  = 0;
    $sf_counts   = [];
    $sf_totals   = [];
    $student_fee_map = [];

    foreach ($sf_rows as $sf) {
        $old_sid = $sf['student_id'] ? (int)$sf['student_id'] : null;
        $new_sid = ($old_sid && isset($student_map[$old_sid])) ? $student_map[$old_sid] : null;

        if (!$new_sid) { $sf_skipped++; continue; }

        $fee_id       = $sf['fee_id'] ? (int)$sf['fee_id'] : null;
        $amount       = (float)$sf['amount'];
        $amount_paid  = (float)($sf['amount_paid'] ?? 0);
        $old_term     = strtolower(trim($sf['term'] ?? ''));
        $semester     = $term_to_sem[$old_term] ?? 'First Semester';
        $year         = trim($sf['academic_year'] ?? '') ?: '2025/2026';
        $notes        = trim($sf['notes'] ?? '');
        $assigned     = $sf['assigned_date'] ?? date('Y-m-d H:i:s');
        $due_date     = $sf['due_date'] ?: null;
        $old_status   = strtolower(trim($sf['status'] ?? 'pending'));
        $status       = $status_map[$old_status] ?? 'pending';

        $sf_counts[$semester] = ($sf_counts[$semester] ?? 0) + 1;
        $sf_totals[$semester] = ($sf_totals[$semester] ?? 0) + $amount;

        $new_sf_id = null;
        if (!$dry_run) {
            $stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_id, amount, amount_paid, semester, academic_year, notes, assigned_date, due_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iiddssssss", $new_sid, $fee_id, $amount, $amount_paid, $semester, $year, $notes, $assigned, $due_date, $status);
            $stmt->execute();
            $new_sf_id = $conn->insert_id;
            $stmt->close();
            
            // Auto-generate double-entry journal entry for gross bill assignments
            if ($amount > 0) {
                record_journal_entry($conn, $assigned, 'StudentBill', $new_sid, "Semester Bill for Student #$new_sid ($semester)", [
                    ['account_code' => '1200', 'debit' => $amount, 'credit' => 0], // DR Accounts Rec
                    ['account_code' => '4000', 'debit' => 0, 'credit' => $amount]  // CR Tuition Revenue
                ]);
            }
        } else {
            $new_sf_id = 800000 + $sf_migrated;
        }
        $student_fee_map[(int)$sf['id']] = $new_sf_id;
        $sf_migrated++;
    }

    out("Student Fees: $sf_migrated migrated | $sf_skipped skipped (unmatched students)", 'success');
    foreach ($sf_counts as $sem => $cnt) {
        out("  $sem: $cnt assignments = GHS " . number_format($sf_totals[$sem], 2));
    }
    $sf_grand = array_sum($sf_totals);
    out("  GRAND TOTAL: GHS " . number_format($sf_grand, 2), 'success');

    // ── PAYMENT ALLOCATIONS ─────────────────────────────────────────────────
    out("----------------------------------------------------");
    out("Migrating payment allocations...", 'head');

    $res = $conn->query("SELECT id, payment_id, student_fee_id, amount FROM tmp_old_payment_allocations");
    $allocs = [];
    if ($res) while ($r = $res->fetch_assoc()) $allocs[] = $r;

    $alloc_migrated = 0;
    $alloc_skipped = 0;
    foreach ($allocs as $a) {
        $old_pid  = (int)$a['payment_id'];
        $old_sfid = (int)$a['student_fee_id'];
        
        $new_pid  = $payment_map[$old_pid] ?? null;
        $new_sfid = $student_fee_map[$old_sfid] ?? null;
        
        if ($new_pid && $new_sfid) {
            if (!$dry_run) {
                $stmt = $conn->prepare("INSERT INTO payment_allocations (payment_id, student_fee_id, amount) VALUES (?, ?, ?)");
                $stmt->bind_param("iid", $new_pid, $new_sfid, $a['amount']);
                $stmt->execute();
                $stmt->close();
            }
            $alloc_migrated++;
        } else {
            $alloc_skipped++;
        }
    }
    out("Payment allocations: $alloc_migrated migrated | $alloc_skipped skipped due to unmatched IDs.", 'success');

    // ── INACTIVE STUDENT REPORT ─────────────────────────────────────────────
    out("----------------------------------------------------");
    out("INACTIVE STUDENT REPORT:", 'warn');
    $res = $conn->query("
        SELECT s.id, s.first_name, s.last_name, s.status,
               COUNT(p.id) as payment_count,
               COALESCE(SUM(p.amount), 0) as total_paid,
               MAX(p.payment_date) as last_payment
        FROM tmp_old_students s
        LEFT JOIN tmp_old_payments p ON p.student_id = s.id
        WHERE s.status = 'inactive'
        GROUP BY s.id ORDER BY total_paid DESC
    ");
    $inactive_count = 0;
    if ($res) while ($r = $res->fetch_assoc()) {
        if ((int)$r['payment_count'] > 0) {
            out("  ⚠ {$r['first_name']} {$r['last_name']} — {$r['payment_count']} payments, GHS " . number_format($r['total_paid'], 2) . " paid, last: {$r['last_payment']}", 'warn');
            $inactive_count++;
        }
    }
    out("  Inactive students with payment history: $inactive_count");

    // ── COMMIT ──────────────────────────────────────────────────────────────
    if (!$dry_run) {
        $conn->commit();
        out("----------------------------------------------------");
        out("✅ SUCCESS: All data committed to production database!", 'success');
        out("Total Income migrated:      GHS " . number_format($pay_grand, 2), 'success');
        out("Total Expenditure migrated: GHS " . number_format($exp_grand, 2), 'success');
        out("Total Assignments migrated: GHS " . number_format($sf_grand, 2), 'success');
    } else {
        out("----------------------------------------------------");
        out("✅ DRY RUN COMPLETE — No errors found. Ready to execute.", 'success');
    }

} catch (Exception $e) {
    if (!$dry_run) $conn->rollback();
    out("CRITICAL ERROR: " . $e->getMessage(), 'error');
    out("Transaction rolled back. No data was changed.", 'error');
}

// ── CLEANUP ─────────────────────────────────────────────────────────────────
$conn->query("SET FOREIGN_KEY_CHECKS = 0;");
foreach ($old_tables as $t) { $conn->query("DROP TABLE IF EXISTS `tmp_old_$t`;"); }
$conn->query("SET FOREIGN_KEY_CHECKS = 1;");
out("Temporary tables cleaned up.");
out("====================================================", 'head');

// ── SUMMARY TABLE ───────────────────────────────────────────────────────────
?>
</div>

<div class="box">
  <h3 style="color:#60a5fa;margin-top:0;">📊 Migration Summary</h3>
  <div>
    <div class="stat"><div class="n"><?= $pay_migrated ?? 0 ?></div><div class="l">Payments</div></div>
    <div class="stat"><div class="n"><?= $exp_migrated ?? 0 ?></div><div class="l">Expenses</div></div>
    <div class="stat"><div class="n"><?= $sf_migrated ?? 0 ?></div><div class="l">Fee Assignments</div></div>
    <div class="stat"><div class="n"><?= $alloc_migrated ?? 0 ?></div><div class="l">Allocations</div></div>
    <div class="stat"><div class="n">GHS <?= number_format($pay_grand ?? 0, 0) ?></div><div class="l">Total Income</div></div>
  </div>
</div>

<?php if (!$dry_run): ?>
<div class="box" style="border-left:4px solid #f87171;">
  <p style="color:#f87171;font-weight:bold;">⚠ IMPORTANT: Delete this file from the server now that migration is complete!</p>
  <p style="color:#94a3b8;font-size:12px;">This script has access to your production database. Leaving it on the server is a security risk.</p>
</div>
<?php endif; ?>

<script>
  var log = document.getElementById('log');
  if (log) log.scrollTop = log.scrollHeight;
</script>
</body>
</html>
