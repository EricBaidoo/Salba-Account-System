<?php
/**
 * Academic Settings Database Migration
 * Runs SQL to create missing academic configuration tables
 */

include 'includes/db_connect.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    die('Unauthorized');
}

$sql_file = 'sql/create_academic_tables.sql';

if (!file_exists($sql_file)) {
    die("SQL file not found: $sql_file");
}

$sql_content = file_get_contents($sql_file);

// Split by semicolon and execute each statement
$statements = array_filter(array_map('trim', explode(';', $sql_content)));

$success = 0;
$failed = 0;
$messages = [];

foreach ($statements as $statement) {
    if (empty($statement) || strpos($statement, '--') === 0) {
        continue;
    }
    
    try {
        if ($conn->query($statement)) {
            $success++;
            $messages[] = "✓ " . substr($statement, 0, 50) . "...";
        } else {
            $failed++;
            $messages[] = "✗ Error: " . $conn->error;
        }
    } catch (Exception $e) {
        // Table might already exist, that's okay
        $messages[] = "⚠ " . substr($statement, 0, 50) . "... (" . $e->getMessage() . ")";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - Academic Settings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-50 p-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-2 flex items-center gap-3">
                <i class="fas fa-database text-blue-600"></i> Database Migration
            </h1>
            <p class="text-gray-600 mb-6">Academic Settings Tables Setup</p>

            <div class="space-y-4 mb-8">
                <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <strong>Status:</strong> Executed <?php echo $success; ?> statements successfully
                        <?php if ($failed > 0): ?>
                            with <?php echo $failed; ?> failures
                        <?php endif; ?>
                    </p>
                </div>

                <div class="bg-gray-50 border border-gray-200 p-4 rounded-lg max-h-96 overflow-y-auto font-mono text-xs">
                    <?php foreach ($messages as $msg): ?>
                        <div class="text-gray-700 my-1"><?php echo htmlspecialchars($msg); ?></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="flex gap-4">
                <a href="pages/administration/settings/academic_settings.php" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg text-center transition">
                    <i class="fas fa-arrow-right mr-2"></i> Go to Academic Settings
                </a>
                <a href="pages/administration/dashboard.php" class="flex-1 bg-gray-600 hover:bg-gray-700 text-white font-bold py-3 px-4 rounded-lg text-center transition">
                    <i class="fas fa-home mr-2"></i> Admin Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html>
