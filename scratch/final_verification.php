<?php
$c = new mysqli('localhost', 'root', 'root', 'Salba_acc');

echo "--- FINAL SYSTEM SETTINGS REALIGNMENT ---\n";

// Ensure current_semester is set correctly
$c->query("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES ('current_semester', 'Second Semester', 'Migration') ON DUPLICATE KEY UPDATE setting_value = 'Second Semester'");
$c->query("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES ('academic_year', '2025/2026', 'Migration') ON DUPLICATE KEY UPDATE setting_value = '2025/2026'");

// Delete old current_term if it exists
$c->query("DELETE FROM system_settings WHERE setting_key = 'current_term'");

echo "Settings aligned to Second Semester | 2025/2026\n";

// Final Verify
$rev = $c->query("SELECT SUM(amount) as total FROM payments WHERE semester = 'Second Semester' AND academic_year = '2025/2026'")->fetch_assoc()['total'];
$exp = $c->query("SELECT SUM(amount) as total FROM expenses WHERE semester = 'Second Semester' AND academic_year = '2025/2026'")->fetch_assoc()['total'];

echo "\n--- FINAL DASHBOARD RECONCILIATION ---\n";
echo "Revenue: GHS " . number_format($rev, 2) . "\n";
echo "Expenses: GHS " . number_format($exp, 2) . "\n";
echo "Net: GHS " . number_format($rev - $exp, 2) . "\n";

?>
