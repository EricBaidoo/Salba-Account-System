<?php
require_once '../includes/db_connect.php';
require_once '../includes/auth_functions.php';
require_once '../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

function columnExists($conn, $table, $column) {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = (bool)$res->fetch_row();
    $stmt->close();
    return $exists;
}

function indexExists($conn, $table, $index) {
    $sql = "SELECT 1 FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $table, $index);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = (bool)$res->fetch_row();
    $stmt->close();
    return $exists;
}

$steps = [];
$errors = [];

// Add academic_year to student_fees if missing
try {
    if (!columnExists($conn, 'student_fees', 'academic_year')) {
        $conn->query("ALTER TABLE student_fees ADD COLUMN academic_year VARCHAR(9) NULL AFTER term");
        $steps[] = 'Added column student_fees.academic_year';
    } else {
        $steps[] = 'Column student_fees.academic_year already exists';
    }
} catch (Throwable $e) {
    $errors[] = 'Failed to add student_fees.academic_year: ' . $e->getMessage();
}

// Add academic_year to payments if missing
try {
    if (!columnExists($conn, 'payments', 'academic_year')) {
        $conn->query("ALTER TABLE payments ADD COLUMN academic_year VARCHAR(9) NULL AFTER term");
        $steps[] = 'Added column payments.academic_year';
    } else {
        $steps[] = 'Column payments.academic_year already exists';
    }
} catch (Throwable $e) {
    $errors[] = 'Failed to add payments.academic_year: ' . $e->getMessage();
}

// Create helpful indexes if missing
try {
    if (!indexExists($conn, 'student_fees', 'idx_student_fees_scope')) {
        $conn->query("CREATE INDEX idx_student_fees_scope ON student_fees (student_id, fee_id, term, academic_year)");
        $steps[] = 'Created index idx_student_fees_scope on student_fees';
    } else {
        $steps[] = 'Index idx_student_fees_scope already exists';
    }
} catch (Throwable $e) {
    $errors[] = 'Failed to create idx_student_fees_scope: ' . $e->getMessage();
}

try {
    if (!indexExists($conn, 'payments', 'idx_payments_scope')) {
        $conn->query("CREATE INDEX idx_payments_scope ON payments (student_id, term, academic_year)");
        $steps[] = 'Created index idx_payments_scope on payments';
    } else {
        $steps[] = 'Index idx_payments_scope already exists';
    }
} catch (Throwable $e) {
    $errors[] = 'Failed to create idx_payments_scope: ' . $e->getMessage();
}

// Simple UI output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Academic Year Migration</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body { padding: 24px; }
        .log-box { background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; padding: 16px; }
    </style>
    </head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="dashboard.php" class="btn btn-outline-secondary">&larr; Back</a>
            <h3 class="mb-0">Academic Year Migration</h3>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger"><strong>Errors encountered:</strong><br><?php echo implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php else: ?>
            <div class="alert alert-success">Migration completed successfully.</div>
        <?php endif; ?>
        <div class="log-box">
            <h6>Steps</h6>
            <ul class="mb-0">
                <?php foreach ($steps as $s): ?>
                    <li><?php echo htmlspecialchars($s); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="mt-3">
            <a class="btn btn-primary" href="student_balances.php">Go to Balances</a>
        </div>
    </div>
</body>
</html>
