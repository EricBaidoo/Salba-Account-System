<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php');
    exit;
}

// Get classes and student counts with robust queries
$classes_data = [];
$res = $conn->query("
    SELECT c.name as class_name, COUNT(s.id) as count 
    FROM classes c 
    LEFT JOIN students s ON s.class = c.name AND s.status = 'active'
    GROUP BY c.id, c.name 
    ORDER BY c.name
");

if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $classes_data[] = ['class' => $row['class_name'], 'count' => $row['count']];
    }
} else {
    // Fallback if class table is missing names but students exist
    $fallback = $conn->query("SELECT class, COUNT(*) as cnt FROM students WHERE status='active' AND class IS NOT NULL GROUP BY class ORDER BY class");
    if ($fallback) {
        while ($row = $fallback->fetch_assoc()) {
            $classes_data[] = ['class' => $row['class'], 'count' => $row['cnt']];
        }
    }
}

// Fetch some quick stats
$total_classes = count($classes_data);
$total_students = array_sum(array_column($classes_data, 'count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Classes Management - Academics Module</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-40">
            <div class="flex items-center gap-3 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-purple-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Academics Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-chalkboard text-purple-600"></i> Classes Management
                    </h1>
                    <p class="text-gray-500 mt-2 text-sm">
                        Manage class levels, view active student enrollments, and assign subjects.
                    </p>
                </div>
                <div class="flex gap-3">
                    <button class="bg-purple-600 text-white px-5 py-2.5 rounded-lg hover:bg-purple-700 transition shadow-sm flex items-center gap-2 text-sm font-medium">
                        <i class="fas fa-plus"></i> Add New Class
                    </button>
                </div>
            </div>
        </div>

        <div class="p-8 max-w-7xl">
            <!-- Summary Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8 mt-2">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 flex items-center justify-between border-l-4 border-l-purple-500">
                    <div>
                        <div class="text-3xl font-bold text-gray-900 mb-1"><?= $total_classes ?></div>
                        <div class="text-xs uppercase font-bold text-gray-400 tracking-wider">Active Classes</div>
                    </div>
                    <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-full flex items-center justify-center text-xl">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                </div>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 flex items-center justify-between border-l-4 border-l-blue-500">
                    <div>
                        <div class="text-3xl font-bold text-gray-900 mb-1"><?= $total_students ?></div>
                        <div class="text-xs uppercase font-bold text-gray-400 tracking-wider">Total Enrolled Students</div>
                    </div>
                    <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-full flex items-center justify-center text-xl">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>

            <!-- Classes Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($classes_data as $index => $class): 
                    // Alternate colors for variety
                    $colors = ['text-purple-600', 'text-blue-600', 'text-emerald-600', 'text-orange-600', 'text-indigo-600', 'text-pink-600'];
                    $bgs    = ['bg-purple-50', 'bg-blue-50', 'bg-emerald-50', 'bg-orange-50', 'bg-indigo-50', 'bg-pink-50'];
                    $colorIndex = $index % count($colors);
                ?>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 hover:shadow-md hover:border-gray-200 transition-all group flex flex-col justify-between">
                        <div>
                            <div class="flex justify-between items-start mb-4">
                                <div class="w-10 h-10 <?php echo $bgs[$colorIndex]; ?> <?php echo $colors[$colorIndex]; ?> rounded-lg flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="px-2.5 py-1 text-xs font-bold bg-green-50 text-green-700 rounded-full border border-green-100">
                                    Active
                                </div>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-1"><?= htmlspecialchars(ucfirst($class['class'])) ?></h3>
                            <div class="text-sm text-gray-500">
                                <span class="font-bold text-gray-800"><?= $class['count'] ?></span> students enrolled
                            </div>
                        </div>
                        <div class="mt-6 pt-4 border-t border-gray-50 flex justify-between items-center">
                            <a href="../administration/students/view_students.php?class=<?= urlencode($class['class']) ?>" class="text-sm font-semibold text-gray-500 group-hover:text-purple-600 transition-colors flex items-center gap-1.5">
                                View Roster <i class="fas fa-arrow-right text-[10px]"></i>
                            </a>
                            <button class="text-gray-400 hover:text-gray-600 transition-colors">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($classes_data)): ?>
                    <div class="col-span-full bg-white rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500">
                        <i class="fas fa-inbox text-5xl mb-4 text-gray-300"></i>
                        <h4 class="text-lg font-bold text-gray-700 mb-1">No classes configured</h4>
                        <p class="text-sm">Get started by creating your first academic class.</p>
                        <button class="mt-6 bg-purple-600 text-white px-6 py-2.5 rounded-lg hover:bg-purple-700 transition shadow-sm font-medium">
                            Create First Class
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
