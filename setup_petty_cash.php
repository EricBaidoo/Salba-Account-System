<?php
// Enable error display for diagnostics
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Prevent mysqli from throwing exceptions to avoid 500 errors
mysqli_report(MYSQLI_REPORT_OFF);

include 'includes/db_connect.php';

echo "<div style='font-family: sans-serif; max-width: 600px; margin: 40px auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;'>";
echo "<h2>Petty Cash Setup Log</h2>";

// 1. Create table
$sql = "
CREATE TABLE IF NOT EXISTS `petty_cash_vouchers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `voucher_date` date NOT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `recipient` varchar(100) NOT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `recorded_by` varchar(50) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($sql);
if ($conn->error) {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error creating petty_cash_vouchers table: " . $conn->error . "</div>";
} else {
    echo "<div style='color: green; margin-bottom: 5px;'>✅ petty_cash_vouchers table created/verified.</div>";
}

// 2. Insert Account
$acct_chk = $conn->query("SELECT id FROM accounts WHERE account_code = '1010'");
if ($acct_chk) {
    if ($acct_chk->num_rows == 0) {
        $conn->query("INSERT INTO accounts (account_code, name, type, is_system) VALUES ('1010', 'Petty Cash', 'asset', 1)");
        if ($conn->error) {
            echo "<div style='color: red; margin-bottom: 5px;'>❌ Error inserting Petty Cash account: " . $conn->error . "</div>";
        } else {
            echo "<div style='color: green; margin-bottom: 5px;'>✅ Petty Cash account (1010) added to Chart of Accounts.</div>";
        }
    } else {
        echo "<div style='color: #777; margin-bottom: 5px;'>ℹ️ Account 1010 (Petty Cash) already exists in Chart of Accounts. Skipping.</div>";
    }
} else {
    echo "<div style='color: red; margin-bottom: 5px;'>❌ Error checking accounts table: " . $conn->error . " (Make sure setup_coa.php is run first)</div>";
}

echo "<hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>";
echo "<h3 style='color: green;'>Setup script completed execution.</h3>";
echo "</div>";
?>
