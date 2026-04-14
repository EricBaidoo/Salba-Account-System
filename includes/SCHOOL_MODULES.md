# School Management Modules (Folder Structure)

Pages are now physically grouped into module folders under `pages/`.

## 1) `pages/administration/`
- `dashboard.php`
- `login.php`
- `logout.php`
- `register.php`
- `register_admin.php`
- `system_settings.php`
- `migrate_academic_year.php`

## 2) `pages/students/`
- `add_student.php`
- `add_student_form.php`
- `edit_student_form.php`
- `update_student.php`
- `toggle_student_status.php`
- `view_students.php`
- `bulk_upload_students.php`


## 3) `pages/accounts/`
- Fees: `add_fee.php`, `add_fee_form.php`, `edit_fee.php`, `edit_fee_custom.php`, `manage_fee_categories.php`, `assign_fee.php`, `assign_fee_form.php`, `unassign_fee.php`, `view_assigned_fees.php`, `edit_student_fee.php`, `view_fees.php`
- Payments: `record_payment.php`, `record_payment_form.php`, `edit_payment_form.php`, `delete_payment.php`, `view_payments.php`, `reallocate_term_payments.php`
- Expenses: `add_expense.php`, `add_expense_form.php`, `edit_expense.php`, `delete_expense.php`, `view_expenses.php`, `add_expense_category_form.php`, `edit_expense_category_form.php`, `delete_expense_category.php`
- Budgets: `budgets.php`, `add_budget_form.php`, `process_budget.php`, `edit_budget_form.php`, `delete_budget.php`, `download_budget.php`
- `generate_term_bills.php`
- `process_term_bills.php`
- `bulk_term_billing_form.php`
- `bulk_term_billing.php`
- `view_term_bills.php`
- `term_budget.php`
- `setup_term_budget.php`
- `process_term_budget.php`
- `edit_term_budget.php`
- `term_invoice.php`
- `download_term_invoice.php`
- `receipt.php`
- `student_balances.php`
- `student_balance_details.php`
- `download_student_balances.php`
- `download_student_balances_pdf.php`

## 6) `pages/reports/`
- `report.php`
- `report_old.php`
- `student_percentage.php`

## Backward Compatibility
Root files such as `pages/view_payments.php` are now lightweight wrappers that `require` their new module location. This keeps existing links and bookmarks working while using a module-based folder layout.
