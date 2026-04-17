<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

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
                // Determine amount for main fees w-full border-collapse
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
                            $fee_details[] = "$class_name: GHâ‚µ" . number_format($amount, 2);
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
                            $fee_details[] = "$category: GHâ‚µ" . number_format($amount, 2);
                        }
                    }
                    $stmt->close();
                } else {
                    $fee_details[] = "Fixed Amount: GHâ‚µ" . number_format($main_amount, 2);
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
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="view_fees.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Fees
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-check-circle mr-2"></i>Fee Creation Result</h1>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <div class="flex flex-wrap justify-center">
            <div class="col-lg-8">
                <?php if ($success): ?>
                    <div class="bg-white rounded shadow">
                        <div class="bg-white rounded shadow-header bg-success text-white text-center">
                            <h4><i class="fas fa-check-circle mr-2"></i>Fee Added Successfully!</h4>
                        </div>
                        <div class="bg-white rounded shadow-body">
                            <div class="text-center mb-">
                                <i class="fas fa-money-bill-wave fa-4x text-green-600 mb-"></i>
                                <h5><?php echo htmlspecialchars($name); ?></h5>
                                <span class="badge bg-info fs-6"><?php echo ucfirst(str_replace('_', ' ', $fee_type)); ?> Fee</span>
                            </div>
                            
                            <?php if (!empty($fee_details)): ?>
                                <div class="bg-white rounded shadow">
                                    <div class="bg-white rounded shadow-header">
                                        <h6 class="mb-"><i class="fas fa-list mr-2"></i>Fee Structure</h6>
                                    </div>
                                    <div class="bg-white rounded shadow-body">
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($fee_details as $detail): ?>
                                                <li class="list-group-item flex justify-between items-center">
                                                    <?php echo htmlspecialchars($detail); ?>
                                                    <i class="fas fa-check text-green-600"></i>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($description)): ?>
                                <div class="mt-3">
                                    <small class="text-gray-600">
                                        <strong>Description:</strong> <?php echo htmlspecialchars($description); ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                            
                            <div class="grid gap-2 md:flex md:justify-center mt-4">
                                <a href="add_fee_form.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    <i class="fas fa-plus mr-2"></i>Add Another Fee
                                </a>
                                <a href="view_fees.php" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                    <i class="fas fa-eye mr-2"></i>View All Fees
                                </a>
                                <a href="assign_fee_form.php" class="px-3 py-2 rounded px-3 py-2 rounded-info">
                                    <i class="fas fa-user-tag mr-2"></i>Assign Fee
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded shadow">
                        <div class="bg-white rounded shadow-header bg-danger text-white text-center">
                            <h4><i class="fas fa-exclamation-triangle mr-2"></i>Error Adding Fee</h4>
                        </div>
                        <div class="bg-white rounded shadow-body text-center">
                            <i class="fas fa-money-bill-alt fa-4x text-red-600 mb-"></i>
                            <div class="p-4 bg-red-100 text-red-700 rounded border border-red-200">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                            
                            <div class="grid gap-2 md:flex md:justify-center">
                                <a href="add_fee_form.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                                    <i class="fas fa-arrow-left mr-2"></i>Back to Form
                                </a>
                                <a href="../dashboard.php" class="px-4 py-2 bg-gray-600 text-white rounded">
                                    <i class="fas fa-home mr-2"></i>Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="text-center mt-4">
                    <a href="../dashboard.php" class="px-4 py-2 border border-gray-300 rounded">
                        <i class="fas fa-home mr-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    </body>
</html>
