<?php

function getSystemModules()
{
    return [
        [
            'title' => 'Administration',
            'icon' => 'fa-cogs',
            'description' => 'Manage system settings, user access, and school administration.',
            'primary_action' => ['label' => 'Dashboard', 'url' => 'administration/dashboard.php'],
            'secondary_action' => ['label' => 'View Students', 'url' => 'administration/students/view_students.php'],
            'links' => [
                ['label' => 'Add Student', 'url' => 'administration/students/add_student_form.php', 'icon' => 'fa-user-plus'],
                ['label' => 'Bulk Upload Students', 'url' => 'administration/students/bulk_upload_students.php', 'icon' => 'fa-file-upload'],
                ['label' => 'Migrate Academic Year', 'url' => 'administration/migrate_academic_year.php', 'icon' => 'fa-calendar'],
                ['label' => 'Register Admin', 'url' => 'administration/register_admin.php', 'icon' => 'fa-user-shield'],
                ['label' => 'Register User', 'url' => 'administration/register.php', 'icon' => 'fa-user-plus']
            ]
        ],
        [
            'title' => 'Finance & Billing',
            'icon' => 'fa-file-invoice-dollar',
            'description' => 'Manage fees, payments, invoices, expenses, and budgeting.',
            'primary_action' => ['label' => 'Dashboard', 'url' => 'finance/dashboard.php'],
            'secondary_action' => ['label' => 'Record Payment', 'url' => 'finance/payments/record_payment_form.php'],
            'links' => [
                ['label' => 'Add Fee', 'url' => 'finance/fees/add_fee_form.php', 'icon' => 'fa-plus'],
                ['label' => 'View Payments', 'url' => 'finance/payments/view_payments.php', 'icon' => 'fa-credit-card'],
                ['label' => 'View Expenses', 'url' => 'finance/expenses/view_expenses.php', 'icon' => 'fa-receipt'],
                ['label' => 'View Budgets', 'url' => 'finance/budgets/budgets.php', 'icon' => 'fa-sack-dollar'],
                ['label' => 'Term Invoices', 'url' => 'finance/invoices/term_invoice.php', 'icon' => 'fa-print'],
                ['label' => 'Reports', 'url' => 'finance/reports/report.php', 'icon' => 'fa-chart-line'],
                ['label' => 'Student Balances', 'url' => 'finance/reports/student_balances.php', 'icon' => 'fa-wallet']
            ]
        ],
        [
            'title' => 'Fees Management',
            'icon' => 'fa-tags',
            'description' => 'Configure, assign, and manage student fees.',
            'primary_action' => ['label' => 'View Fees', 'url' => 'finance/fees/view_fees.php'],
            'secondary_action' => ['label' => 'Add Fee', 'url' => 'finance/fees/add_fee_form.php'],
            'links' => [
                ['label' => 'Assign Fees', 'url' => 'finance/fees/assign_fee_form.php', 'icon' => 'fa-link'],
                ['label' => 'View Assigned Fees', 'url' => 'finance/fees/view_assigned_fees.php', 'icon' => 'fa-check'],
                ['label' => 'Fee Categories', 'url' => 'finance/fees/manage_fee_categories.php', 'icon' => 'fa-list']
            ]
        ],
        [
            'title' => 'Payments',
            'icon' => 'fa-credit-card',
            'description' => 'Record, track, and allocate student payments.',
            'primary_action' => ['label' => 'View Payments', 'url' => 'finance/payments/view_payments.php'],
            'secondary_action' => ['label' => 'Record Payment', 'url' => 'finance/payments/record_payment_form.php'],
            'links' => [
                ['label' => 'Reallocate Term Payments', 'url' => 'finance/payments/reallocate_term_payments.php', 'icon' => 'fa-shuffle'],
                ['label' => 'Payment Receipts', 'url' => 'finance/payments/receipt.php', 'icon' => 'fa-receipt']
            ]
        ],
        [
            'title' => 'Expenses & Budgeting',
            'icon' => 'fa-receipt',
            'description' => 'Track expenses and manage term and annual budgets.',
            'primary_action' => ['label' => 'View Expenses', 'url' => 'finance/expenses/view_expenses.php'],
            'secondary_action' => ['label' => 'Add Expense', 'url' => 'finance/expenses/add_expense_form.php'],
            'links' => [
                ['label' => 'View Budgets', 'url' => 'finance/budgets/budgets.php', 'icon' => 'fa-sack-dollar'],
                ['label' => 'Term Budget', 'url' => 'finance/budgets/term_budget.php', 'icon' => 'fa-calendar-days'],
                ['label' => 'Setup Term Budget', 'url' => 'finance/budgets/setup_term_budget.php', 'icon' => 'fa-sliders'],
                ['label' => 'Expense Categories', 'url' => 'finance/expenses/add_expense_category_form.php', 'icon' => 'fa-tags']
            ]
        ],
        [
            'title' => 'Billing & Invoices',
            'icon' => 'fa-file-invoice',
            'description' => 'Generate and manage term bills and invoices.',
            'primary_action' => ['label' => 'Generate Bills', 'url' => 'finance/invoices/generate_term_bills.php'],
            'secondary_action' => ['label' => 'View Term Bills', 'url' => 'finance/invoices/view_term_bills.php'],
            'links' => [
                ['label' => 'Term Invoices', 'url' => 'finance/invoices/term_invoice.php', 'icon' => 'fa-print'],
                ['label' => 'Bulk Term Billing', 'url' => 'finance/invoices/bulk_term_billing_form.php', 'icon' => 'fa-layer-group'],
                ['label' => 'Download Invoice', 'url' => 'finance/invoices/download_term_invoice.php', 'icon' => 'fa-download']
            ]
        ],
        [
            'title' => 'Reports & Analytics',
            'icon' => 'fa-chart-pie',
            'description' => 'Analyze collections, balances, and school finance performance.',
            'primary_action' => ['label' => 'Financial Reports', 'url' => 'finance/reports/report.php'],
            'secondary_action' => ['label' => 'Student Balances', 'url' => 'finance/reports/student_balances.php'],
            'links' => [
                ['label' => 'Student Percentage', 'url' => 'finance/reports/student_percentage.php', 'icon' => 'fa-percent'],
                ['label' => 'Download Balances', 'url' => 'finance/reports/download_student_balances.php', 'icon' => 'fa-download'],
                ['label' => 'Balance Details', 'url' => 'finance/reports/student_balance_details.php', 'icon' => 'fa-list']
            ]
        ],
        [
            'title' => 'Academics',
            'icon' => 'fa-book',
            'description' => 'Manage grades, attendance, subjects, and academic records.',
            'primary_action' => ['label' => 'Dashboard', 'url' => 'academics/dashboard.php'],
            'secondary_action' => ['label' => 'View Grades', 'url' => 'academics/grades.php'],
            'links' => [
                ['label' => 'Attendance', 'url' => 'academics/attendance.php', 'icon' => 'fa-calendar-check'],
                ['label' => 'Subjects', 'url' => 'academics/subjects.php', 'icon' => 'fa-list'],
                ['label' => 'Classes', 'url' => 'academics/classes.php', 'icon' => 'fa-chalkboard'],
                ['label' => 'Teacher Allocation', 'url' => 'academics/teacher_allocation.php', 'icon' => 'fa-users'],
                ['label' => 'Transcripts', 'url' => 'academics/transcripts.php', 'icon' => 'fa-file-alt'],
                ['label' => 'Academic Reports', 'url' => 'academics/report.php', 'icon' => 'fa-chart-line']
            ]
        ],
        [
            'title' => 'Communication',
            'icon' => 'fa-envelope',
            'description' => 'Send messages, announcements, and notifications to parents and staff.',
            'primary_action' => ['label' => 'Dashboard', 'url' => 'communication/dashboard.php'],
            'secondary_action' => ['label' => 'Send Message', 'url' => 'communication/send_message.php'],
            'links' => [
                ['label' => 'Announcements', 'url' => 'communication/announcements.php', 'icon' => 'fa-bullhorn'],
                ['label' => 'Messages', 'url' => 'communication/messages.php', 'icon' => 'fa-mail-bulk'],
                ['label' => 'Notifications', 'url' => 'communication/notifications.php', 'icon' => 'fa-bell']
            ]
        ]
    ];
}
