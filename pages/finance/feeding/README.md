# Daily/Weekly Feeding Tracker System

## Overview

This module enables tracking of students who pay for feeding **separately** from their main bill, rather than as part of the regular fees. This is commonly used when parents prefer to pay **daily, weekly, or monthly** for meals instead of including it in the annual/semester bill.

## How It Works

### Standard Process
1. **All students** automatically have feeding included in their bill
2. When a parent wants to pay separately:
   - Admin **deletes** the feeding line item from the student's fee bill
   - The student is then **enrolled** in the Daily/Weekly Feeding Tracker
3. Student now pays **daily/weekly/monthly** instead of with regular fees

## Database Tables

### 1. `student_daily_weekly_feeding`
Tracks students enrolled in daily/weekly/monthly feeding payment plans.

**Fields:**
- `id` - Primary key
- `student_id` - Reference to student
- `class_id` - Current class
- `plan_type` - 'daily', 'weekly', or 'monthly'
- `amount_per_unit` - Amount charged per day/week/month (GHS)
- `academic_year` - Academic year (e.g., '2025-2026')
- `semester` - Semester (First/Second/Third Semester)
- `status` - 'active', 'suspended', or 'completed'
- `deleted_from_bill_date` - When feeding was removed from main bill
- `started_date` - Enrollment date
- `ended_date` - When plan was ended (if applicable)
- `notes` - Additional notes

**Key Constraints:**
- One active plan per student per semester
- Foreign key to `students` and `classes`

### 2. `feeding_payments`
Records individual daily/weekly/monthly feeding payments.

**Fields:**
- `id` - Primary key
- `student_id` - Reference to student
- `student_feeding_plan_id` - Reference to the plan
- `payment_date` - Date of payment
- `payment_type` - 'daily', 'weekly', or 'monthly'
- `amount` - Amount paid (GHS)
- `payment_method` - 'cash', 'check', 'transfer', 'momo'
- `recorded_by` - User who recorded payment
- `notes` - Additional notes

**Constraints:**
- Unique constraint: one payment per student per date per type
- Foreign keys to `students`, `student_daily_weekly_feeding`, and `users`

### 3. `feeding_payment_summary` (Optional)
Aggregated view for quick reporting and balance calculations.

**Fields:**
- Precomputed totals per student per semester
- Automatically updated
- Useful for quick queries and reports

## Pages & Features

### 1. Daily/Weekly Feeding Tracker
**File:** `daily_weekly_tracker.php`

Main dashboard showing all students on feeding plans.

**Features:**
- List all students enrolled in daily/weekly feeding
- Show payment frequency (daily/weekly/monthly)
- Display amount per unit
- Show number of payments made and total paid
- Day closeout with register lock/reopen controls
- Quick links to enroll and record payments

### 2. Enroll Student
**File:** `enroll_daily_feeding.php`

Enroll a student in daily/weekly feeding plan.

**Steps:**
1. Select student
2. Choose payment frequency (daily/weekly/monthly)
3. Set amount per unit (GHS)
4. Add optional notes
5. Enroll

**What happens:**
- Student record created in `student_daily_weekly_feeding`
- Status set to 'active'
- Current semester/year automatically set

### 3. Record Feeding Payment
**File:** `record_feeding_payment.php`

Record individual feeding payments from students.

**Steps:**
1. Select student
2. Set payment date
3. Choose payment type (daily/weekly/monthly)
4. Enter amount paid
5. Select payment method (cash/check/transfer/momo)
6. Add optional notes
7. Record

**What happens:**
- Payment inserted into `feeding_payments`
- Unique constraint prevents duplicate payments for same student/date/type
- Plan information auto-populates from student's active plan

### 4. Feeding Summary Report
**File:** `feeding_summary_report.php`

Comprehensive view of all feeding payments and student statuses.

**Includes:**
- **Statistics:**
  - Total students on feeding plans
  - Breakdown by plan type (daily/weekly/monthly)
  - Total collected
  
- **Recent Payments:** Last 20 payments recorded
  
