<?php
include '../includes/auth_check.php';
include '../includes/db_connect.php';

$success = false;
$error = '';
$fee_details = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fee_type = $_POST['fee_type'] ?? '';
    $name = (isset($_POST['fee_name']) && $_POST['fee_name'] === 'custom') ? trim($_POST['custom_fee_name'] ?? '') : ($_POST['fee_name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    // Validate required fields
    if (empty($fee_type) || empty($name)) {
        $error = "Fee type and name are required fields.";
    } else {
        // Prevent duplicate fee names (case-insensitive)
        $dup_stmt = $conn->prepare("SELECT id FROM fees WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $dup_stmt->bind_param("s", $name);
        $dup_stmt->execute();
        $dup_stmt->store_result();
        if ($dup_stmt->num_rows > 0) {
            $error = "A fee with this name already exists. Please choose a different name.";
            $dup_stmt->close();
        } else {
            $dup_stmt->close();
            try {
                $conn->autocommit(false); // Start transaction
                // Determine amount for main fees table
                $main_amount = null;
                if ($fee_type === 'fixed') {
                    $main_amount = floatval($_POST['fixed_amount'] ?? 0);
                    if ($main_amount <= 0) {
                        throw new Exception("Fixed amount must be greater than 0.");
                    }
                }
                // Insert main fee record
                $stmt = $conn->prepare("INSERT INTO fees (name, amount, fee_type, description) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("sdss", $name, $main_amount, $fee_type, $description);
                if (!$stmt->execute()) {
                    throw new Exception("Error creating fee: " . $stmt->error);
                }
                $fee_id = $conn->insert_id;
                $stmt->close();
                // Handle class-based or category-based amounts
                if ($fee_type === 'class_based') {
                    $class_amounts = $_POST['class_amounts'] ?? [];
                    $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, class_name, amount) VALUES (?, ?, ?)");
                    foreach ($class_amounts as $class_name => $amount) {
                        if (!empty($amount) && $amount > 0) {
                            $stmt->bind_param("isd", $fee_id, $class_name, $amount);
                            if (!$stmt->execute()) {
                                throw new Exception("Error setting class amount for $class_name: " . $stmt->error);
                            }
                            $fee_details[] = "$class_name: GH₵" . number_format($amount, 2);
                        }
                    }
                    $stmt->close();
                } elseif ($fee_type === 'category') {
                    $category_amounts = $_POST['category_amounts'] ?? [];
                    $stmt = $conn->prepare("INSERT INTO fee_amounts (fee_id, category, amount) VALUES (?, ?, ?)");
                    foreach ($category_amounts as $category => $amount) {
                        if (!empty($amount) && $amount > 0) {
                            $stmt->bind_param("isd", $fee_id, $category, $amount);
                            if (!$stmt->execute()) {
                                throw new Exception("Error setting category amount for $category: " . $stmt->error);
                            }
                            $fee_details[] = "$category: GH₵" . number_format($amount, 2);
                        }
                    }
                    $stmt->close();
                } else {
                    $fee_details[] = "Fixed Amount: GH₵" . number_format($main_amount, 2);
                }
                $conn->commit(); // Commit transaction
                $success = true;
            } catch (Exception $e) {
                $conn->rollback(); // Rollback on error
                $error = $e->getMessage();
            }
            $conn->autocommit(true); // Reset autocommit
        }
    }
}
            
// ...existing code...
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Fee Result - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="clean-page">

    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="view_fees.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Fees
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-check-circle me-2"></i>Fee Creation Result</h1>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($success): ?>
                    <div class="card">
                        <div class="card-header bg-success text-white text-center">
                            <h4><i class="fas fa-check-circle me-2"></i>Fee Added Successfully!</h4>
                        </div>
                        <div class="card-body">
                            <div class="text-center mb-4">
                                <i class="fas fa-money-bill-wave fa-4x text-success mb-3"></i>
                                <h5><?php echo htmlspecialchars($name); ?></h5>
                                <span class="badge bg-info fs-6"><?php echo ucfirst(str_replace('_', ' ', $fee_type)); ?> Fee</span>
                            </div>
                            
                            <?php if (!empty($fee_details)): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-list me-2"></i>Fee Structure</h6>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($fee_details as $detail): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                                    <?php echo htmlspecialchars($detail); ?>
                                                    <i class="fas fa-check text-success"></i>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($description)): ?>
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <strong>Description:</strong> <?php echo htmlspecialchars($description); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center mt-4">
                                <a href="add_fee_form.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Add Another Fee
                                </a>
                                <a href="view_fees.php" class="btn btn-success">
                                    <i class="fas fa-eye me-2"></i>View All Fees
                                </a>
                                <a href="assign_fee_form.php" class="btn btn-info">
                                    <i class="fas fa-user-tag me-2"></i>Assign Fee
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header bg-danger text-white text-center">
                            <h4><i class="fas fa-exclamation-triangle me-2"></i>Error Adding Fee</h4>
                        </div>
                        <div class="card-body text-center">
                            <i class="fas fa-money-bill-alt fa-4x text-danger mb-3"></i>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                                <a href="add_fee_form.php" class="btn btn-primary">
                                    <i class="fas fa-arrow-left me-2"></i>Back to Form
                                </a>
                                <a href="dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-home me-2"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="text-center mt-4">
                    <a href="dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>