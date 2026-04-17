<?php
$conn = new mysqli('localhost', 'root', 'root', 'Salba_acc');

echo "--- STARTING DATA STANDARDIZATION (TERM -> SEMESTER) ---\n";

$tables = [
    'payments' => 'term',
    'expenses' => 'term',
    'student_fees' => 'semester', // Already semester?
    'semester_budgets' => 'term',
    'budgets' => 'term'
];

foreach ($tables as $table => $old_col) {
    // Check if table exists
    $table_exists = $conn->query("SHOW TABLES LIKE '$table'")->num_rows > 0;
    if (!$table_exists) {
        echo "  - Table $table does not exist, skipping.\n";
        continue;
    }

    $res = $conn->query("DESCRIBE `$table`")->fetch_all(MYSQLI_ASSOC);
    $cols = array_column($res, 'Field');
    
    // 1. Rename column if it is still 'term'
    if (in_array('term', $cols) && !in_array('semester', $cols)) {
        echo "  - Renaming 'term' to 'semester' in $table\n";
        $conn->query("ALTER TABLE `$table` CHANGE `term` `semester` VARCHAR(50)");
    }
    
    // 2. Standardize Labels
    echo "  - Standardizing labels in $table\n";
    $conn->query("UPDATE `$table` SET `semester` = 'First Semester' WHERE `semester` LIKE '%First%' OR `semester` = 'Semester 1'");
    $conn->query("UPDATE `$table` SET `semester` = 'Second Semester' WHERE `semester` LIKE '%Second%' OR `semester` = 'Semester 2'");
    $conn->query("UPDATE `$table` SET `semester` = 'Third Semester' WHERE `semester` LIKE '%Third%' OR `semester` = 'Semester 3'");
}

// Special Case: Expenses often have NULLs in this SQL
echo "Standardizing NULL labels based on dates...\n";
$conn->query("UPDATE expenses SET semester = 'First Semester' WHERE semester IS NULL AND expense_date BETWEEN '2025-09-01' AND '2025-12-31'");
$conn->query("UPDATE expenses SET semester = 'Second Semester' WHERE semester IS NULL AND expense_date BETWEEN '2026-01-01' AND '2026-04-30'");

// Final Verification of Targets
$rev = $conn->query("SELECT SUM(amount) as total FROM payments WHERE semester = 'Second Semester'")->fetch_assoc()['total'];
$exp = $conn->query("SELECT SUM(amount) as total FROM expenses WHERE semester = 'Second Semester'")->fetch_assoc()['total'];

echo "\n--- VERIFICATION RESULTS ---\n";
echo "TARGET REVENUE: GHS 172,340.00 | ACTUAL: GHS " . number_format($rev, 2) . "\n";
echo "TARGET EXPENSES: GHS 138,702.98 | ACTUAL: GHS " . number_format($exp, 2) . "\n";

if (abs($rev - 172340) < 1 && abs($exp - 138702.98) < 1) {
    echo "\nSUCCESS: ONLINE TRUTH RECONCILED!\n";
} else {
    echo "\nWARNING: Discrepancy detected. Rev Diff: " . ($rev - 172340) . "\n";
}
?>
