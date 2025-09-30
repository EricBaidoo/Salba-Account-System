<?php include '../includes/auth_functions.php'; ?>
<?php if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
} ?>
<?php
include '../includes/db_connect.php';
$sql = "SELECT p.id, s.first_name, s.last_name, s.class, p.amount, p.payment_date, p.receipt_no, p.description
        FROM payments p
        JOIN students s ON p.student_id = s.id
        ORDER BY p.payment_date DESC, p.id DESC";
$result = $conn->query($sql);
// Summary stats
$total_payments = 0;
$total_amount = 0;
$payments = [];
while($row = $result->fetch_assoc()) {
    $payments[] = $row;
    $total_payments++;
    $total_amount += $row['amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment History - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .header-bg {
            background: linear-gradient(120deg, #6a11cb 0%, #2575fc 100%);
            color: white;
            border-radius: 0 0 2rem 2rem;
            box-shadow: 0 8px 32px rgba(106,17,203,0.08);
            padding: 2.5rem 0 2rem 0;
            margin-bottom: 2.5rem;
        }
        .summary-card {
            background: #fff;
            border-radius: 1.5rem;
            box-shadow: 0 4px 24px rgba(37,117,252,0.07);
            padding: 2rem 1.5rem;
            margin-bottom: 2rem;
        }
        .table-responsive {
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(37,117,252,0.04);
        }
        .table thead {
            background: #f8fafd;
        }
        .search-bar {
            max-width: 350px;
        }
        .badge-class {
            background: #e3e6fc;
            color: #3a3f7d;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="header-bg mb-4">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h1 class="fw-bold mb-1">Payment History</h1>
                    <p class="mb-0">All student payments, receipts, and details</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="dashboard.php" class="btn btn-outline-light btn-lg">
                        <i class="fas fa-home me-2"></i>Dashboard
                    </a>
                    <a href="record_payment_form.php" class="btn btn-light btn-lg">
                        <i class="fas fa-credit-card me-2"></i>Record New Payment
                    </a>
                </div>
            </div>
        </div>
    </div>
    <div class="container">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="summary-card text-center">
                    <div class="fs-2 fw-bold text-primary"><?php echo $total_payments; ?></div>
                    <div class="text-muted">Total Payments</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="summary-card text-center">
                    <div class="fs-2 fw-bold text-success">GH₵<?php echo number_format($total_amount, 2); ?></div>
                    <div class="text-muted">Total Amount Paid</div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="summary-card text-center">
                    <div class="fs-2 fw-bold text-info">
                        <?php echo date('F Y'); ?>
                    </div>
                    <div class="text-muted">Current Period</div>
                </div>
            </div>
        </div>
        <!-- Search Bar -->
        <div class="row mb-3">
            <div class="col-md-6">
                <input type="text" class="form-control search-bar" id="searchInput" placeholder="Search by student, receipt, or description...">
            </div>
        </div>
        <!-- Payment Table -->
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="paymentsTable">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Amount (GH₵)</th>
                        <th>Date</th>
                        <th>Receipt No.</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($payments as $row): ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td><?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?></td>
                        <td><span class="badge badge-class"><?php echo htmlspecialchars($row['class']); ?></span></td>
                        <td class="fw-bold text-success">GH₵<?php echo number_format($row['amount'], 2); ?></td>
                        <td><?php echo date('M j, Y', strtotime($row['payment_date'])); ?></td>
                        <td><?php echo htmlspecialchars($row['receipt_no']); ?></td>
                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                        <td>
                            <a href="receipt.php?payment_id=<?php echo $row['id']; ?>" class="btn btn-outline-primary btn-sm" target="_blank">
                                <i class="fas fa-receipt"></i> View/Print Receipt
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($payments)): ?>
            <div class="text-center py-5">
                <i class="fas fa-money-bill-wave fa-3x text-muted mb-3"></i>
                <h4 class="text-muted">No payments found.</h4>
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    <script>
        // Simple search filter
        document.getElementById('searchInput').addEventListener('input', function() {
            const value = this.value.toLowerCase();
            const rows = document.querySelectorAll('#paymentsTable tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(value) ? '' : 'none';
            });
        });
    </script>
</body>
</html>