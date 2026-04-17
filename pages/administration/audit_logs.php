<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../login');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../../index.php');
    exit;
}

// Fetch all system settings for audit
$all_settings = getAllSettings($conn);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Audit Logs - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="lg:ml-72 min-h-screen">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-40">
            <div class="flex items-center gap-3 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-clipboard-list text-gray-700"></i> System Audit Logs
                </h1>
                <p class="text-gray-500 mt-2 text-sm">
                    Read-only trace of configuration changes and system parameter updates.
                </p>
            </div>
        </div>

        <div class="p-8 max-w-6xl">
            <!-- Raw Database Settings Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-8">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-database text-blue-500"></i> Settings Dictionary Reference
                    </h5>
                    <span class="px-3 py-1 bg-blue-50 text-blue-700 font-bold text-xs uppercase tracking-wider rounded-lg shadow-sm border border-blue-100">Live Database View</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 font-semibold text-gray-500">
                                <th class="px-6 py-4">Configuration Key</th>
                                <th class="px-6 py-4">Current Value</th>
                                <th class="px-6 py-4">Description</th>
                                <th class="px-6 py-4">Trace Signature</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($all_settings as $key => $setting): ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4 font-mono text-gray-600 font-medium">
                                        <?php echo htmlspecialchars($key); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2.5 py-1.5 bg-gray-100 border border-gray-200 rounded-md text-gray-800 font-bold whitespace-nowrap shadow-sm text-xs">
                                            <?php echo htmlspecialchars($setting['setting_value']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500">
                                        <?php echo htmlspecialchars($setting['description'] ?? '—'); ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center font-bold text-xs">
                                                <?php echo strtoupper(substr($setting['updated_by'], 0, 2)); ?>
                                            </div>
                                            <div>
                                                <div class="text-xs font-bold text-gray-700"><?php echo htmlspecialchars($setting['updated_by']); ?></div>
                                                <div class="text-xs text-gray-400 font-medium flex items-center gap-1 mt-0.5">
                                                    <i class="far fa-clock"></i> <?php echo date('M j, Y g:i A', strtotime($setting['updated_at'])); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </main>
</body>
</html>
