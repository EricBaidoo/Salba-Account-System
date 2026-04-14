# School Management System File Reorganization Script

$ErrorActionPreference = "Stop"
$moved = 0
$errors = 0

function Move-FileWithCheck {
    param([string]$Source, [string]$Destination)
    
    if (Test-Path $Source) {
        try {
            $dir = Split-Path -Parent $Destination
            if (-not (Test-Path $dir)) {
                New-Item -ItemType Directory -Path $dir -Force | Out-Null
            }
            Move-Item -Path $Source -Destination $Destination -Force
            Write-Host "[OK] $(Split-Path -Leaf $Source)" -ForegroundColor Green
            $script:moved++
        }
        catch {
            Write-Host "[ERROR] $(Split-Path -Leaf $Source): $_" -ForegroundColor Red
            $script:errors++
        }
    }
    else {
        Write-Host "[NOT FOUND] $Source" -ForegroundColor Yellow
    }
}

Write-Host "========================================"
Write-Host "REORGANIZING FILES TO MODULE STRUCTURE"
Write-Host "========================================"
Write-Host ""

Write-Host "CORE FILES" -ForegroundColor Yellow
Move-FileWithCheck "login.php" "core/login.php"
Move-FileWithCheck "logout.php" "core/logout.php"
Move-FileWithCheck "register.php" "core/register.php"
Move-FileWithCheck "register_admin.php" "core/register_admin.php"
Move-FileWithCheck "dashboard.php" "core/dashboard.php"

Write-Host ""
Write-Host "ADMINISTRATION - STUDENTS" -ForegroundColor Yellow
Move-FileWithCheck "add_student.php" "administration/students/add_student.php"
Move-FileWithCheck "add_student_form.php" "administration/students/add_student_form.php"
Move-FileWithCheck "edit_student_form.php" "administration/students/edit_student_form.php"
Move-FileWithCheck "update_student.php" "administration/students/update_student.php"
Move-FileWithCheck "toggle_student_status.php" "administration/students/toggle_student_status.php"
Move-FileWithCheck "bulk_upload_students.php" "administration/students/bulk_upload_students.php"
Move-FileWithCheck "view_students.php" "administration/students/view_students.php"
Move-FileWithCheck "edit_student_fee.php" "administration/students/edit_student_fee.php"

Write-Host ""
Write-Host "ADMINISTRATION - GENERAL" -ForegroundColor Yellow
Move-FileWithCheck "system_settings.php" "administration/system_settings.php"
Move-FileWithCheck "migrate_academic_year.php" "administration/migrate_academic_year.php"

Write-Host ""
Write-Host "FINANCE - FEES" -ForegroundColor Yellow
Move-FileWithCheck "add_fee.php" "finance/fees/add_fee.php"
Move-FileWithCheck "add_fee_form.php" "finance/fees/add_fee_form.php"
Move-FileWithCheck "edit_fee.php" "finance/fees/edit_fee.php"
Move-FileWithCheck "edit_fee_custom.php" "finance/fees/edit_fee_custom.php"
Move-FileWithCheck "manage_fee_categories.php" "finance/fees/manage_fee_categories.php"
Move-FileWithCheck "view_fees.php" "finance/fees/view_fees.php"
Move-FileWithCheck "assign_fee.php" "finance/fees/assign_fee.php"
Move-FileWithCheck "assign_fee_form.php" "finance/fees/assign_fee_form.php"
Move-FileWithCheck "unassign_fee.php" "finance/fees/unassign_fee.php"
Move-FileWithCheck "view_assigned_fees.php" "finance/fees/view_assigned_fees.php"

