<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

echo "Starting System-Wide Migration: Term -> Semester\n";

// 1. Create Semester Dictionary
$conn->query("CREATE TABLE IF NOT EXISTS academic_semester_dictionary (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(50) UNIQUE NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$semesters = ['First Semester', 'Second Semester', 'Third Semester'];
$stmt = $conn->prepare("INSERT IGNORE INTO academic_semester_dictionary (semester_name, display_order) VALUES (?, ?)");
foreach ($semesters as $idx => $sem) {
    $order = $idx + 1;
    $stmt->bind_param("si", $sem, $order);
    $stmt->execute();
}
echo "Dictionary initialized.\n";

// 2. Rename Columns and Tables
$migrations = [
    ['table' => 'assessment_configurations', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'attendance', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'budgets', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'expenses', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'grades', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'lesson_plans', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'payments', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'student_fees', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'student_term_remarks', 'old' => 'term', 'new' => 'semester'],
    ['table' => 'teacher_allocations', 'old' => 'term', 'new' => 'semester']
];

foreach ($migrations as $m) {
    $t = $m['table'];
    $o = $m['old'];
    $n = $m['new'];
    
    // Check if column exists
    $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$o'");
    if ($res && $res->num_rows > 0) {
        $col_info = $res->fetch_assoc();
        $type = $col_info['Type'];
        $null = ($col_info['Null'] === 'YES') ? 'NULL' : 'NOT NULL';
        $def = ($col_info['Default'] !== null) ? "DEFAULT '".$col_info['Default']."'" : "";
        
        echo "Converting $t.$o to $t.$n...\n";
        $conn->query("ALTER TABLE `$t` CHANGE COLUMN `$o` `$n` $type $null $def");
        
        // Update Data
        $conn->query("UPDATE `$t` SET `$n` = REPLACE(`$n`, 'Term', 'Semester')");
    }
}

// 3. Rename Specific Tables
$table_renames = [
    ['old' => 'term_budgets', 'new' => 'semester_budgets'],
    ['old' => 'term_budget_items', 'new' => 'semester_budget_items'],
    ['old' => 'term_invoices', 'new' => 'semester_invoices']
];

foreach ($table_renames as $r) {
    $old_t = $r['old'];
    $new_t = $r['new'];
    $res = $conn->query("SHOW TABLES LIKE '$old_t'");
    if ($res && $res->num_rows > 0) {
        echo "Renaming table $old_t to $new_t...\n";
        $conn->query("RENAME TABLE `$old_t` TO `$new_t` ");
    }
}

// 4. Update specific columns in renamed tables
// semester_budgets (rename term to semester)
$res = $conn->query("SHOW COLUMNS FROM `semester_budgets` LIKE 'term'");
if ($res && $res->num_rows > 0) {
    echo "Updating semester_budgets.term to semester...\n";
    $conn->query("ALTER TABLE `semester_budgets` CHANGE COLUMN `term` `semester` VARCHAR(50)");
    $conn->query("UPDATE `semester_budgets` SET `semester` = REPLACE(`semester`, 'Term', 'Semester')");
}

// semester_budget_items (rename term_budget_id to semester_budget_id)
$res = $conn->query("SHOW COLUMNS FROM `semester_budget_items` LIKE 'term_budget_id'");
if ($res && $res->num_rows > 0) {
    echo "Updating semester_budget_items.term_budget_id to semester_budget_id...\n";
    $conn->query("ALTER TABLE `semester_budget_items` CHANGE COLUMN `term_budget_id` `semester_budget_id` INT");
}

// semester_invoices (rename term to semester)
$res = $conn->query("SHOW COLUMNS FROM `semester_invoices` LIKE 'term'");
if ($res && $res->num_rows > 0) {
    echo "Updating semester_invoices.term to semester...\n";
    $conn->query("ALTER TABLE `semester_invoices` CHANGE COLUMN `term` `semester` VARCHAR(50)");
    $conn->query("UPDATE `semester_invoices` SET `semester` = REPLACE(`semester`, 'Term', 'Semester')");
}

// 5. Update System Settings
echo "Updating System Settings keys...\n";
$settings_map = [
    'current_term' => 'current_semester',
    'term_oa_weight' => 'semester_oa_weight',
    'term_exam_weight' => 'semester_exam_weight',
    'weeks_per_term' => 'weeks_per_semester'
];

foreach ($settings_map as $old_k => $new_k) {
    $conn->query("UPDATE system_settings SET setting_key = '$new_k' WHERE setting_key = '$old_k'");
}

// Update specific complex keys (term_invoice_settings_*)
$conn->query("UPDATE system_settings SET setting_key = REPLACE(setting_key, 'term_invoice', 'semester_invoice') WHERE setting_key LIKE 'term_invoice%'");
$conn->query("UPDATE system_settings SET setting_key = REPLACE(setting_key, 'term', 'semester') WHERE setting_key LIKE '%term%'");

// Update actual setting values
$conn->query("UPDATE system_settings SET setting_value = REPLACE(setting_value, 'Term', 'Semester')");

echo "Migration Complete.\n";
$conn->close();
?>
