<?php
/**
 * Core Accounting Engine API
 * Handles all double-entry ledger operations.
 */

if (!function_exists('record_journal_entry')) {
    function record_journal_entry($conn, $date, $ref_type, $ref_id, $description, $lines) {
        // Validate debits = credits
        $total_debit = 0;
        $total_credit = 0;
        foreach ($lines as $line) {
            $total_debit += (float)($line['debit'] ?? 0);
            $total_credit += (float)($line['credit'] ?? 0);
        }

        // Round to 2 decimals to prevent floating point issues
        if (round($total_debit, 2) !== round($total_credit, 2)) {
            error_log("Journal Entry Failed: Debits ($total_debit) do not equal Credits ($total_credit). Ref: $ref_type #$ref_id");
            return false;
        }

        // Start transaction if not already in one
        $conn->begin_transaction();

        try {
            // Insert Header
            $stmt = $conn->prepare("INSERT INTO journal_entries (entry_date, reference_type, reference_id, description) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssis", $date, $ref_type, $ref_id, $description);
            $stmt->execute();
            $je_id = $stmt->insert_id;
            $stmt->close();

            // Insert Lines
            $line_stmt = $conn->prepare("INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit) VALUES (?, ?, ?, ?)");
            
            // Map account codes to IDs cache
            $acc_cache = [];

            foreach ($lines as $line) {
                $code = $line['account_code'];
                if (!isset($acc_cache[$code])) {
                    $res = $conn->query("SELECT id FROM accounts WHERE account_code = '$code' LIMIT 1");
                    if ($res && $res->num_rows > 0) {
                        $acc_cache[$code] = $res->fetch_assoc()['id'];
                    } else {
                        throw new Exception("Account code $code not found.");
                    }
                }
                
                $acc_id = $acc_cache[$code];
                $dr = (float)($line['debit'] ?? 0);
                $cr = (float)($line['credit'] ?? 0);
                
                // Only insert if > 0
                if ($dr > 0 || $cr > 0) {
                    $line_stmt->bind_param("iidd", $je_id, $acc_id, $dr, $cr);
                    $line_stmt->execute();
                }
            }
            $line_stmt->close();

            $conn->commit();
            return $je_id;

        } catch (Exception $e) {
            $conn->rollback();
            error_log("Journal Entry Exception: " . $e->getMessage());
            return false;
        }
    }
}
?>
