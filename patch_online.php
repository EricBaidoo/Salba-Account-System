<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Online DB Patch Script
 * ──────────────────────
 * Applies ONLY structural fixes to the online database.
 * Does NOT delete, truncate, or overwrite any existing data.
 *
 * Safe to run multiple times (all operations are idempotent).
 *
 * Deploy this file to your server and visit it once in the browser, then delete it.
 * OR run via SSH:  php patch_online.php
 */

// Load production config automatically
define('RUNNING_PATCH', true);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db_connect.php';

header('Content-Type: text/plain; charset=utf-8');

// Simple access guard — must pass ?token=salba2026patch in the URL
if (php_sapi_name() !== 'cli' && ($_GET['token'] ?? '') !== 'salba2026patch') {
    http_response_code(403);
    $host = $_SERVER['HTTP_HOST'] ?? 'your-domain.com';
    $path = $_SERVER['PHP_SELF'] ?? '/patch_online.php';
    die("403 Forbidden. Missing or invalid token.\n\nRun this patch by visiting:\nhttps://{$host}{$path}?token=salba2026patch");
}

$log   = [];
$errors = [];

function ok(string $msg)  { global $log;    $log[]    = "✓ " . $msg; }
function err(string $msg) { global $errors; $errors[] = "✗ " . $msg; }
function skip(string $msg){ global $log;    $log[]    = "~ " . $msg . " (already done)"; }

// ── 1. Drop broken view (definer from old server crashes any query touching it)
$broken = $conn->query("SELECT COUNT(*) as c FROM information_schema.VIEWS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='temp_t1_v_fee_assignments'")->fetch_assoc()['c'];
if ($broken > 0) {
    if ($conn->query("DROP VIEW IF EXISTS temp_t1_v_fee_assignments")) ok("Dropped broken view: temp_t1_v_fee_assignments");
    else err("Could not drop broken view: " . $conn->error);
} else {
    skip("Broken view temp_t1_v_fee_assignments not present");
}

// ── 2. Create 'Waivers & Scholarships' fee row if missing
//    This is the anchor row the billing engine requires — without it, no discounts ever apply.
$existing_waiver_fee = $conn->query("SELECT id FROM fees WHERE name='Waivers & Scholarships' LIMIT 1")->fetch_assoc();
if (!$existing_waiver_fee) {
    if ($conn->query("INSERT INTO fees (name, amount, fee_type, description) VALUES ('Waivers & Scholarships', 0.00, 'fixed', 'System row for scholarship/waiver discount entries — do not delete')")) {
        $waiver_fee_id = $conn->insert_id;
        ok("Created 'Waivers & Scholarships' fee row (id=$waiver_fee_id)");
    } else {
        err("Could not create waiver fee row: " . $conn->error);
        $waiver_fee_id = null;
    }
} else {
    $waiver_fee_id = $existing_waiver_fee['id'];
    skip("'Waivers & Scholarships' fee row already exists (id=$waiver_fee_id)");
}

