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

// ── Output
echo "=== ONLINE DB PATCH RESULTS ===" . PHP_EOL;
echo "Date: " . date('Y-m-d H:i:s') . PHP_EOL;
echo "Database: " . DB_NAME . PHP_EOL . PHP_EOL;

foreach ($log as $line)    echo $line . PHP_EOL;
foreach ($errors as $line) echo $line . PHP_EOL;

echo PHP_EOL . ($errors ? count($errors) . " error(s) — review above." : "All patches applied successfully.") . PHP_EOL;
echo PHP_EOL . "⚠  DELETE THIS FILE from the server after running it." . PHP_EOL;
