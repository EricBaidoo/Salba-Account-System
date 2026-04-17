<?php
if (!isset($_SERVER['SERVER_NAME'])) { $_SERVER['SERVER_NAME'] = 'localhost'; }
include 'includes/db_connect.php';

echo "--- STARTING DATABASE MIGRATION: DATE-BASED SEMESTER RE-ALIGNMENT ---\n";

$migrations = [
    // 1. Payments
    [
        'table' => 'payments',
        'date_col' => 'payment_date',
        'sem_col' => 'semester'
    ],
    // 2. Expenses
    [
        'table' => 'expenses',
        'date_col' => 'expense_date',
        'sem_col' => 'semester'
    ],
    // 3. Student Fees (using assigned_date)
    [
        'table' => 'student_fees',
        'date_col' => 'assigned_date',
        'sem_col' => 'semester'
    ]
];

$conn->begin_transaction();

try {
    foreach ($migrations as $m) {
        $t = $m['table'];
        $dc = $m['date_col'];
        $sc = $m['sem_col'];

        echo "Processing $t...\n";

        // First Semester: Sept-Dec 2025
        $sql1 = "UPDATE `$t` SET `$sc` = 'First Semester' WHERE `$dc` BETWEEN '2025-09-01' AND '2025-12-31'";
        if (!$conn->query($sql1)) throw new Exception($conn->error);
        echo "  - Updated First Semester records: " . $conn->affected_rows . "\n";

        // Second Semester: Jan-Apr 2026
        $sql2 = "UPDATE `$t` SET `$sc` = 'Second Semester' WHERE `$dc` BETWEEN '2026-01-01' AND '2026-04-30'";
        if (!$conn->query($sql2)) throw new Exception($conn->error);
        echo "  - Updated Second Semester records: " . $conn->affected_rows . "\n";

        // Third Semester Correct Spelling
        $sql3 = "UPDATE `$t` SET `$sc` = 'Third Semester' WHERE `$sc` = 'Third Semesters'";
        if (!$conn->query($sql3)) throw new Exception($conn->error);
        echo "  - Fixed Third Semester spelling: " . $conn->affected_rows . "\n";
    }

    $conn->commit();
    echo "\n--- MIGRATION COMPLETED SUCCESSFULLY ---\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "\n!!! MIGRATION FAILED: " . $e->getMessage() . "\n";
}
?>