// ── 3. Retroactively apply scholarship discounts to students who have
//    scholarship assignments but no matching discount row yet.
//    Safe: only INSERTS new rows, never modifies existing data.
if ($waiver_fee_id) {
    $assignments = $conn->query("
        SELECT ss.student_id, s.id as scholarship_id, s.name as scholarship_name,
               s.discount_type, s.discount_value, s.applies_to_fees,
               st.first_name, st.last_name
        FROM student_scholarships ss
        JOIN scholarships s ON ss.scholarship_id = s.id
        JOIN students st ON ss.student_id = st.id
        WHERE ss.status = 'active' AND s.status = 'active'
    ");

    if ($assignments) {
        $applied = 0;
        while ($a = $assignments->fetch_assoc()) {
            $student_id     = (int)$a['student_id'];
            $target_fee_ids = json_decode($a['applies_to_fees'] ?? '[]', true) ?: [];
            $discount_type  = $a['discount_type'];
            $discount_value = (float)$a['discount_value'];

            // Find all semester/year combos this student was billed in
            $combos_res = $conn->query("
                SELECT DISTINCT semester, academic_year
                FROM student_fees
                WHERE student_id=$student_id AND fee_id != $waiver_fee_id AND amount > 0 AND status != 'cancelled'
            ");
            if (!$combos_res) continue;

            while ($combo = $combos_res->fetch_assoc()) {
                $sem  = $conn->real_escape_string($combo['semester']);
                $year = $conn->real_escape_string($combo['academic_year']);
                $schol_name_esc = $conn->real_escape_string($a['scholarship_name']);

                // Skip if a discount for this scholarship already exists this semester
                $exists = $conn->query("
                    SELECT id FROM student_fees
                    WHERE student_id=$student_id AND fee_id=$waiver_fee_id
                      AND semester='$sem' AND academic_year='$year'
                      AND notes LIKE '%$schol_name_esc%'
                ")->fetch_assoc();
                if ($exists) continue;

                // Sum targeted fees for this semester
                if (empty($target_fee_ids)) {
                    $sum_res = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM student_fees WHERE student_id=$student_id AND semester='$sem' AND academic_year='$year' AND fee_id != $waiver_fee_id AND amount > 0 AND status != 'cancelled'");
                } else {
                    $fids = implode(',', array_map('intval', $target_fee_ids));
                    $sum_res = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM student_fees WHERE student_id=$student_id AND semester='$sem' AND academic_year='$year' AND fee_id IN($fids) AND amount > 0 AND status != 'cancelled'");
                }
                $target_amount = (float)($sum_res ? $sum_res->fetch_assoc()['t'] : 0);
                if ($target_amount <= 0) continue;

                $discount = $discount_type === 'percentage'
                    ? -1 * ($target_amount * $discount_value / 100)
                    : -1 * min($target_amount, $discount_value);

                $note     = $conn->real_escape_string("Waiver Applied: {$a['scholarship_name']}");
                $due_date = date('Y-m-d');
                $conn->query("INSERT INTO student_fees (student_id, fee_id, due_date, amount, amount_paid, semester, academic_year, notes, assigned_date, status) VALUES ($student_id, $waiver_fee_id, '$due_date', $discount, 0, '$sem', '$year', '$note', NOW(), 'paid')");
                $applied++;
            }
        }
        if ($applied > 0) ok("Applied $applied retroactive waiver discount rows");
        else skip("No new retroactive waiver discounts needed");
    } else {
        skip("No scholarships table or no active assignments found");
    }
}

// ── 4. Drop empty migration debris tables (old server leftovers — 0 rows)
foreach (['old1_v_fee_assignments', 'old2_v_fee_assignments', 'old3_v_fee_assignments'] as $table) {
    $exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$table'")->fetch_assoc()['c'];
    if ($exists) {
        $rows = (int)$conn->query("SELECT COUNT(*) as c FROM `$table`")->fetch_assoc()['c'];
        if ($rows === 0) {
            if ($conn->query("DROP TABLE IF EXISTS `$table`")) ok("Dropped empty table: $table");
            else err("Could not drop $table: " . $conn->error);
        } else {
            skip("$table has $rows rows — not dropping (not empty)");
        }
    } else {
        skip("Table $table does not exist");
    }
}

// ── 5. Payroll JSON columns (named allowances/deductions for payslip display)
$payroll_cols = [
    ['staff_salary_structures', 'custom_allowances', "TEXT DEFAULT NULL AFTER `allowances`"],
    ['staff_salary_structures', 'custom_deductions',  "TEXT DEFAULT NULL AFTER `deductions`"],
    ['payroll_records',         'custom_allowances', "TEXT DEFAULT NULL AFTER `allowances`"],
    ['payroll_records',         'custom_deductions',  "TEXT DEFAULT NULL AFTER `deductions`"],
    ['payroll_records',         'global_taxes',       "TEXT DEFAULT NULL AFTER `custom_deductions`"],
];
foreach ($payroll_cols as [$tbl, $col, $def]) {
    $exists_col = $conn->query("SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl' AND COLUMN_NAME='$col'")->fetch_assoc()['c'];
    if (!$exists_col) {
        if ($conn->query("ALTER TABLE `$tbl` ADD COLUMN `$col` $def")) ok("Added column $col to $tbl");
        else err("Could not add $col to $tbl: " . $conn->error);
    } else {
        skip("Column $col already exists in $tbl");
    }
}

// ── 6. semester_budget_item_sources table (budget sub-items / line-item breakdown)
$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='semester_budget_item_sources'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE semester_budget_item_sources (
        id INT PRIMARY KEY AUTO_INCREMENT,
        budget_item_id INT NOT NULL,
        source VARCHAR(255) NOT NULL,
        amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_sbis_item (budget_item_id),
        CONSTRAINT fk_sbis_item FOREIGN KEY (budget_item_id) REFERENCES semester_budget_items(id) ON DELETE CASCADE
    )";
    if ($conn->query($sql)) ok("Created table: semester_budget_item_sources");
    else err("Could not create semester_budget_item_sources: " . $conn->error);
} else {
    skip("Table semester_budget_item_sources already exists");
}

// ── 7. semester_budgets — add lock columns (status, locked_at, locked_by)
$col_check = $conn->query("SELECT COUNT(*) as c FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='semester_budgets' AND COLUMN_NAME='status'")->fetch_assoc()['c'];
if (!$col_check) {
    $sql = "ALTER TABLE semester_budgets 
        ADD COLUMN status ENUM('draft','locked') NOT NULL DEFAULT 'draft',
        ADD COLUMN locked_at DATETIME NULL DEFAULT NULL,
        ADD COLUMN locked_by VARCHAR(100) NULL DEFAULT NULL";
    if ($conn->query($sql)) ok("semester_budgets: added status, locked_at, locked_by columns");
    else err("semester_budgets lock columns: " . $conn->error);
} else {
    skip("semester_budgets lock columns already exist");
}

// ── 7a. Drop old class_stationery_items table (superseded by new 3-table schema)
$old_tbl = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='class_stationery_items'")->fetch_assoc()['c'];
if ($old_tbl) {
    if ($conn->query("DROP TABLE IF EXISTS class_stationery_items")) ok("Dropped old table: class_stationery_items");
    else err("Could not drop class_stationery_items: " . $conn->error);
} else {
    skip("Old table class_stationery_items not present");
}

// ── 7b. Drop stationery_submissions if it has the OLD schema (no assignment_id column)
//       The old table had class_stationery_item_id; new schema uses assignment_id.
//       Safe to drop — old data is meaningless without the old parent table.
$sub_exists  = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stationery_submissions'")->fetch_assoc()['c'];
$has_new_col = $conn->query("SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stationery_submissions' AND COLUMN_NAME='assignment_id'")->fetch_assoc()['c'];
if ($sub_exists && !$has_new_col) {
    if ($conn->query("DROP TABLE IF EXISTS stationery_submissions")) ok("Dropped old-schema stationery_submissions (will be recreated in step 10)");
    else err("Could not drop old stationery_submissions: " . $conn->error);
} elseif ($sub_exists && $has_new_col) {
    skip("stationery_submissions already has new schema — no action needed");
} else {
    skip("stationery_submissions does not exist yet — will be created in step 10");
}

// ── 8. stationery_items (master catalog)
$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stationery_items'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE stationery_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        unit VARCHAR(100) DEFAULT '',
        default_price DECIMAL(10,2) DEFAULT 0.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) ok("Created table: stationery_items");
    else err("stationery_items: " . $conn->error);
} else {
    skip("Table stationery_items already exists");
}

