<?php
require 'includes/db_connect.php';

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
if ($conn->error) echo "Error accounts: " . $conn->error . "<br>";
$conn->query($sql_je);
if ($conn->error) echo "Error je: " . $conn->error . "<br>";
$conn->query($sql_lines);
if ($conn->error) echo "Error jl: " . $conn->error . "<br>";

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
foreach ($defaults as $acc) {
    $stmt->bind_param("sssi", $acc[0], $acc[1], $acc[2], $acc[3]);
    $stmt->execute();
}
$stmt->close();

echo "Chart of Accounts tables and defaults created successfully.";
