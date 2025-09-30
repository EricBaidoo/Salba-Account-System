<?php
include '../includes/db_connect.php';
include '../includes/auth_check.php';

// Get fee ID
$fee_id = isset($_GET['fee_id']) ? intval($_GET['fee_id']) : 0;
if (!$fee_id) {
    die('Invalid fee ID.');
}

// Fetch fee info
$fee = $conn->query("SELECT * FROM fees WHERE id = $fee_id")->fetch_assoc();
if (!$fee) {
    die('Fee not found.');
}

// Fetch class/category amounts
$amounts = [];
$res = $conn->query("SELECT * FROM fee_amounts WHERE fee_id = $fee_id");
while ($row = $res->fetch_assoc()) {
    if ($row['class_name']) {
        $amounts['class'][$row['class_name']] = $row['amount'];
    } elseif ($row['category']) {
        $amounts['category'][$row['category']] = $row['amount'];
    }
}

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $fee_type = $_POST['fee_type'];
    $main_amount = floatval($_POST['fixed_amount'] ?? 0);
    $class_amounts = $_POST['class_amounts'] ?? [];
    $category_amounts = $_POST['category_amounts'] ?? [];

    // Update main fee
    $stmt = $conn->prepare("UPDATE fees SET name=?, amount=?, fee_type=?, description=? WHERE id=?");
    $stmt->bind_param("sdssi", $name, $main_amount, $fee_type, $description, $fee_id);
    $stmt->execute();

    // Remove old amounts
    $conn->query("DELETE FROM fee_amounts WHERE fee_id = $fee_id");

    // Insert new class amounts
    if ($fee_type === 'class_based') {
        $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, class_name, amount) VALUES (?, ?, ?)");
        foreach ($class_amounts as $class => $amount) {
            if ($amount > 0) {
                $stmt->bind_param("isd", $fee_id, $class, $amount);
                $stmt->execute();
            }
        }
    }
    // Insert new category amounts
    if ($fee_type === 'category') {
        $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, category, amount) VALUES (?, ?, ?)");
        foreach ($category_amounts as $cat => $amount) {
            if ($amount > 0) {
                $stmt->bind_param("isd", $fee_id, $cat, $amount);
                $stmt->execute();
            }
        }
    }
    header("Location: view_fees.php?updated=1");
    exit;
}

// Classes and categories
$classes = [];
$class_res = $conn->query("SELECT name FROM classes ORDER BY id ASC");
while ($row = $class_res->fetch_assoc()) {
    $classes[] = $row['name'];
}
$categories = [
    'pre_school' => 'Pre-School (Creche - KG2)',
    'lower_basic' => 'Lower Basic (Basic 1-3)',
    'upper_basic' => 'Upper Basic (Basic 4-6)',
    'junior_high' => 'Junior High (Basic 7)'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Fee - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<div class="container mt-5">
    <h2>Edit Fee</h2>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Fee Name</label>
            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($fee['name']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control"><?php echo htmlspecialchars($fee['description']); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Fee Type</label>
            <select name="fee_type" class="form-select" id="feeTypeSelect" required onchange="updateAmountFields()">
                <option value="fixed" <?php if($fee['fee_type']==='fixed') echo 'selected'; ?>>Fixed</option>
                <option value="class_based" <?php if($fee['fee_type']==='class_based') echo 'selected'; ?>>Class Based</option>
                <option value="category" <?php if($fee['fee_type']==='category') echo 'selected'; ?>>Category Based</option>
            </select>
        </div>
        <div class="mb-3" id="fixedAmountDiv">
            <label class="form-label">Fixed Amount (GH₵)</label>
            <input type="number" step="0.01" name="fixed_amount" class="form-control" value="<?php echo $fee['amount']; ?>">
        </div>
        <div class="mb-3" id="classAmountsDiv" style="display:none;">
            <label class="form-label">Class Amounts</label>
            <?php foreach($classes as $class): ?>
                <div class="input-group mb-2">
                    <span class="input-group-text"><?php echo $class; ?></span>
                    <input type="number" step="0.01" name="class_amounts[<?php echo $class; ?>]" class="form-control" value="<?php echo isset($amounts['class'][$class]) ? $amounts['class'][$class] : ''; ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <div class="mb-3" id="categoryAmountsDiv" style="display:none;">
            <label class="form-label">Category Amounts</label>
            <?php foreach($categories as $cat=>$catLabel): ?>
                <div class="input-group mb-2">
                    <span class="input-group-text"><?php echo $catLabel; ?></span>
                    <input type="number" step="0.01" name="category_amounts[<?php echo $cat; ?>]" class="form-control" value="<?php echo isset($amounts['category'][$cat]) ? $amounts['category'][$cat] : ''; ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary">Save Changes</button>
        <a href="view_fees.php" class="btn btn-secondary">Cancel</a>
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
</script>
</body>
</html>
