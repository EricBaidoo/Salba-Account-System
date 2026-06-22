<?php
require 'includes/db_connect.php';

$conn->query("TRUNCATE TABLE payroll_records");
$conn->query("TRUNCATE TABLE payroll_runs");
$conn->query("TRUNCATE TABLE staff_salary_structures");

// Alter staff_salary_structures
$conn->query("ALTER TABLE staff_salary_structures DROP COLUMN allowances");
$conn->query("ALTER TABLE staff_salary_structures DROP COLUMN deductions");
$conn->query("ALTER TABLE staff_salary_structures DROP COLUMN deduction_reason");
$conn->query("ALTER TABLE staff_salary_structures DROP COLUMN income_tax");
$conn->query("ALTER TABLE staff_salary_structures ADD COLUMN custom_allowances TEXT DEFAULT NULL AFTER base_salary");
$conn->query("ALTER TABLE staff_salary_structures ADD COLUMN custom_deductions TEXT DEFAULT NULL AFTER custom_allowances");

// Alter payroll_records
$conn->query("ALTER TABLE payroll_records DROP COLUMN allowances");
$conn->query("ALTER TABLE payroll_records DROP COLUMN deductions");
$conn->query("ALTER TABLE payroll_records DROP COLUMN deduction_reason");
$conn->query("ALTER TABLE payroll_records DROP COLUMN income_tax");
$conn->query("ALTER TABLE payroll_records ADD COLUMN custom_allowances TEXT DEFAULT NULL AFTER base_salary");
$conn->query("ALTER TABLE payroll_records ADD COLUMN custom_deductions TEXT DEFAULT NULL AFTER custom_allowances");
$conn->query("ALTER TABLE payroll_records ADD COLUMN global_taxes TEXT DEFAULT NULL AFTER custom_deductions");

echo "Database upgraded successfully.";
