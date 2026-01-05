# Budget System - Quick Start Guide

## Accessing the Budget System

### From Dashboard
1. Click on the budget-related link/button on your dashboard
2. You'll be taken to **Budget Breakdown** page

### From Report Page
1. Go to Reports
2. Click "View Budget Breakdown" button

## Main Features & How to Use

### 1. Viewing a Budget

**Step 1:** Budget Breakdown Page
- Default shows current term's budget
- Use the **"Select Term"** dropdown to view other terms
- Budget shows side-by-side: INCOME (left) and EXPENSES (right)

**View Includes:**
- Income sources with budgeted vs actual collected
- Expense categories with budgeted vs actual spent
- Visual charts showing distribution and variance
- Summary totals and net position

### 2. Variance Alerts

**Yellow Alert Box** appears when:
- ❌ Any expense category is OVER BUDGET (shows % overage)
- ❌ Any fee type collected LESS than expected (shows % shortfall)

**What to Do:**
- Click on alerts to understand the variance
- Use this to adjust spending or investigate collection gaps
- Alerts automatically refresh when you change terms

### 3. Creating/Editing a Budget

**Step 1:** Click the **"EDIT BUDGET"** button
- Takes you to Budget Setup page
- Shows all fee types for income
- Shows all expense categories for expenses

**Step 2:** Enter Budget Amounts
- Type amount for each income source (what you expect to collect)
- Type amount for each expense category (what you plan to spend)
- Real-time totals show surplus/deficit

**Step 3:** Add Notes (Optional)
- Small text field under each line item
- Good for explaining assumptions or constraints
- Example: "Abacus fee assumes 80% enrollment"

**Step 4:** Save
- Click **"SAVE BUDGET"** button
- Redirected back to Budget Breakdown with success message
- Budget is now live for that term

### 4. Using Budget Templates

**Scenario:** Next term starts and you want to use similar budget to this term

**Step 1:** Click **"TEMPLATES"** button on Budget Breakdown
- Shows list of all previous budgets
- Displays income/expense totals for each

**Step 2:** Select Budget to Copy
- Click **"Copy"** button on the budget you want to replicate
- Modal dialog appears asking which term to copy to
- Select target term and academic year

**Step 3:** Confirm
- Click **"Copy Budget"** button
- All budget items and notes are copied to new term
- Automatically redirects to new budget's breakdown page

### 5. Comparing Budget History

**Scenario:** Want to see how expenses changed from last term

**Step 1:** Click **"HISTORY"** button on Budget Breakdown
- Takes you to Budget History & Comparison page

**Step 2:** Select Comparison Terms
- Choose which term to compare
- Choose academic year
- Click **"Compare"** button

**Step 3:** Review Comparison
- **Chart:** Visual side-by-side bar chart of all categories
- **Summary:** Shows total change and percentage
- **Table:** Detailed line-by-line comparison
  - Each row shows current vs previous amount
  - Shows absolute difference in Ksh
  - Shows percentage change (+ for increase, - for decrease)
  - Color badges indicate trend

**Interpretation:**
- **Red Badge (+X%):** Expense category increased
- **Green Badge (-X%):** Expense category decreased
- Use this to identify spending trends

### 6. Printing Budget

**Step 1:** Click **"PRINT"** button on Budget Breakdown
- Opens browser print dialog
- Charts and buttons hidden automatically
- Budget formatted for clean printing

**Step 2:** Print or Save as PDF
- Select printer and print
- OR select "Save as PDF" to create PDF file
- Use for archival or sharing

### 7. Exporting to PDF

**Step 1:** Click **"PDF"** button on Budget Breakdown
- Generates professional PDF report
- Includes all budget data and variance information

**Step 2:** Save File
- Browser automatically downloads as `Budget_[Term]_[Year].pdf`
- File ready for email, storage, or printing

**PDF Includes:**
- Budget header with term and date
- Income sources with budgeted vs actual
- Expense categories with budgeted vs actual
- Variance analysis
- All notes on budget items

## Understanding the Charts

### 1. Income Distribution (Doughnut Chart)
- Shows how much each fee type contributes to total budgeted income
- Useful for understanding revenue mix
- Example: Tuition 60%, Abacus 40%

### 2. Expense Distribution (Doughnut Chart)
- Shows how much each expense category takes from total budget
- Useful for understanding spending priorities
- Example: Staff 50%, Supplies 30%, Utilities 20%

