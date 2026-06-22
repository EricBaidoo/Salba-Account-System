<?php
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
include '../../../includes/accounting_engine.php';

// Set default timezone to Africa/Accra (Ghana/GMT)
date_default_timezone_set('Africa/Accra');

// Get active academic session details
$semester = getCurrentSemester($conn);
$academic_year = getAcademicYear($conn);

$raw_expenses = [
    // Description, Amount, Category ID
    ['Gas repairs', 250.00, 3],
    ['Court - habib sent it', 450.00, 10],
    ['Computer repairs with transportation', 250.00, 3],
    ['Amount given to the carpenter', 1000.00, 3],
    ['Teachers feeding', 250.00, 6],
    ['Thinner and paint brushes', 60.00, 3],
    ['Website and school management hosting and domain for Salba', 3000.00, 1],
    ['Transportation for Mr Ishmael for the books', 100.00, 5],
    ['Materials and sewing of uniforms (110 + 200)', 310.00, 8],
    ['Tetteh Henry (Staff Salaries)', 1500.00, 4],
    ['My hospital bills', 600.00, 10],
    ['Carpenter chairs', 2000.00, 3],
    ['Court process service charges', 200.00, 10],
    ['York series (Book Supplies)', 2234.00, 11],
    ['Don series (Book Supplies)', 867.00, 11],
    ['Riverside (Book Supplies)', 916.00, 11],
    ['Disposal of trash', 70.00, 15],
    ['Daniel uniform', 150.00, 8],
    ['Paint', 250.00, 3],
    ['Fuel', 600.00, 5],
    ['Crest for uniforms', 30.00, 8],
    ['Henry to buy hoe', 50.00, 3],
    ['Uniform', 60.00, 8],
    ['Cake', 800.00, 6],
    ['T-roll', 30.00, 2],
    ['Abacus educational cost', 1000.00, 9],
    ['Bill board', 1800.00, 10],
    ['Printing at Sowutuom', 98.00, 14],
    ['Abacus educational cost', 270.00, 9],
    ['Carpenter chairs', 600.00, 3],
    ['Computer repairs', 500.00, 3],
    ['Fuel', 300.00, 5],
    ['Waakye food cost', 135.00, 6],
    ['Kingsley (Staff Salaries)', 400.00, 4],
    ['Gas repair', 200.00, 3],
    ['Paint', 540.00, 3],
    ['Transportation for paint', 50.00, 5],
    ['Carpenter chairs', 200.00, 3],
    ['Amount sent to dr to be given to the inspector that came to the Bermudez residence', 555.00, 10],
    ['Plumber', 350.00, 3],
    ['Carpenter', 1000.00, 3],
    ['Cost of wood (2x2) and dividing of old woods at saw mill for picture frames', 400.00, 3],
    ['Cost of 3 French books', 450.00, 11],
    ['Cost toilet pulling', 900.00, 3],
    ['4 creates of egg', 222.00, 6],
    ['Victory text book', 160.00, 11],
    ['Onion', 550.00, 6],
    ['Potatoes', 700.00, 6],
    ['Data (Internet)', 50.00, 1],
    ['Kitchen sink', 1200.00, 3],
    ['Suitability test fee', 1500.00, 12],
    ['2 bags of rice', 1200.00, 6],
    ['Uniforms', 373.00, 8],
    ['Toilet pulling at Bermudez apartments', 4000.00, 3],
    ['Potatoes', 400.00, 6],
    ['Magazine to the house dr', 50.00, 10],
    ['Abacus educational cost', 2000.00, 9],
    ['Waakye leaves', 15.00, 6],
    ['Beans', 120.00, 6],
    ['Eggs and charge (220 + 4)', 224.00, 6],
    ['Keyboard', 400.00, 3],
    ['EBENZER to hospital (welfare/misc)', 300.00, 10],
    ['Photoshoot', 1000.00, 10],
    ['Oil', 505.00, 6],
    ['Beans and eggs', 360.00, 6],
];

// Date progression variables
$current_date_ts = strtotime('2026-04-15');

function get_next_weekday(&$ts) {
    $date_str = date('Y-m-d', $ts);
    // increment for the next call
    do {
        $ts = strtotime('+1 day', $ts);
        $w = date('N', $ts);
    } while ($w >= 6); // skip Saturday (6) and Sunday (7)
    return $date_str;
}

echo "Preparing to insert " . count($raw_expenses) . " expenses...\n";
$success_count = 0;
$fail_count = 0;

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("INSERT INTO expenses (category_id, amount, expense_date, description, semester, academic_year) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    foreach ($raw_expenses as $exp) {
        $desc = $exp[0];
        $amount = (float)$exp[1];
        $cat_id = (int)$exp[2];
        $date = get_next_weekday($current_date_ts);
        
        $stmt->bind_param("idssss", $cat_id, $amount, $date, $desc, $semester, $academic_year);
        if ($stmt->execute()) {
            $expense_id = $conn->insert_id;
            
            // Record double-entry journal logs
            $je_id = record_journal_entry($conn, $date, 'Expense', $expense_id, "Expense: $desc", [
                ['account_code' => '5200', 'debit' => $amount, 'credit' => 0], // Operational Expense DR
                ['account_code' => '1000', 'debit' => 0, 'credit' => $amount]  // Cash CR
            ]);
            
            if ($je_id) {
                $success_count++;
            } else {
                throw new Exception("Journal entry recording failed for: $desc");
            }
        } else {
            throw new Exception("Insert failed for: $desc - Error: " . $stmt->error);
        }
    }
    
    $stmt->close();
    $conn->commit();
    echo "Successfully inserted $success_count expenses and recorded corresponding journal logs.\n";

} catch (Exception $e) {
    $conn->rollback();
    echo "Transaction rolled back due to error: " . $e->getMessage() . "\n";
}
