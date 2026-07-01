<?php
ini_set('display_errors', '0');
ob_start();
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';
ob_clean();
header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── CATALOG ──────────────────────────────────────────────────────────
    case 'add_item': {
        $name  = trim($_POST['name'] ?? '');
        $unit  = trim($_POST['unit'] ?? '');
        $price = floatval($_POST['default_price'] ?? 0);
        $desc  = trim($_POST['description'] ?? '');
        if (!$name) { echo json_encode(['success'=>false,'message'=>'Name required']); exit; }
        $stmt = $conn->prepare("INSERT INTO stationery_items (name, description, unit, default_price) VALUES (?,?,?,?)");
        $stmt->bind_param('sssd', $name, $desc, $unit, $price);
        $stmt->execute();
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        break;
    }

    case 'edit_item': {
        $id    = intval($_POST['id'] ?? 0);
        $name  = trim($_POST['name'] ?? '');
        $unit  = trim($_POST['unit'] ?? '');
        $price = floatval($_POST['default_price'] ?? 0);
        $desc  = trim($_POST['description'] ?? '');
        if (!$id || !$name) { echo json_encode(['success'=>false,'message'=>'Invalid']); exit; }
        $stmt = $conn->prepare("UPDATE stationery_items SET name=?, description=?, unit=?, default_price=? WHERE id=?");
        $stmt->bind_param('sssdi', $name, $desc, $unit, $price, $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;
    }

    case 'delete_item': {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false]); exit; }
        $cnt = $conn->query("SELECT COUNT(*) as c FROM stationery_assignments WHERE item_id=$id")->fetch_assoc()['c'];
        if ($cnt > 0) {
            echo json_encode(['success'=>false,'message'=>"Assigned to $cnt class(es). Remove assignments first."]);
            exit;
        }
        $conn->query("DELETE FROM stationery_items WHERE id=$id");
        echo json_encode(['success' => true]);
        break;
    }

    // ── ASSIGNMENTS ──────────────────────────────────────────────────────
    case 'assign_item': {
        $item_id  = intval($_POST['item_id'] ?? 0);
        $class    = trim($_POST['class'] ?? '');
        $year     = trim($_POST['academic_year'] ?? '');
        $semester = trim($_POST['semester'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '1');
        $price    = floatval($_POST['price'] ?? 0);
        if (!$item_id || !$class || !$year) {
            echo json_encode(['success'=>false,'message'=>'Missing fields']); exit;
        }
        $sc = $conn->real_escape_string($class);
        $sy = $conn->real_escape_string($year);
        $max = (int)$conn->query("SELECT COALESCE(MAX(sort_order),0)+1 as n FROM stationery_assignments WHERE class='$sc' AND academic_year='$sy'")->fetch_assoc()['n'];
        $stmt = $conn->prepare("INSERT INTO stationery_assignments (item_id, class, academic_year, semester, quantity, price, sort_order)
            VALUES (?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE quantity=VALUES(quantity), price=VALUES(price), semester=VALUES(semester)");
        $stmt->bind_param('issssdi', $item_id, $class, $year, $semester, $quantity, $price, $max);
        $stmt->execute();
        $id = $conn->insert_id ?: (int)$conn->query("SELECT id FROM stationery_assignments WHERE item_id=$item_id AND class='$sc' AND academic_year='$sy'")->fetch_assoc()['id'];
        echo json_encode(['success' => true, 'id' => $id]);
        break;
    }

    case 'edit_assignment': {
        $id       = intval($_POST['id'] ?? 0);
        $quantity = trim($_POST['quantity'] ?? '1');
        $price    = floatval($_POST['price'] ?? 0);
        $semester = trim($_POST['semester'] ?? '');
        if (!$id) { echo json_encode(['success'=>false]); exit; }
        $stmt = $conn->prepare("UPDATE stationery_assignments SET quantity=?, price=?, semester=? WHERE id=?");
        $stmt->bind_param('sdsi', $quantity, $price, $semester, $id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;
    }

    case 'unassign_item': {
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success'=>false]); exit; }
        $conn->query("DELETE FROM stationery_assignments WHERE id=$id");
        echo json_encode(['success' => true]);
        break;
    }

    // ── TRACKING ─────────────────────────────────────────────────────────
    case 'toggle_brought': {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $student_id    = intval($_POST['student_id'] ?? 0);
        $brought       = intval($_POST['brought'] ?? 0);
        if (!$assignment_id || !$student_id) { echo json_encode(['success'=>false]); exit; }
        $stmt = $conn->prepare("INSERT INTO stationery_submissions (assignment_id, student_id, brought) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE brought=VALUES(brought)");
        $stmt->bind_param('iii', $assignment_id, $student_id, $brought);
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;
    }

    case 'bill': {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $student_id    = intval($_POST['student_id'] ?? 0);
        $semester      = trim($_POST['semester'] ?? '');
        $academic_year = trim($_POST['academic_year'] ?? '');
        if (!$assignment_id || !$student_id || !$semester || !$academic_year) {
            echo json_encode(['success'=>false,'message'=>'Missing fields']); exit;
        }
        $result = $conn->query("SELECT sa.price, si.name FROM stationery_assignments sa
            JOIN stationery_items si ON sa.item_id=si.id WHERE sa.id=$assignment_id");
        if (!$result) {
            echo json_encode(['success'=>false,'message'=>'Database error: '.$conn->error.' — run the DB patch first.']); exit;
        }
        $row = $result->fetch_assoc();
        if (!$row || $row['price'] <= 0) {
            echo json_encode(['success'=>false,'message'=>'Item has no price set. Edit the assignment to add a price first.']); exit;
        }
        $billing_fee_name = getSystemSetting($conn, 'stationery_billing_fee_name', 'stationery');
        $fee = $conn->query("SELECT id FROM fees WHERE LOWER(name)=LOWER('".$conn->real_escape_string($billing_fee_name)."') LIMIT 1")->fetch_assoc();
        if (!$fee) {
            echo json_encode(['success'=>false,'message'=>'No "'.htmlspecialchars($billing_fee_name).'" fee exists. Create one under Finance > Fees first or update the fee name in Stationery Settings.']); exit;
        }
        $fee_id    = $fee['id'];
        $price     = (float)$row['price'];
        $item_name = $row['name'];
        $sem_esc   = $conn->real_escape_string($semester);
        $yr_esc    = $conn->real_escape_string($academic_year);

        // Find existing consolidated stationery row for this student/semester/year
        $existing = $conn->query("SELECT id, amount, notes FROM student_fees
            WHERE student_id=$student_id AND fee_id=$fee_id
              AND semester='$sem_esc' AND academic_year='$yr_esc'
            LIMIT 1")->fetch_assoc();

        if ($existing) {
            // Add to the existing row
            $new_amount = round((float)$existing['amount'] + $price, 2);
            $new_notes  = $existing['notes'] . ', ' . $item_name;
            $sf_id      = (int)$existing['id'];
            $upd = $conn->prepare("UPDATE student_fees SET amount=?, notes=? WHERE id=?");
            $upd->bind_param('dsi', $new_amount, $new_notes, $sf_id);
            $upd->execute();
        } else {
            // Create the first consolidated row
            $notes = $item_name;
            $stmt  = $conn->prepare("INSERT INTO student_fees (student_id, fee_id, amount, amount_paid, semester, academic_year, notes, assigned_date, status)
                VALUES (?,?,?,0,?,?,?,CURDATE(),'pending')");
            $stmt->bind_param('iidsss', $student_id, $fee_id, $price, $semester, $academic_year, $notes);
            $stmt->execute();
            $sf_id = $conn->insert_id;
        }
        $stmt2 = $conn->prepare("INSERT INTO stationery_submissions (assignment_id, student_id, billed, student_fee_id) VALUES (?,?,1,?)
            ON DUPLICATE KEY UPDATE billed=1, student_fee_id=VALUES(student_fee_id)");
        $stmt2->bind_param('iii', $assignment_id, $student_id, $sf_id);
        $stmt2->execute();
        echo json_encode(['success'=>true,'message'=>'GH&#8373;'.number_format($price,2).' billed.']);
        break;
    }

    case 'unbill': {
        $assignment_id = intval($_POST['assignment_id'] ?? 0);
        $student_id    = intval($_POST['student_id']    ?? 0);
        $mark_brought  = intval($_POST['mark_brought']  ?? 0);
        if (!$assignment_id || !$student_id) {
            echo json_encode(['success'=>false,'message'=>'Missing fields']); exit;
        }
        // Get item price and name
        $item = $conn->query("SELECT sa.price, si.name FROM stationery_assignments sa
            JOIN stationery_items si ON sa.item_id=si.id WHERE sa.id=$assignment_id")->fetch_assoc();
        // Get the linked student_fees row
        $sub = $conn->query("SELECT student_fee_id FROM stationery_submissions
            WHERE assignment_id=$assignment_id AND student_id=$student_id")->fetch_assoc();
        if ($item && $sub && $sub['student_fee_id']) {
            $sf_id     = (int)$sub['student_fee_id'];
            $price     = (float)$item['price'];
            $item_name = $item['name'];
            $sf = $conn->query("SELECT amount, notes FROM student_fees WHERE id=$sf_id")->fetch_assoc();
            if ($sf) {
                $new_amount = round((float)$sf['amount'] - $price, 2);
                // Remove this item from the notes list
                $parts = array_filter(array_map('trim', explode(',', $sf['notes'])), fn($n) => $n !== $item_name);
                $new_notes = implode(', ', $parts);
                if ($new_amount <= 0) {
                    $conn->query("DELETE FROM student_fees WHERE id=$sf_id");
                } else {
                    $upd = $conn->prepare("UPDATE student_fees SET amount=?, notes=? WHERE id=?");
                    $upd->bind_param('dsi', $new_amount, $new_notes, $sf_id);
                    $upd->execute();
                }
            }
        }
        // Update submission row
        $brought_val = $mark_brought ? 1 : 0;
        $stmt = $conn->prepare("INSERT INTO stationery_submissions (assignment_id, student_id, brought, billed, student_fee_id)
            VALUES (?,?,?,0,NULL) ON DUPLICATE KEY UPDATE brought=?, billed=0, student_fee_id=NULL");
        $stmt->bind_param('iiii', $assignment_id, $student_id, $brought_val, $brought_val);
        $stmt->execute();
        $msg = $mark_brought ? 'Marked as brought and charge removed.' : 'Charge removed.';
        echo json_encode(['success'=>true,'message'=>$msg]);
        break;
    }

    default:
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
