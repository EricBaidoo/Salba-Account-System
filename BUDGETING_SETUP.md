# Budgeting Page Setup Guide

## Files Created

### Pages
1. **budgets.php** - Main budget dashboard showing overview and all budgets
2. **add_budget_form.php** - Form to create a new budget
3. **edit_budget_form.php** - Form to edit existing budget
4. **process_budget.php** - Backend handler for create/update operations
5. **delete_budget.php** - Budget deletion handler

### Includes
1. **budget_functions.php** - Helper functions for budget operations:
   - `getBudgetsByYear()` - Fetch budgets for a fiscal year
   - `getBudgetById()` - Get specific budget
   - `getBudgetSpent()` - Calculate spending against budget
   - `getBudgetStatus()` - Determine budget health status
   - `getAlertedBudgets()` - Get over-threshold budgets
   - `getBudgetSummary()` - Get budget statistics
   - `getVarianceReport()` - Generate variance analysis

### Database
1. **create_budgets_table.sql** - SQL migration to create budgets table

## Setup Instructions

### 1. Database Setup
Run the SQL migration to create the budgets table:
```sql
-- Execute in phpMyAdmin or MySQL CLI:
CREATE TABLE IF NOT EXISTS `budgets` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `category` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `amount` DECIMAL(10, 2) NOT NULL,
  `fiscal_year` VARCHAR(50) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `alert_threshold` INT(3) DEFAULT 80,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_fiscal_year` (`fiscal_year`),
  INDEX `idx_category` (`category`),
  INDEX `idx_start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. Navigation Menu Update
Add the following link to your main navigation/dashboard menu:
```html
<li class="nav-item">
    <a class="nav-link" href="pages/budgets.php">
        <i class="fas fa-chart-pie"></i> Budgets
    </a>
</li>
```

Or if using a dropdown menu structure:
```html
<li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="financeMenu" role="button" data-bs-toggle="dropdown">
        <i class="fas fa-chart-pie"></i> Budget & Finance
    </a>
    <ul class="dropdown-menu">
        <li><a class="dropdown-item" href="pages/budgets.php">Budgets</a></li>
        <li><a class="dropdown-item" href="pages/add_budget_form.php">Create Budget</a></li>
    </ul>
</li>
```

## Features

### Budget Management
- **Create Budgets** - Set up budgets for different categories
- **Edit Budgets** - Modify existing budget amounts and dates
- **Delete Budgets** - Remove budgets
- **Budget Tracking** - Automatic calculation of spending vs. budget

### Dashboard Metrics
- **Total Budgeted** - Sum of all budgets
- **Total Spent** - Current spending across all categories
- **Remaining** - Available budget remaining
- **Average Usage** - Overall budget utilization percentage

### Status Indicators
- **On Track** - 0-60% of budget spent
- **Warning** - 61-90% of budget spent
- **Over Budget** - 91%+ of budget spent

### Visualizations
- **Budget Distribution Chart** - Doughnut chart showing budget allocation
- **Spending vs Budget Chart** - Bar chart comparing budgeted vs. actual spending

### Alert System
- Configurable alert threshold (default: 80%)
- Automatic status warnings when thresholds are exceeded
- Visual indicators (status badges) for quick overview

## Usage

### Creating a Budget
1. Navigate to Budgets page
2. Click "CREATE BUDGET" button
3. Fill in:
   - Budget Category (from expense categories)
   - Description (optional)
   - Budgeted Amount
   - Start and End Dates
   - Alert Threshold (optional, default 80%)
4. Click "Create Budget"

### Monitoring Budgets
- Main budgets page shows:
  - All active budgets
  - Spending progress bars
  - Remaining balance
  - Budget status (On Track/Warning/Over Budget)

### Editing Budgets
1. Go to Budgets page
2. Click edit icon for a budget
3. Modify details as needed
4. Click "Update Budget"

### Generating Reports
- Print budget reports using the Print button
- Charts display budget distribution and spending patterns
- Use for presentations and financial analysis

## Customization

### Alert Thresholds
Change default alert threshold per budget. Options:
- Conservative: 60% (alert early)
- Standard: 80% (default)
- Aggressive: 90% (alert late)

### Budget Categories
Budgets use existing expense categories. To add new categories:
1. Go to "Manage Expense Categories"
2. Add new category
3. Create budget using the new category

### Date Ranges
Budgets can span any date range, useful for:
- Fiscal years (entire year)
- Terms (per academic term)
- Months (monthly budgets)
- Custom periods

## Notes
- Spending is calculated by matching the budget category with expense categories
- Expenses recorded within the budget date range are automatically included
- Budget amounts are tracked in GH₵ currency format
- All operations require authentication