// ── 9. stationery_assignments (per-class item lists)
$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stationery_assignments'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE stationery_assignments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        class VARCHAR(100) NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        semester VARCHAR(50) DEFAULT '',
        quantity VARCHAR(100) NOT NULL DEFAULT '1',
        price DECIMAL(10,2) DEFAULT 0.00,
        sort_order INT DEFAULT 0,
        assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES stationery_items(id) ON DELETE CASCADE,
        UNIQUE KEY uq_item_class_year (item_id, class, academic_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) ok("Created table: stationery_assignments");
    else err("stationery_assignments: " . $conn->error);
} else {
    skip("Table stationery_assignments already exists");
}

// ── 10. stationery_submissions (student tracking)
$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='stationery_submissions'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE stationery_submissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        assignment_id INT NOT NULL,
        student_id INT NOT NULL,
        brought TINYINT(1) DEFAULT 0,
        billed TINYINT(1) DEFAULT 0,
        student_fee_id INT DEFAULT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_assign_student (assignment_id, student_id),
        FOREIGN KEY (assignment_id) REFERENCES stationery_assignments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) ok("Created table: stationery_submissions");
    else err("stationery_submissions: " . $conn->error);
} else {
    skip("Table stationery_submissions already exists");
}

// ── 11. Add 'data_entry' role to users table enum (migrate old 'clerk' users too)
$col = $conn->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='role'")->fetch_assoc();
// Step 1: Add both clerk and data_entry to the enum so we can safely UPDATE
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','supervisor','facilitator','bursar','finance','staff','clerk','data_entry') NOT NULL DEFAULT 'facilitator'");
// Step 2: Migrate any existing clerk users to data_entry
$conn->query("UPDATE users SET role='data_entry' WHERE role='clerk'");
$migrated = $conn->affected_rows;
if ($migrated > 0) ok("Migrated $migrated user(s) from 'clerk' → 'data_entry'");
else skip("No 'clerk' users to migrate");
// Step 3: Remove 'clerk' from enum
if ($conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('admin','supervisor','facilitator','bursar','finance','staff','data_entry') NOT NULL DEFAULT 'facilitator'"))
    ok("users.role enum updated — 'data_entry' role ready");
else err("Could not finalise users.role enum: " . $conn->error);

// ── 12. feeding module core tables
$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_daily_weekly_feeding'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE student_daily_weekly_feeding (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        class_id INT NULL,
        plan_type ENUM('weekly','monthly') NOT NULL,
        amount_per_unit DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        academic_year VARCHAR(20) NOT NULL,
        semester VARCHAR(50) NOT NULL,
        status ENUM('active','suspended','completed') NOT NULL DEFAULT 'active',
        deleted_from_bill_date DATE NULL,
        started_date DATE NOT NULL DEFAULT (CURRENT_DATE),
        ended_date DATE NULL,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_term (student_id, academic_year, semester),
        INDEX idx_feed_status (status),
        CONSTRAINT fk_dwf_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) ok("Created table: student_daily_weekly_feeding");
    else err("student_daily_weekly_feeding: " . $conn->error);
} else {
    skip("Table student_daily_weekly_feeding already exists");
}

