<?php
include 'includes/db_connect.php';

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
if ($conn->error) echo "Error creating table: " . $conn->error . "\n";
else echo "petty_cash_vouchers created.\n";

// 2. Insert Account
$acct_chk = $conn->query("SELECT id FROM accounts WHERE account_code = '1010'");
if ($acct_chk->num_rows == 0) {
    $conn->query("INSERT INTO accounts (account_code, name, type, is_system) VALUES ('1010', 'Petty Cash', 'asset', 1)");
    if ($conn->error) echo "Error inserting account: " . $conn->error . "\n";
    else echo "Petty Cash account added.\n";
} else {
    echo "Account 1010 already exists.\n";
}
?>
