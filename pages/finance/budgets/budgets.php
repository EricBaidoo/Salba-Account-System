<?php
// Redirect to new semester budget page
header('Location: term_budget.php');
exit;
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budgeting - Salba Montessori Accounting</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="../dashboard.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-chart-pie mr-2"></i>Budget Management</h1>
                    <p class="clean-page-subtitle">Plan, track, and manage your budget for <?php echo htmlspecialchars($current_term); ?></p>
                </div>
                <div class="flex gap-2 print:hidden">
                    <a href="#" onclick="window.print()" class="px-3 py-2 rounded-clean-outline">
                        <i class="fas fa-print"></i> PRINT
                    </a>
                    <a href="add_budget_form.php" class="px-3 py-2 rounded-clean-primary">
                        <i class="fas fa-plus"></i> CREATE BUDGET
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <!-- Print Header -->
        <?php $school_name = getSystemSetting($conn, 'school_name', 'Salba Montessori'); ?>
        <div class="print-header text-center">
            <h3 class="mb-"><?php echo htmlspecialchars($school_name); ?></h3>
            <div class="small text-gray-600">Budget Report - <?php echo htmlspecialchars($current_term); ?></div>
            <div class="small text-gray-600">Academic Year: <?php echo htmlspecialchars($academic_year); ?></div>
            <div class="small text-gray-600">Printed on <?php echo date('M j, Y'); ?></div>
        </div>

        <!-- Summary Cards -->
        <div class="row mb- print:hidden">
            <div class="col-md-3">
                <div class="budget-bg-white rounded shadow green">
                    <div class="flex justify-between items-start">
                        <div>
                            <h6 class="mb-">Total Budgeted</h6>
                            <h3 class="mb-">GHâ‚µ<?php echo number_format($total_budgeted, 2); ?></h3>
                        </div>
                        <i class="fas fa-bullseye fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-bg-white rounded shadow orange">
                    <div class="flex justify-between items-start">
                        <div>
                            <h6 class="mb-">Total Spent</h6>
                            <h3 class="mb-">GHâ‚µ<?php echo number_format($total_spent, 2); ?></h3>
                        </div>
                        <i class="fas fa-money-bill-wave fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-bg-white rounded shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <h6 class="mb-">Remaining</h6>
                            <h3 class="mb-">GHâ‚µ<?php echo number_format($total_budgeted - $total_spent, 2); ?></h3>
                        </div>
                        <i class="fas fa-piggy-bank fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="budget-bg-white rounded shadow">
                    <div class="flex justify-between items-start">
                        <div>
                            <h6 class="mb-">Average Usage</h6>
                            <h3 class="mb-"><?php echo $overall_variance; ?>%</h3>
                        </div>
                        <i class="fas fa-chart-bar fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Budgets w-full border-collapse -->
        <div class="clean-bg-white rounded shadow">
            <div class="clean-bg-white rounded shadow-header">
                <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-w-full border-collapse mr-2"></i>All Budgets (<?php echo count($budgets); ?>)</h5>
            </div>
            <div class="clean-bg-white rounded shadow-body">
                <?php if (count($budgets) > 0): ?>
                <div class="w-full border-collapse-responsive">
                    <table class="w-full border-collapse clean-w-full border-collapse budget-w-full border-collapse mb-">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Budgeted Amount</th>
                                <th>Spent</th>
                                <th>Remaining</th>
                                <th>Usage %</th>
                                <th>Status</th>
                                <th class="text-center print:hidden">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($budgets as $budget): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($budget['category']); ?></strong>
                                    <br><small class="text-gray-600"><?php echo htmlspecialchars($budget['description'] ?? ''); ?></small>
                                </td>
                                <td>GHâ‚µ<?php echo number_format($budget['amount'], 2); ?></td>
                                <td>GHâ‚µ<?php echo number_format($budget['spent'], 2); ?></td>
                                <td>GHâ‚µ<?php echo number_format($budget['balance'], 2); ?></td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <small><?php echo $budget['variance_percent']; ?>%</small>
                                        <div class="progress" class="w-24 h-1.5">
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
                                <td class="text-center print:hidden">
                                    <a href="edit_budget_form.php?id=<?php echo $budget['id']; ?>" class="clean-action-link" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_budget.php?id=<?php echo $budget['id']; ?>" class="clean-action-link text-red-600" title="Delete" onclick="return confirm('Are you sure?')">
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
        <div class="flex flex-wrap mt-4">
            <div class="lg:col-span-6">
                <div class="clean-bg-white rounded shadow">
                    <div class="clean-bg-white rounded shadow-header">
                        <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-chart-doughnut mr-2"></i>Budget Distribution</h5>
                    </div>
                    <div class="clean-bg-white rounded shadow-body">
                        <canvas id="budgetChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-6">
                <div class="clean-bg-white rounded shadow">
                    <div class="clean-bg-white rounded shadow-header">
                        <h5 class="clean-bg-white rounded shadow-title"><i class="fas fa-chart-bar mr-2"></i>Spending vs Budget</h5>
                    </div>
                    <div class="clean-bg-white rounded shadow-body">
                        <canvas id="spendingChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

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

