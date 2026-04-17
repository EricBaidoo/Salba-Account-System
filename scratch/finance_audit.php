<?php
// Force local environment detection for CLI
if (!isset($_SERVER['SERVER_NAME'])) {
    $_SERVER['SERVER_NAME'] = 'localhost';
}
include 'includes/db_connect.php';
include 'includes/system_settings.php';

$curr_sem = getCurrentSemester($conn);
$acad_year = getAcademicYear($conn);

echo "SYSTEM CONTEXT:\n";
echo "Current Semester Label: " . $curr_sem . "\n";
echo "Academic Year Label: " . $acad_year . "\n\n";

function auditTable($conn, $table, $label) {
    echo "--- AUDIT: $label ($table) ---\n";
    $res = $conn->query("SELECT semester, academic_year, COUNT(*) as cnt, SUM(amount) as total FROM $table GROUP BY semester, academic_year ORDER BY academic_year DESC, semester ASC");
    if (!$res) {
        echo "Error: " . $conn->error . "\n";
        return;
    }
    printf("%-20s | %-15s | %-5s | %-15s\n", "Semester", "Acad Year", "Count", "Total (GHS)");
    echo str_repeat("-", 60) . "\n";
    while ($row = $res->fetch_assoc()) {
        printf("%-20s | %-15s | %-5d | %-15s\n", 
            $row['semester'] ?? 'NULL', 
            $row['academic_year'] ?? 'NULL', 
            $row['cnt'], 
            number_format($row['total'], 2)
        );
    }
    echo "\n";
}

function auditPayments($conn, $acad_year) {
    echo "--- GRANULAR COLLECTIONS AUDIT (Academic Year: $acad_year) ---\n";
    $sql = "SELECT payment_type, semester, COUNT(*) as cnt, SUM(amount) as total 
            FROM payments 
            WHERE academic_year = '$acad_year' 
            GROUP BY payment_type, semester";
    $res = $conn->query($sql);
    printf("%-15s | %-20s | %-5s | %-15s\n", "Type", "Semester", "Count", "Total (GHS)");
    echo str_repeat("-", 65) . "\n";
    while ($row = $res->fetch_assoc()) {
        printf("%-15s | %-20s | %-5d | %-15s\n", 
            $row['payment_type'] ?? 'NULL', 
            $row['semester'] ?? 'NULL', 
            $row['cnt'], 
            number_format($row['total'], 2)
        );
    }
    echo "\n";
}

function auditFees($conn, $acad_year) {
    echo "--- GRANULAR FEES AUDIT (Academic Year: $acad_year) ---\n";
    $sql = "SELECT semester, COUNT(*) as cnt, SUM(amount) as total 
            FROM student_fees 
            WHERE academic_year = '$acad_year' AND status != 'cancelled'
            GROUP BY semester";
    $res = $conn->query($sql);
    printf("%-20s | %-5s | %-15s\n", "Semester", "Count", "Total (GHS)");
    echo str_repeat("-", 45) . "\n";
    while ($row = $res->fetch_assoc()) {
        printf("%-20s | %-5d | %-15s\n", 
            $row['semester'] ?? 'NULL', 
            $row['cnt'], 
            number_format($row['total'], 2)
        );
    }
    echo "\n";
}

auditPayments($conn, $acad_year);
auditFees($conn, $acad_year);
auditTable($conn, 'expenses', 'EXPENDITURES');
?>
