<?php
// Redirect to new term budget page
header('Location: term_budget.php');
exit;
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgeting - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .budget-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
        }
        .budget-card.green {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: #333;
        }
        .budget-card.orange {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: #333;
        }
        .budget-card.red {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }
        .progress-bar {
            background-color: #667eea;
        }
        .budget-table {
            font-size: 0.95rem;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
        }
        .status-on-track { background-color: #28a745; color: white; }
        .status-warning { background-color: #ffc107; color: #333; }
        .status-over-budget { background-color: #dc3545; color: white; }
        @media print {
            .d-print-none { display: none !important; }
            .print-header { display: block !important; margin-bottom: 16px; }
        }
        @media screen {
            .print-header { display: none; }
        }
    </style>
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="dashboard.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-chart-pie me-2"></i>Budget Management</h1>
                    <p class="clean-page-subtitle">Plan, track, and manage your budget for <?php echo htmlspecialchars($current_term); ?></p>
                </div>
                <div class="d-flex gap-2 d-print-none">
                    <a href="#" onclick="window.print()" class="btn-clean-outline">
                        <i class="fas fa-print"></i> PRINT
                    </a>
                    <a href="add_budget_form.php" class="btn-clean-primary">
                        <i class="fas fa-plus"></i> CREATE BUDGET
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">
        <!-- Print Header -->
        <?php $school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori'); ?>
        <div class="print-header text-center">
            <h3 class="mb-0"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-muted">Budget Report - <?php echo htmlspecialchars($current_term); ?></div>
            <div class="small text-muted">Academic Year: <?php echo htmlspecialchars($academic_year); ?></div>
            <div class="small text-muted">Printed on <?php echo date('M j, Y'); ?></div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb-4 d-print-none">
            <div class="col-md-3">
                <div class="budget-card green">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-2">Total Budgeted</h6>
                            <h3 class="mb-0">GH₵<?php echo number_format($total_budgeted, 2); ?></h3>
                        </div>
                        <i class="fas fa-bullseye fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-card orange">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-2">Total Spent</h6>
                            <h3 class="mb-0">GH₵<?php echo number_format($total_spent, 2); ?></h3>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-2">Remaining</h6>
                            <h3 class="mb-0">GH₵<?php echo number_format($total_budgeted - $total_spent, 2); ?></h3>
                        </div>
                        <i class="fas fa-piggy-bank fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h6 class="mb-2">Average Usage</h6>
                            <h3 class="mb-0"><?php echo $overall_variance; ?>%</h3>
                        </div>
                        <i class="fas fa-chart-bar fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budgets Table -->
        <div class="clean-card">
            <div class="clean-card-header">
                <h5 class="clean-card-title"><i class="fas fa-table me-2"></i>All Budgets (<?php echo count($budgets); ?>)</h5>
            </div>
            <div class="clean-card-body">
                <?php if (count($budgets) > 0): ?>
                <div class="table-responsive">
                    <table class="table clean-table budget-table mb-0">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Budgeted Amount</th>
                                <th>Spent</th>
                                <th>Remaining</th>
                                <th>Usage %</th>
                                <th>Status</th>
                                <th class="text-center d-print-none">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($budget['category']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($budget['description'] ?? ''); ?></small>
                                </td>
                                <td>GH₵<?php echo number_format($budget['amount'], 2); ?></td>
                                <td>GH₵<?php echo number_format($budget['spent'], 2); ?></td>
                                <td>GH₵<?php echo number_format($budget['balance'], 2); ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <small><?php echo $budget['variance_percent']; ?>%</small>
                                        <div class="progress" style="width: 100px; height: 6px;">
                                            <div class="progress-bar <?php echo $budget['variance_percent'] > 80 ? 'bg-danger' : ($budget['variance_percent'] > 60 ? 'bg-warning' : 'bg-success'); ?>" 
                                                 style="width: <?php echo min($budget['variance_percent'], 100); ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo 'status-' . str_replace(' ', '-', strtolower($budget['status'])); ?>">
                                        <?php echo $budget['status']; ?>
                                    </span>
                                </td>
                                <td class="text-center d-print-none">
                                    <a href="edit_budget_form.php?id=<?php echo $budget['id']; ?>" class="clean-action-link" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_budget.php?id=<?php echo $budget['id']; ?>" class="clean-action-link text-danger" title="Delete" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="clean-alert clean-alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span>No budgets created yet. <a href="add_budget_form.php">Create your first budget</a> to get started.</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Budget Analysis -->
        <?php if (count($budgets) > 0): ?>
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title"><i class="fas fa-chart-doughnut me-2"></i>Budget Distribution</h5>
                    </div>
                    <div class="clean-card-body">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="clean-card">
                    <div class="clean-card-header">
                        <h5 class="clean-card-title"><i class="fas fa-chart-bar me-2"></i>Spending vs Budget</h5>
                    </div>
                    <div class="clean-card-body">
                        <canvas id="spendingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <script>
        <?php if (count($budgets) > 0): ?>
        // Budget Distribution Chart
        const budgetCtx = document.getElementById('budgetChart').getContext('2d');
        new Chart(budgetCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(fn($b) => "'" . addslashes($b['category']) . "'", $budgets)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_map(fn($b) => $b['amount'], $budgets)); ?>],
                    backgroundColor: [
                        '#667eea', '#764ba2', '#f093fb', '#4facfe', '#43e97b',
                        '#fa709a', '#fee140', '#30b0fe', '#ec2f55', '#ffa502'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 15 }
                    }
                }
            }
        });

        // Spending vs Budget Chart
        const spendingCtx = document.getElementById('spendingChart').getContext('2d');
        new Chart(spendingCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(fn($b) => "'" . addslashes($b['category']) . "'", $budgets)); ?>],
                datasets: [
                    {
                        label: 'Budgeted',
                        data: [<?php echo implode(',', array_map(fn($b) => $b['amount'], $budgets)); ?>],
                        backgroundColor: '#667eea'
                    },
                    {
                        label: 'Spent',
                        data: [<?php echo implode(',', array_map(fn($b) => $b['spent'], $budgets)); ?>],
                        backgroundColor: '#f093fb'
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    }
                }
            }
        });
        <?php endif; ?>
    </script>

</body>
</html>