$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feeding_payments'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE feeding_payments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        student_feeding_plan_id INT NOT NULL,
        payment_date DATE NOT NULL,
        payment_type ENUM('daily','weekly','monthly') NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        payment_method ENUM('cash','check','transfer','momo') NOT NULL DEFAULT 'cash',
        recorded_by INT NULL,
        month_no TINYINT UNSIGNED NULL,
        months_count TINYINT UNSIGNED NOT NULL DEFAULT 1,
        units_paid DECIMAL(10,2) NOT NULL DEFAULT 1.00,
        notes TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_feed_payment (student_id, payment_date, payment_type),
        INDEX idx_feed_pay_date (payment_date),
        CONSTRAINT fk_fp_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        CONSTRAINT fk_fp_plan FOREIGN KEY (student_feeding_plan_id) REFERENCES student_daily_weekly_feeding(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) ok("Created table: feeding_payments");
    else err("feeding_payments: " . $conn->error);
} else {
    skip("Table feeding_payments already exists");
}

// ── 13. feeding_class_rates settings table (class-based pricing)
$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feeding_class_rates'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE feeding_class_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(100) NOT NULL,
        plan_type ENUM('weekly','monthly') NOT NULL,
        amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        effective_from DATE NOT NULL,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        notes VARCHAR(255) NULL,
        created_by VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_feed_rate_lookup (class_name, plan_type, effective_from),
        UNIQUE KEY uq_feed_rate (class_name, plan_type, effective_from)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) ok("Created table: feeding_class_rates");
    else err("feeding_class_rates: " . $conn->error);
} else {
    skip("Table feeding_class_rates already exists");
}

