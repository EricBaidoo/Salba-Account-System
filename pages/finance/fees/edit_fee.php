<?php
include '../../includes/db_connect.php';
include '../../includes/auth_check.php';
$fee_id = isset($_GET['fee_id']) ? intval($_GET['fee_id']) : 0;
if (!$fee_id) { die('Invalid fee ID.'); }

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $fee_type = isset($_POST['fee_type']) ? $_POST['fee_type'] : '';

    // Update main fee table
    $stmt = $conn->prepare("UPDATE fees SET name=?, description=?, fee_type=? WHERE id=?");
    $stmt->bind_param('sssi', $name, $description, $fee_type, $fee_id);
    $stmt->execute();
    $stmt->close();

    // Remove old amounts
    $conn->query("DELETE FROM fee_amounts WHERE fee_id = $fee_id");

    // Insert new amounts
    if ($fee_type === 'fixed') {
        $fixed_amount = isset($_POST['fixed_amount']) ? floatval($_POST['fixed_amount']) : 0;
        $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, amount) VALUES (?, ?)");
        $stmt->bind_param('id', $fee_id, $fixed_amount);
        $stmt->execute();
        $stmt->close();
        // Also update main fee amount for legacy compatibility
        $conn->query("UPDATE fees SET amount = $fixed_amount WHERE id = $fee_id");
    } elseif ($fee_type === 'class_based' && isset($_POST['class_amounts'])) {
        foreach ($_POST['class_amounts'] as $class => $amount) {
            if ($amount !== '' && is_numeric($amount)) {
                $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, class_name, amount) VALUES (?, ?, ?)");
                $stmt->bind_param('isd', $fee_id, $class, $amount);
                $stmt->execute();
                $stmt->close();
            }
        }
        $conn->query("UPDATE fees SET amount = 0 WHERE id = $fee_id");
    } elseif ($fee_type === 'category' && isset($_POST['category_amounts'])) {
        foreach ($_POST['category_amounts'] as $catId => $amount) {
            if ($amount !== '' && is_numeric($amount)) {
                $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, category, amount) VALUES (?, ?, ?)");
                $stmt->bind_param('isd', $fee_id, $catId, $amount);
                $stmt->execute();
                $stmt->close();
            }
        }
        $conn->query("UPDATE fees SET amount = 0 WHERE id = $fee_id");
    }

    // Redirect to avoid resubmission and reload updated data
    header("Location: edit_fee.php?fee_id=$fee_id&updated=1");
    exit;
}

$fee = $conn->query("SELECT * FROM fees WHERE id = $fee_id")->fetch_assoc();
if (!$fee) { die('Fee not found.'); }
$amounts = [];
$res = $conn->query("SELECT * FROM fee_amounts WHERE fee_id = $fee_id");
while ($row = $res->fetch_assoc()) {
    if ($row['class_name']) {
        $amounts['class'][(string)$row['class_name']] = $row['amount'];
    } elseif ($row['category']) {
        $amounts['category'][(string)$row['category']] = $row['amount'];
    }
}
$classes = [];
$class_res = $conn->query("SELECT name FROM classes ORDER BY id ASC");
while ($row = $class_res->fetch_assoc()) { $classes[] = $row['name']; }
include '../../includes/fee_categories.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Fee - Custom</title>
    <link rel="stylesheet" href="../../css/edit_fee_custom.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="edit-fee-wrapper">
    <div class="edit-fee-header">Edit Fee</div>
    <form method="post" autocomplete="off">
        <label class="edit-fee-label">Fee Name</label>
        <input type="text" name="name" class="edit-fee-input" value="<?php echo htmlspecialchars($fee['name']); ?>" required>
        <label class="edit-fee-label">Description</label>
        <textarea name="description" class="edit-fee-textarea" rows="2"><?php echo htmlspecialchars($fee['description']); ?></textarea>
        <label class="edit-fee-label">Fee Type</label>
        <select name="fee_type" class="edit-fee-select" id="feeTypeSelect" required>
            <option value="fixed" <?php if($fee['fee_type']==='fixed') echo 'selected'; ?>>Fixed Amount</option>
            <option value="class_based" <?php if($fee['fee_type']==='class_based') echo 'selected'; ?>>Class Based</option>
            <option value="category" <?php if($fee['fee_type']==='category') echo 'selected'; ?>>Category Based</option>
        </select>
        <div id="fixedAmountDiv" class="edit-fee-section">
            <h4>Fixed Amount (GH₵)</h4>
            <input type="number" step="0.01" name="fixed_amount" class="edit-fee-input" value="<?php echo $fee['amount']; ?>">
        </div>
        <div id="classAmountsDiv" class="edit-fee-section">
            <h4>Class-Based Amounts</h4>
            <?php foreach($classes as $class): ?>
            <div class="edit-fee-amount-row">
                <span class="edit-fee-amount-label"><?php echo $class; ?></span>
                <input type="number" step="0.01" name="class_amounts[<?php echo $class; ?>]" class="edit-fee-amount-input" value="<?php echo isset($amounts['class'][$class]) ? htmlspecialchars($amounts['class'][$class]) : ''; ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <div id="categoryAmountsDiv" class="edit-fee-section">
            <h4>Category-Based Amounts</h4>
            <?php foreach($fee_categories as $catId=>$catLabel): ?>
            <div class="edit-fee-amount-row">
                <span class="edit-fee-amount-label"><?php echo htmlspecialchars($catLabel); ?></span>
                <input type="number" step="0.01" name="category_amounts[<?php echo $catId; ?>]" class="edit-fee-amount-input" value="<?php echo isset($amounts['category'][(string)$catId]) ? htmlspecialchars($amounts['category'][(string)$catId]) : ''; ?>">
            </div>
            <?php endforeach; ?>
        </div>
        <div class="edit-fee-px-3 py-2 rounded font-medium-row">
            <button type="submit" class="edit-fee-px-3 py-2 rounded font-medium">Save Changes</button>
            <a href="view_fees.php" class="edit-fee-px-3 py-2 rounded font-medium cancel">Cancel</a>
        </div>
    </form>
</div>
<script>
function updateAmountFields() {
    var type = document.getElementById('feeTypeSelect').value;
    document.getElementById('fixedAmountDiv').style.display = (type === 'fixed') ? '' : 'none';
    document.getElementById('classAmountsDiv').style.display = (type === 'class_based') ? '' : 'none';
    document.getElementById('categoryAmountsDiv').style.display = (type === 'category') ? '' : 'none';
}
updateAmountFields();
document.getElementById('feeTypeSelect').addEventListener('change', updateAmountFields);
</script>
</body>
</html>