### 3. Income: Budgeted vs Actual (Bar Chart)
- Each fee type shown as two bars
- Blue bar = Budgeted amount
- Teal bar = Actually collected
- If teal bar < blue bar = Under-collected

### 4. Expenses: Budgeted vs Actual (Bar Chart)
- Each expense category shown as two bars
- Orange bar = Budgeted amount
- Red bar = Actually spent
- If red bar > orange bar = Over budget

## Troubleshooting

### Problem: Variance Alerts Don't Show
**Solution:** Variance alerts only appear when actual differs from budget by more than 0%
- For expenses: Alert appears when actual > budgeted
- For income: Alert appears when actual < budgeted

### Problem: Charts Show No Data
**Solution:** Ensure you've:
1. Set up a budget (income and expense amounts)
2. Saved the budget
3. Refreshed the page

### Problem: PDF Export Blank
**Solution:** 
1. Check that you have entered budget data
2. Ensure mPDF library is installed (should be in vendor folder)
3. Check file permissions on server

### Problem: Template Copy Shows Error
**Solution:**
- Cannot copy to same term and year (creates duplicate)
- Must select a different term or academic year
- Ensure target budget doesn't already exist

### Problem: Can't See Other Terms
**Solution:**
- Term dropdown only shows terms that have budgets created
- Create a new budget for a term first
- Then term appears in selection

## Common Tasks

### Task: Create Budget for New Term
1. Click "EDIT BUDGET"
2. Enter income and expense amounts
3. Add notes if needed
4. Click "SAVE BUDGET"
5. Budget now appears in Budget Breakdown

### Task: Quick Budget from Template
1. Go to Budget Breakdown
2. Click "TEMPLATES"
3. Find previous similar term
4. Click "Copy"
5. Select new term
6. Done! Budget copied automatically

### Task: Check Spending Against Budget
1. Go to Budget Breakdown
2. Select the term you want to check
3. Look at variance columns
4. Check variance alerts (yellow box)
5. Use History to see trends

### Task: Share Budget Report
1. Go to Budget Breakdown for desired term
2. Click "PDF" button
3. Send PDF file to stakeholders
4. OR Click "PRINT" to print copies

### Task: Update Budget Mid-Term
1. Go to Budget Breakdown
2. Click "EDIT BUDGET"
3. Update amounts or notes
4. Click "SAVE BUDGET"
5. Changes take effect immediately

## Key Metrics to Monitor

### 1. Expense Variance
- **Formula:** (Actual - Budgeted) / Budgeted × 100%
- **Good:** -20% to 0% (under budget)
- **Alert:** 0% to 20% (slightly over)
- **Warning:** >20% (significantly over)

### 2. Income Variance
- **Formula:** (Actual - Budgeted) / Budgeted × 100%
- **Good:** 0% to 20% (meeting or exceeding target)
- **Alert:** -20% to 0% (slightly under)
- **Warning:** <-20% (significantly under target)

### 3. Net Position
- **Budget Net:** Total Budgeted Income - Total Budgeted Expenses
- **Actual Net:** Total Actual Income - Total Actual Expenses
- **Positive = Surplus** (more money in than out)
- **Negative = Deficit** (more money out than in)

## Best Practices

1. **Review Monthly:** Check actual spending vs budget regularly
2. **Investigate Variances:** Understand why amounts differ from budget
3. **Use Notes:** Add context to budget items for future reference
4. **Compare Trends:** Use History to identify spending patterns
5. **Plan Early:** Use Templates to quickly set up next term's budget
6. **Share Reports:** Export PDF for stakeholder communication
7. **Adjust as Needed:** Update budget mid-term if circumstances change
8. **Archive Budgets:** Keep old budgets for historical reference

## Important Reminders

✅ **Budget income is automatically calculated** from fee assignments (no manual entry)
- Budget shows what you expect to collect based on students enrolled
- Actual shows what was actually collected

✅ **Budget expenses are manually entered**
- You decide how much to allocate for each category
- Actual spending pulls from expense records

✅ **All data is term-specific**
- Budgets are created per term per academic year
- Income/expenses filtered by term dates
- Comparisons are term-to-term only

✅ **Notes are optional but valuable**
- Use for explaining assumptions
- Helps next person understand your budget logic
- Included in PDF exports

---

**Need Help?** Contact your system administrator for additional support or customization.
