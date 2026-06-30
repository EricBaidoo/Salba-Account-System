<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
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
        $row = $conn->query("SELECT sa.price, si.name FROM stationery_assignments sa
            JOIN stationery_items si ON sa.item_id=si.id WHERE sa.id=$assignment_id")->fetch_assoc();
        if (!$row || $row['price'] <= 0) {
            echo json_encode(['success'=>false,'message'=>'Item has no price set. Edit the assignment to add a price first.']); exit;
        }
        $fee = $conn->query("SELECT id FROM fees WHERE LOWER(name)='stationery' LIMIT 1")->fetch_assoc();
        if (!$fee) {
            echo json_encode(['success'=>false,'message'=>'No "Stationery" fee exists. Create one under Finance > Fees first.']); exit;
        }
        $fee_id = $fee['id'];
        $price  = $row['price'];
        $notes  = $conn->real_escape_string($row['name']) . ' (Stationery)';
        $stmt = $conn->prepare("INSERT INTO student_fees (student_id, fee_id, amount, amount_paid, semester, academic_year, notes, assigned_date, status)
            VALUES (?,?,?,0,?,?,?,CURDATE(),'unpaid')");
        $stmt->bind_param('iidsss', $student_id, $fee_id, $price, $semester, $academic_year, $notes);
        $stmt->execute();
        $sf_id = $conn->insert_id;
        $stmt2 = $conn->prepare("INSERT INTO stationery_submissions (assignment_id, student_id, billed, student_fee_id) VALUES (?,?,1,?)
            ON DUPLICATE KEY UPDATE billed=1, student_fee_id=VALUES(student_fee_id)");
        $stmt2->bind_param('iii', $assignment_id, $student_id, $sf_id);
        $stmt2->execute();
        echo json_encode(['success'=>true,'message'=>'GH&#8373;'.number_format($price,2).' billed.']);
        break;
    }

    default:
        echo json_encode(['success'=>false,'message'=>'Unknown action']);
}