- **Student Status:** All students with:
  - Plan type
  - Amount per unit
  - Payment count
  - Total paid
  - Start date

## SQL Migration

To set up the tables, run:

```sql
-- Run this file to create all tables:
sql/create_daily_weekly_feeding_tables.sql
```

Or manually execute the CREATE TABLE statements in a database client.

## Integration with Finance Module

This system is integrated into the Finance module at:
- **Path:** `pages/finance/feeding/`
- **Accessible from:** Finance Dashboard
- **Requires:** Finance access permissions

## Workflow Example

### Scenario: Parent wants to pay daily for feeding

1. **Setup Phase:**
   - Admin goes to Finance → Feeding Tracker
   - Clicks "Enroll Student"
   - Selects student (e.g., "John Doe")
   - Selects "Daily" plan
   - Sets amount: GHS 5.00 per day
   - Enrolls student

2. **Payment Recording:**
   - Each day, admin goes to "Record Payment"
   - Selects John Doe
   - Sets today's date
   - Enters GHS 5.00
   - Records payment

3. **Reporting:**
   - Admin checks "Feeding Summary Report"
   - Can see:
     - John has paid 15 times (15 days)
     - Total paid: GHS 75.00
     - Latest payment: Today

4. **Reconciliation:**
   - Compare daily feeding collected vs. regular fees
   - View payment methods
   - Track student compliance
   - Run day closeout to lock the class/date register after verification

## Reports & Queries

### Common Queries

**1. Find students owing money:**
```sql
SELECT s.first_name, s.last_name, 
       (dwf.amount_per_unit * 20) - COALESCE(SUM(fp.amount), 0) as balance
FROM student_daily_weekly_feeding dwf
JOIN students s ON dwf.student_id = s.id
LEFT JOIN feeding_payments fp ON dwf.id = fp.student_feeding_plan_id
WHERE dwf.status = 'active'
GROUP BY dwf.id
HAVING balance > 0;
```

**2. Total daily feeding collected today:**
```sql
SELECT SUM(amount) as daily_total
FROM feeding_payments
WHERE payment_date = CURDATE()
AND payment_type = 'daily';
```

**3. Weekly payment summary:**
```sql
SELECT 
    WEEK(payment_date) as week,
    SUM(amount) as total,
    COUNT(*) as num_payments
FROM feeding_payments
WHERE payment_type = 'weekly'
AND YEAR(payment_date) = YEAR(CURDATE())
GROUP BY WEEK(payment_date);
```

## Important Notes

1. **Student Deletion:** When enrolling a student:
   - Manually remove the feeding fee from their regular bill first
   - Then enroll them in the daily/weekly tracker

2. **Payment Duplicate Prevention:**
   - System prevents recording the same payment twice (same student/date/type)
   - Error message will appear

3. **Semester Changes:**
   - When a new semester starts, old plans are preserved
   - Students must be re-enrolled if continuing daily feeding in new semester

4. **Data Integrity:**
   - All payments cascade delete when student is deleted
   - Plan data preserved for historical reporting
   - Audit trail via `created_at` and `recorded_by` fields

## Troubleshooting

### Issue: Can't find student to enroll
**Solution:** Only active students not already on a feeding plan appear. Check:
- Student is marked as "active" status
- Student is not already enrolled in daily/weekly feeding for this semester

### Issue: Duplicate payment error
**Solution:** This student already has a payment recorded for this date/type.
- Check "Feeding Summary Report" for recent payments
- If error is incorrect, manually delete duplicate from database

### Issue: Fields not populating when selecting student
**Solution:** JavaScript may not have loaded. Refresh the page or check browser console.

## Future Enhancements

Potential improvements:
- Automatic payment reminders
- Integration with SMS/Email notifications
- Bulk import from payment files
- Attendance-based feeding charges
- Customizable payment frequencies
- Export to Excel/PDF reports
- Parent mobile app integration

---

**Last Updated:** 2026-07-01
**Module:** Finance - Daily/Weekly Feeding Tracker
**Version:** 1.0