// Convert legacy daily feeding rows to weekly before tightening the enum.
$daily_plan_count = 0;
$daily_rate_count = 0;
$res = $conn->query("SELECT COUNT(*) as c FROM student_daily_weekly_feeding WHERE plan_type = 'daily'");
if ($res) {
    $daily_plan_count = (int)($res->fetch_assoc()['c'] ?? 0);
}
if ($daily_plan_count > 0) {
    if ($conn->query("UPDATE student_daily_weekly_feeding SET plan_type = 'weekly', amount_per_unit = ROUND(amount_per_unit * 5, 2) WHERE plan_type = 'daily'")) {
        ok("Converted $daily_plan_count daily feeding plan row(s) to weekly");
    } else {
        err("Could not convert daily feeding plans: " . $conn->error);
    }
}
if ($conn->query("ALTER TABLE student_daily_weekly_feeding MODIFY COLUMN plan_type ENUM('weekly','monthly') NOT NULL")) {
    ok("student_daily_weekly_feeding.plan_type restricted to weekly/monthly");
} else {
    err("Could not restrict student_daily_weekly_feeding.plan_type: " . $conn->error);
}

$res = $conn->query("SELECT COUNT(*) as c FROM feeding_class_rates WHERE plan_type = 'daily'");
if ($res) {
    $daily_rate_count = (int)($res->fetch_assoc()['c'] ?? 0);
}
if ($daily_rate_count > 0) {
    if ($conn->query("UPDATE feeding_class_rates SET plan_type = 'weekly', amount = ROUND(amount * 5, 2) WHERE plan_type = 'daily'")) {
        ok("Converted $daily_rate_count daily rate row(s) to weekly");
    } else {
        err("Could not convert daily feeding rates: " . $conn->error);
    }
}
if ($conn->query("ALTER TABLE feeding_class_rates MODIFY COLUMN plan_type ENUM('weekly','monthly') NOT NULL")) {
    ok("feeding_class_rates.plan_type restricted to weekly/monthly");
} else {
    err("Could not restrict feeding_class_rates.plan_type: " . $conn->error);
}

// ── 14. Ensure new feeding columns exist on older installs
$feed_cols = [
    ['feeding_payments', 'payment_method', "ENUM('cash','check','transfer','momo') NOT NULL DEFAULT 'cash' AFTER amount"],
    ['feeding_payments', 'recorded_by',   "INT NULL AFTER payment_method"],
    ['feeding_payments', 'month_no',      "TINYINT UNSIGNED NULL AFTER recorded_by"],
    ['feeding_payments', 'months_count',  "TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER month_no"],
    ['feeding_payments', 'units_paid',    "DECIMAL(10,2) NOT NULL DEFAULT 1.00 AFTER months_count"],
];
foreach ($feed_cols as [$tbl, $col, $def]) {
    $exists_col = $conn->query("SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl' AND COLUMN_NAME='$col'")->fetch_assoc()['c'];
    if (!$exists_col) {
        if ($conn->query("ALTER TABLE `$tbl` ADD COLUMN `$col` $def")) ok("Added column $col to $tbl");
        else err("Could not add $col to $tbl: " . $conn->error);
    } else {
        skip("Column $col already exists in $tbl");
    }
}

// ── 15. feeding_day_closeouts table (daily reconciliation lock/reopen trail)
$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feeding_day_closeouts'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE feeding_day_closeouts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_name VARCHAR(100) NOT NULL,
        close_date DATE NOT NULL,
        academic_year VARCHAR(20) NOT NULL,
        semester VARCHAR(50) NOT NULL,
        expected_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        collected_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        variance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        cash_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        momo_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        transfer_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        check_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        close_notes VARCHAR(255) NULL,
        is_locked TINYINT(1) NOT NULL DEFAULT 1,
        closed_by INT NULL,
        closed_at DATETIME NOT NULL,
        reopened_by INT NULL,
        reopened_at DATETIME NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_feed_closeout (class_name, close_date, academic_year, semester),
        INDEX idx_feed_closeout_date (close_date),
        INDEX idx_feed_closeout_term (academic_year, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) ok("Created table: feeding_day_closeouts");
    else err("feeding_day_closeouts: " . $conn->error);
} else {
    skip("Table feeding_day_closeouts already exists");
}

