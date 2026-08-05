<?php
/**
 * Core Waiver & Scholarship Engine
 * Computes, applies, and caps waiver amounts dynamically to prevent over-discounting.
 */

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/accounting_engine.php';

if (!function_exists('apply_student_waivers')) {
    function apply_student_waivers($conn, $student_id, $semester, $academic_year) {
        $applied_count = 0;
        
        // 1. Get or Create the 'Waivers & Scholarships' fee row
        $waiver_fee_stmt = $conn->query("SELECT id FROM fees WHERE name = 'Waivers & Scholarships' LIMIT 1");
        if ($waiver_fee_stmt && $waiver_fee_stmt->num_rows > 0) {
            $waiver_fee_id = $waiver_fee_stmt->fetch_assoc()['id'];
        } else {
            $conn->query("INSERT INTO fees (name, amount, fee_type, description) VALUES ('Waivers & Scholarships', 0.00, 'fixed', 'System row for scholarship/waiver entries')");
            $waiver_fee_id = $conn->insert_id;
        }

        // 2. Get active scholarships for the student
        $schols_stmt = $conn->prepare("
            SELECT s.id, s.name, s.discount_type, s.discount_value, s.applies_to_fees
            FROM student_scholarships ss 
            JOIN scholarships s ON ss.scholarship_id = s.id 
            WHERE ss.student_id = ? AND ss.status = 'active'
        ");
        $schols_stmt->bind_param("i", $student_id);
        $schols_stmt->execute();
        $schols = $schols_stmt->get_result();
        
        while ($schol = $schols->fetch_assoc()) {
            $schol_name_esc = $conn->real_escape_string($schol['name']);
            $target_fees = json_decode($schol['applies_to_fees'] ?? '[]', true) ?: [];
            
            // 3. Compute Target Amount (Sum of target fees billed this semester)
            $target_amount = 0;
            if (empty($target_fees)) {
                // All fees EXCEPT the waiver fee itself
                $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as t FROM student_fees WHERE student_id = ? AND semester = ? AND academic_year = ? AND fee_id != ? AND amount > 0 AND status != 'cancelled'");
                $sum_stmt->bind_param("issi", $student_id, $semester, $academic_year, $waiver_fee_id);
            } else {
                // Specific targeted fees
                $fids = implode(',', array_map('intval', $target_fees));
                if (empty($fids)) { $target_amount = 0; continue; }
                $sum_stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) as t FROM student_fees WHERE student_id = ? AND semester = ? AND academic_year = ? AND fee_id IN ($fids) AND amount > 0 AND status != 'cancelled'");
                $sum_stmt->bind_param("iss", $student_id, $semester, $academic_year);
            }
            
            if (isset($sum_stmt)) {
                $sum_stmt->execute();
                $target_amount = (float)$sum_stmt->get_result()->fetch_assoc()['t'];
                $sum_stmt->close();
            }

            // 4. Calculate Expected Discount (Positive Number)
            $expected_discount = 0;
            if ($target_amount > 0) {
                if ($schol['discount_type'] === 'percentage') {
                    $expected_discount = $target_amount * ($schol['discount_value'] / 100);
                } else {
                    $expected_discount = min($target_amount, (float)$schol['discount_value']);
                }
            }
            
            // 5. Check Already Granted Discount (Sum of negative amounts)
            // We search for rows where the note contains the scholarship name
            $note_like = "%{$schol_name_esc}%";
            $granted_stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as t FROM student_fees WHERE student_id = ? AND fee_id = ? AND semester = ? AND academic_year = ? AND notes LIKE ? AND status != 'cancelled'");
            $granted_stmt->bind_param("iisss", $student_id, $waiver_fee_id, $semester, $academic_year, $note_like);
            $granted_stmt->execute();
            $already_granted = abs((float)$granted_stmt->get_result()->fetch_assoc()['t']);
            $granted_stmt->close();
            
            // 6. Calculate Delta
            // We round to 2 decimals to prevent floating point mismatch
            $delta = round($expected_discount - $already_granted, 2);
            
            if (abs($delta) > 0.00) {
                // Insert adjustment row
                $note = "Waiver Applied: {$schol['name']}";
                $due_date = date('Y-m-d');
                $amount_to_insert = 0;
                $is_reversal = false;
                
                if ($delta > 0) {
                    // We owe them more discount
                    $amount_to_insert = -1 * $delta;
                } else {
                    // We over-discounted them (Delta < 0). Insert positive row to charge back.
                    $amount_to_insert = abs($delta);
                    $note = "Waiver Adjustment: {$schol['name']} (Reversal)";
                    $is_reversal = true;
                }
                
                $insert_stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_id, due_date, amount, amount_paid, semester, academic_year, notes, assigned_date, status) VALUES (?, ?, ?, ?, 0, ?, ?, ?, NOW(), 'paid')");
                $insert_stmt->bind_param("iisdsss", $student_id, $waiver_fee_id, $due_date, $amount_to_insert, $semester, $academic_year, $note);
                
                if ($insert_stmt->execute()) {
                    $discount_id = $conn->insert_id;
                    $applied_count++;
                    
                    // Journal Entry
                    if (!$is_reversal) {
                        // DR Scholarship Expense (5100), CR Accounts Receivable (1200)
                        record_journal_entry($conn, date('Y-m-d'), 'Waiver', $discount_id, "Waiver for Student #{$student_id} ($semester)", [
                            ['account_code' => '5100', 'debit' => abs($amount_to_insert), 'credit' => 0],
                            ['account_code' => '1200', 'debit' => 0, 'credit' => abs($amount_to_insert)]
                        ]);
                    } else {
                        // REVERSAL: DR Accounts Receivable (1200), CR Scholarship Expense (5100)
                        record_journal_entry($conn, date('Y-m-d'), 'WaiverReversal', $discount_id, "Waiver Reversal for Student #{$student_id} ($semester)", [
                            ['account_code' => '1200', 'debit' => abs($amount_to_insert), 'credit' => 0],
                            ['account_code' => '5100', 'debit' => 0, 'credit' => abs($amount_to_insert)]
                        ]);
                    }
                }
                $insert_stmt->close();
            }
        }
        $schols_stmt->close();
        
        return $applied_count;
    }
}
?>
