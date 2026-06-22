<?php
// Enable error display for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions to avoid 500 errors
mysqli_report(MYSQLI_REPORT_OFF);

require 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>Chart of Accounts Setup Log</h2>";

$sql_accounts = "CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    is_system BOOLEAN DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql_je = "CREATE TABLE IF NOT EXISTS journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    reference_type VARCHAR(50) NOT NULL,
    reference_id INT NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sql_lines = "CREATE TABLE IF NOT EXISTS journal_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(12,2) DEFAULT 0.00,
    credit DECIMAL(12,2) DEFAULT 0.00,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id)
)";

$conn->query($sql_accounts);
if ($conn->error) {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error creating accounts table: " . $conn->error . "</div>";
} else {
    echo "<div style='color: green; margin-bottom: 5px;'>✅ Accounts table created/verified.</div>";
}

$conn->query($sql_je);
if ($conn->error) {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error creating journal_entries table: " . $conn->error . "</div>";
} else {
    echo "<div style='color: green; margin-bottom: 5px;'>✅ Journal Entries table created/verified.</div>";
}

$conn->query($sql_lines);
if ($conn->error) {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error creating journal_lines table: " . $conn->error . "</div>";
} else {
    echo "<div style='color: green; margin-bottom: 5px;'>✅ Journal Lines table created/verified.</div>";
}

// Insert Default Accounts
$defaults = [
    ['1000', 'Cash on Hand', 'asset', 1],
    ['1010', 'Bank Account', 'asset', 1],
    ['1200', 'Accounts Receivable (Students)', 'asset', 1],
    ['2000', 'Accounts Payable', 'liability', 1],
    ['3000', 'Retained Earnings', 'equity', 1],
    ['4000', 'Tuition & Fee Revenue', 'revenue', 1],
    ['4100', 'Miscellaneous Revenue', 'revenue', 1],
    ['5000', 'Salary & Payroll Expense', 'expense', 1],
    ['5100', 'Scholarship & Waiver Expense', 'expense', 1],
    ['5200', 'General Operational Expense', 'expense', 1]
];

$stmt = $conn->prepare("INSERT IGNORE INTO accounts (account_code, name, type, is_system) VALUES (?, ?, ?, ?)");
if ($stmt) {
    foreach ($defaults as $acc) {
        $stmt->bind_param("sssi", $acc[0], $acc[1], $acc[2], $acc[3]);
        $stmt->execute();
    }
    $stmt->close();
    echo "<div style='color: green; margin-bottom: 5px;'>✅ Default Chart of Accounts populated.</div>";
} else {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error preparing defaults statement: " . $conn->error . "</div>";
}

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";
echo "<h3 style='color: green;'>Setup script completed execution.</h3>";
echo "</div>";
