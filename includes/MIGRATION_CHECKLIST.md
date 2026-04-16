**Database Migration Checklist**
- **Goal:** Align schema with semester+year scoping, arrears carry-forward, and invoice/PDF logic.

**Critical Columns**
- **`student_fees.semester`**: canonical values: First/Second/Third Semester.
- **`student_fees.academic_year`**: format YYYY/YYYY (e.g., 2024/2025); nullable allowed.
- **`student_fees.amount_paid`**: DECIMAL, defaults to 0.00.
- **`student_fees.status`**: pending/paid/cancelled.
- **`payments.semester`** and **`payments.academic_year`**: same formats; nullable allowed.
- **`fees.name`**: must include “Outstanding Balance”.

**Verification Queries**
- Columns present:
  - `SHOW COLUMNS FROM student_fees LIKE 'amount_paid';`
  - `SHOW COLUMNS FROM student_fees LIKE 'academic_year';`
  - `SHOW COLUMNS FROM student_fees LIKE 'semester';`
  - `SHOW COLUMNS FROM student_fees LIKE 'status';`
  - `SHOW COLUMNS FROM payments LIKE 'academic_year';`
  - `SHOW COLUMNS FROM payments LIKE 'semester';`
- Fee exists:
  - `SELECT id, name FROM fees WHERE name IN ('Outstanding Balance','Arrears Carry Forward');`
- Semester/year normalization sample:
  - `UPDATE student_fees SET semester = 'First Semester' WHERE semester IN ('1st Semester','First');`
  - `UPDATE student_fees SET semester = 'Second Semester' WHERE semester IN ('2nd Semester','Second');`
  - `UPDATE student_fees SET semester = 'Third Semester' WHERE semester IN ('3rd Semester','Third');`
- Academic year backfill (example):
  - `UPDATE payments SET academic_year = '2024/2025' WHERE academic_year IS NULL AND payment_date BETWEEN '2024-09-01' AND '2025-08-31';`
  - `UPDATE student_fees SET academic_year = '2024/2025' WHERE academic_year IS NULL AND assigned_date BETWEEN '2024-09-01' AND '2025-08-31';`

**Indexes**
- Add helpful indexes for filters:
  - `ALTER TABLE student_fees ADD INDEX idx_sf_scope (student_id, semester, academic_year, status);`
  - `ALTER TABLE payments ADD INDEX idx_pay_scope (student_id, semester, academic_year);`

**Arrears Fee Setup**
- Ensure fee:
  - If “Arrears Carry Forward” exists, rename it to “Outstanding Balance”.
  - Otherwise insert new “Outstanding Balance” with `amount=0`, `fee_type='fixed'`.

**Foreign Keys (optional)**
- Recommended if using InnoDB:
  - `student_fees.student_id` → `students.id`
  - `student_fees.fee_id` → `fees.id`
  - `payments.student_id` → `students.id`

**Data Hygiene**
- Set `amount_paid` to 0.00 where NULL:
  - `UPDATE student_fees SET amount_paid = 0.00 WHERE amount_paid IS NULL;`
- Normalize `status` values to `pending` or `paid`; mark removed as `cancelled`.

**Post-Migration Validation**
- Pick 3 students across classes; for each semester-year:
  - Assigned fees total equals on-screen invoice total.
  - Payments in that semester-year match the Payment History.
  - Previous semester snapshot arrears equals the “Outstanding Balance” auto-assignment.
  - PDF totals match invoice totals.

**Rollback Plan**
- Before running changes, export current schema and data:
  - `mysqldump -u user -p dbname > backup_before_migration.sql`
- Keep a list of updated tables to revert if needed.