Write-Host ""
Write-Host "FINANCE - PAYMENTS" -ForegroundColor Yellow
Move-FileWithCheck "record_payment.php" "finance/payments/record_payment.php"
Move-FileWithCheck "record_payment_form.php" "finance/payments/record_payment_form.php"
Move-FileWithCheck "edit_payment_form.php" "finance/payments/edit_payment_form.php"
Move-FileWithCheck "delete_payment.php" "finance/payments/delete_payment.php"
Move-FileWithCheck "view_payments.php" "finance/payments/view_payments.php"
Move-FileWithCheck "reallocate_term_payments.php" "finance/payments/reallocate_term_payments.php"
Move-FileWithCheck "receipt.php" "finance/payments/receipt.php"

Write-Host ""
Write-Host "FINANCE - EXPENSES" -ForegroundColor Yellow
Move-FileWithCheck "add_expense.php" "finance/expenses/add_expense.php"
Move-FileWithCheck "add_expense_form.php" "finance/expenses/add_expense_form.php"
Move-FileWithCheck "add_expense_category_form.php" "finance/expenses/add_expense_category_form.php"
Move-FileWithCheck "edit_expense.php" "finance/expenses/edit_expense.php"
Move-FileWithCheck "edit_expense_category_form.php" "finance/expenses/edit_expense_category_form.php"
Move-FileWithCheck "delete_expense.php" "finance/expenses/delete_expense.php"
Move-FileWithCheck "delete_expense_category.php" "finance/expenses/delete_expense_category.php"
Move-FileWithCheck "view_expenses.php" "finance/expenses/view_expenses.php"

Write-Host ""
Write-Host "FINANCE - BUDGETS" -ForegroundColor Yellow
Move-FileWithCheck "add_budget_form.php" "finance/budgets/add_budget_form.php"
Move-FileWithCheck "budgets.php" "finance/budgets/budgets.php"
Move-FileWithCheck "delete_budget.php" "finance/budgets/delete_budget.php"
Move-FileWithCheck "download_budget.php" "finance/budgets/download_budget.php"
Move-FileWithCheck "edit_budget_form.php" "finance/budgets/edit_budget_form.php"
Move-FileWithCheck "process_budget.php" "finance/budgets/process_budget.php"
Move-FileWithCheck "setup_term_budget.php" "finance/budgets/setup_term_budget.php"
Move-FileWithCheck "edit_term_budget.php" "finance/budgets/edit_term_budget.php"
Move-FileWithCheck "term_budget.php" "finance/budgets/term_budget.php"

Write-Host ""
Write-Host "FINANCE - INVOICES" -ForegroundColor Yellow
Move-FileWithCheck "download_term_invoice.php" "finance/invoices/download_term_invoice.php"
Move-FileWithCheck "term_invoice.php" "finance/invoices/term_invoice.php"
Move-FileWithCheck "bulk_term_billing.php" "finance/invoices/bulk_term_billing.php"
Move-FileWithCheck "bulk_term_billing_form.php" "finance/invoices/bulk_term_billing_form.php"
Move-FileWithCheck "generate_term_bills.php" "finance/invoices/generate_term_bills.php"
Move-FileWithCheck "process_term_bills.php" "finance/invoices/process_term_bills.php"
Move-FileWithCheck "view_term_bills.php" "finance/invoices/view_term_bills.php"

Write-Host ""
Write-Host "FINANCE - REPORTS" -ForegroundColor Yellow
Move-FileWithCheck "report.php" "finance/reports/report.php"
Move-FileWithCheck "report_old.php" "finance/reports/report_old.php"
Move-FileWithCheck "student_balances.php" "finance/reports/student_balances.php"
Move-FileWithCheck "student_balance_details.php" "finance/reports/student_balance_details.php"
Move-FileWithCheck "student_percentage.php" "finance/reports/student_percentage.php"
Move-FileWithCheck "download_student_balances.php" "finance/reports/download_student_balances.php"
Move-FileWithCheck "download_student_balances_pdf.php" "finance/reports/download_student_balances_pdf.php"

Write-Host ""
Write-Host "========================================"
Write-Host "REORGANIZATION COMPLETE"
Write-Host "Files moved: $moved"
Write-Host "Errors: $errors"
Write-Host "========================================"
