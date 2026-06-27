<?php
require_once '../../../includes/db_connect.php';
require_once '../../../includes/auth_functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_POST['action'] ?? '';

function syncParentAmount($conn, $budget_item_id) {
    $bid = intval($budget_item_id);
    $conn->query("UPDATE semester_budget_items
                  SET amount = (SELECT COALESCE(SUM(amount),0) FROM semester_budget_item_sources WHERE budget_item_id = $bid)
                  WHERE id = $bid");
}

function getParentTotal($conn, $budget_item_id) {
    $bid = intval($budget_item_id);
    $row = $conn->query("SELECT amount FROM semester_budget_items WHERE id = $bid")->fetch_assoc();
    return $row ? (float)$row['amount'] : 0.0;
}

function ensureBudgetItemExists($conn, $semester, $academic_year, $category) {
    // Ensure semester_budgets header row exists
    $sem  = $conn->real_escape_string($semester);
    $year = $conn->real_escape_string($academic_year);
    $cat  = $conn->real_escape_string($category);

    $budget = $conn->query("SELECT id FROM semester_budgets WHERE semester='$sem' AND academic_year='$year'")->fetch_assoc();
    if (!$budget) {
        $conn->query("INSERT INTO semester_budgets (semester, academic_year, expected_income, created_at) VALUES ('$sem','$year',0,NOW())");
        $budget_id = $conn->insert_id;
    } else {
        $budget_id = (int)$budget['id'];
    }

    // Ensure semester_budget_items row exists for this category/expense
    $item = $conn->query("SELECT id FROM semester_budget_items WHERE semester_budget_id=$budget_id AND category='$cat' AND type='expense'")->fetch_assoc();
    if (!$item) {
        $conn->query("INSERT INTO semester_budget_items (semester_budget_id, category, type, amount) VALUES ($budget_id,'$cat','expense',0)");
        return $conn->insert_id;
    }
    return (int)$item['id'];
}

switch ($action) {

    case 'add':
        $semester      = trim($_POST['semester'] ?? '');
        $academic_year = trim($_POST['academic_year'] ?? '');
        $category      = trim($_POST['category'] ?? '');
        $source        = trim($_POST['source'] ?? '');
        $amount        = (float)($_POST['amount'] ?? 0);
        $notes         = trim($_POST['notes'] ?? '');

        if (!$semester || !$academic_year || !$category || !$source || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'All fields are required and amount must be greater than 0.']);
            exit;
        }

        $budget_item_id = ensureBudgetItemExists($conn, $semester, $academic_year, $category);

        $src   = $conn->real_escape_string($source);
        $notes_esc = $conn->real_escape_string($notes);
        $conn->query("INSERT INTO semester_budget_item_sources (budget_item_id, source, amount, notes) VALUES ($budget_item_id,'$src',$amount,'$notes_esc')");
        $new_id = $conn->insert_id;

        syncParentAmount($conn, $budget_item_id);
        $new_total = getParentTotal($conn, $budget_item_id);

        echo json_encode([
            'success'         => true,
            'id'              => $new_id,
            'budget_item_id'  => $budget_item_id,
            'new_total'       => $new_total,
            'formatted_total' => number_format($new_total, 2)
        ]);
        break;

    case 'update':
        $id     = intval($_POST['id'] ?? 0);
        $source = trim($_POST['source'] ?? '');
        $amount = (float)($_POST['amount'] ?? 0);
        $notes  = trim($_POST['notes'] ?? '');

        if (!$id || !$source || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'All fields are required and amount must be greater than 0.']);
            exit;
        }

        $src       = $conn->real_escape_string($source);
        $notes_esc = $conn->real_escape_string($notes);
        $conn->query("UPDATE semester_budget_item_sources SET source='$src', amount=$amount, notes='$notes_esc' WHERE id=$id");

        $row = $conn->query("SELECT budget_item_id FROM semester_budget_item_sources WHERE id=$id")->fetch_assoc();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Item not found.']); exit; }

        syncParentAmount($conn, $row['budget_item_id']);
        $new_total = getParentTotal($conn, $row['budget_item_id']);

        echo json_encode([
            'success'         => true,
            'new_total'       => $new_total,
            'formatted_total' => number_format($new_total, 2)
        ]);
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID.']); exit; }

        $row = $conn->query("SELECT budget_item_id FROM semester_budget_item_sources WHERE id=$id")->fetch_assoc();
        if (!$row) { echo json_encode(['success' => false, 'message' => 'Item not found.']); exit; }

        $budget_item_id = (int)$row['budget_item_id'];
        $conn->query("DELETE FROM semester_budget_item_sources WHERE id=$id");

        syncParentAmount($conn, $budget_item_id);
        $new_total = getParentTotal($conn, $budget_item_id);

        echo json_encode([
            'success'         => true,
            'new_total'       => $new_total,
            'formatted_total' => number_format($new_total, 2)
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
}