// ── 16. Ensure feeding_day_closeouts has all required columns on older installs
$closeout_cols = [
    ['feeding_day_closeouts', 'academic_year', "VARCHAR(20) NOT NULL DEFAULT ''"],
    ['feeding_day_closeouts', 'semester', "VARCHAR(50) NOT NULL DEFAULT ''"],
    ['feeding_day_closeouts', 'expected_total', "DECIMAL(12,2) NOT NULL DEFAULT 0.00"],
    ['feeding_day_closeouts', 'collected_total', "DECIMAL(12,2) NOT NULL DEFAULT 0.00"],
    ['feeding_day_closeouts', 'variance', "DECIMAL(12,2) NOT NULL DEFAULT 0.00"],
    ['feeding_day_closeouts', 'cash_total', "DECIMAL(12,2) NOT NULL DEFAULT 0.00"],
    ['feeding_day_closeouts', 'momo_total', "DECIMAL(12,2) NOT NULL DEFAULT 0.00"],
    ['feeding_day_closeouts', 'transfer_total', "DECIMAL(12,2) NOT NULL DEFAULT 0.00"],
    ['feeding_day_closeouts', 'check_total', "DECIMAL(12,2) NOT NULL DEFAULT 0.00"],
    ['feeding_day_closeouts', 'close_notes', "VARCHAR(255) NULL"],
    ['feeding_day_closeouts', 'is_locked', "TINYINT(1) NOT NULL DEFAULT 1"],
    ['feeding_day_closeouts', 'closed_by', "INT NULL"],
    ['feeding_day_closeouts', 'closed_at', "DATETIME NULL"],
    ['feeding_day_closeouts', 'reopened_by', "INT NULL"],
    ['feeding_day_closeouts', 'reopened_at', "DATETIME NULL"],
];
foreach ($closeout_cols as [$tbl, $col, $def]) {
    $exists_col = $conn->query("SELECT COUNT(*) as c FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$tbl' AND COLUMN_NAME='$col'")->fetch_assoc()['c'];
    if (!$exists_col) {
        if ($conn->query("ALTER TABLE `$tbl` ADD COLUMN `$col` $def")) ok("Added column $col to $tbl");
        else err("Could not add $col to $tbl: " . $conn->error);
    } else {
        skip("Column $col already exists in $tbl");
    }
}

$uq_closeout = $conn->query("SELECT COUNT(*) as c FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feeding_day_closeouts' AND INDEX_NAME='uq_feed_closeout'")->fetch_assoc()['c'];
if (!$uq_closeout) {
    if ($conn->query("ALTER TABLE feeding_day_closeouts ADD UNIQUE KEY uq_feed_closeout (class_name, close_date, academic_year, semester)")) {
        ok("Added unique key uq_feed_closeout to feeding_day_closeouts");
    } else {
        err("Could not add uq_feed_closeout to feeding_day_closeouts: " . $conn->error);
    }
} else {
    skip("Unique key uq_feed_closeout already exists on feeding_day_closeouts");
}

// ── 17. feeding_weekly_adjustments table (weekly expected overrides for missed days)
$tbl_exists = $conn->query("SELECT COUNT(*) as c FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='feeding_weekly_adjustments'")->fetch_assoc()['c'];
if (!$tbl_exists) {
    $sql = "CREATE TABLE feeding_weekly_adjustments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        student_feeding_plan_id INT NOT NULL,
        week_start_date DATE NOT NULL,
        week_end_date DATE NOT NULL,
        academic_year VARCHAR(30) NOT NULL,
        semester VARCHAR(100) NOT NULL,
        days_missed TINYINT UNSIGNED NOT NULL DEFAULT 0,
        reason VARCHAR(255) DEFAULT NULL,
        created_by INT DEFAULT NULL,
        updated_by INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_student_plan_week (student_id, student_feeding_plan_id, week_start_date, week_end_date, academic_year, semester),
        KEY idx_week_lookup (week_start_date, week_end_date, academic_year, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    if ($conn->query($sql)) ok("Created table: feeding_weekly_adjustments");
    else err("feeding_weekly_adjustments: " . $conn->error);
} else {
    skip("Table feeding_weekly_adjustments already exists");
}

// ── Output
echo "=== ONLINE DB PATCH RESULTS ===" . PHP_EOL;
echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "Database: " . DB_NAME . PHP_EOL . PHP_EOL;

foreach ($log as $line)    echo $line . PHP_EOL;
foreach ($errors as $line) echo $line . PHP_EOL;

echo PHP_EOL . ($errors ? count($errors) . " error(s) — review above." : "All patches applied successfully.") . PHP_EOL;
echo PHP_EOL . "⚠  DELETE THIS FILE from the server after running it." . PHP_EOL;
