<?php
session_start();
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
include '../../includes/system_settings.php';

if (!is_logged_in()) {
    header('Location: ../../includes/login.php');
    exit;
}

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_subject') {
    $subject_name = trim($_POST['subject_name'] ?? '');
    $subject_code = trim($_POST['subject_code'] ?? '');
    $description  = trim($_POST['description'] ?? '');

    if (empty($subject_name) || empty($subject_code)) {
        $error = "Subject Name and Code are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO subjects (name, code, description) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sss', $subject_name, $subject_code, $description);
            if ($stmt->execute()) {
                $success = "Subject added successfully!";
            } else {
                // Handle duplicate codes nicely
                if ($conn->errno === 1062) {
                    $error = "A subject with this code already exists.";
                } else {
                    $error = "Database Error: " . $conn->error;
                }
            }
            $stmt->close();
        }
    }
}

// Get subjects
$subjects = $conn->query("SELECT id, name as subject_name, code as subject_code, description FROM subjects ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subject Management - Academics</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <script>
        function toggleModal(modalID){
            document.getElementById(modalID).classList.toggle("hidden");
            document.getElementById(modalID).classList.toggle("flex");
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen relative">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6 sticky top-0 z-30">
            <div class="flex items-center gap-3 mb-4">
                <a href="dashboard.php" class="text-gray-400 hover:text-indigo-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Academics Dashboard
                </a>
            </div>
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-book-open text-indigo-600"></i> Subject Management
                    </h1>
                    <p class="text-gray-500 mt-2 text-sm">
                        Define school curricula, subjects, and unique subject identifiers.
                    </p>
                </div>
                <button onclick="toggleModal('addSubjectModal')" class="bg-indigo-600 text-white px-5 py-2.5 rounded-lg hover:bg-indigo-700 transition shadow-sm border border-transparent flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-plus"></i> Register Subject
                </button>
            </div>
        </div>

        <div class="p-8 max-w-6xl">
            <?php if ($success): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-check-circle text-emerald-500"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6 flex items-center gap-3 shadow-sm">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Subjects Table -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                    <h5 class="font-bold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-list text-gray-400"></i> Active Subjects Directory
                    </h5>
                    <span class="px-2 py-1 bg-gray-200 text-gray-600 text-xs font-mono rounded">
                        Total: <?php echo $subjects ? $subjects->num_rows : 0; ?>
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100 font-semibold text-gray-500">
                                <th class="px-6 py-4">Subject Name</th>
                                <th class="px-6 py-4">Subject Code</th>
                                <th class="px-6 py-4">Description</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php 
                            $has_subjects = false;
                            if ($subjects && $subjects->num_rows > 0):
                                while ($row = $subjects->fetch_assoc()):
                                    $has_subjects = true;
                            ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['subject_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="px-2 py-1 bg-indigo-50 text-indigo-700 font-mono text-xs font-bold rounded border border-indigo-100">
                                            <?php echo htmlspecialchars($row['subject_code']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 text-gray-500 max-w-md truncate">
                                        <?php echo htmlspecialchars($row['description']) ?: '<span class="text-gray-300 italic">No description</span>'; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="text-gray-400 hover:text-indigo-600 transition-colors mr-3" title="Edit Subject">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="text-gray-400 hover:text-red-600 transition-colors" title="Delete Subject">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php 
                                endwhile;
                            endif;
                            ?>
                            <?php if (!$has_subjects): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                                        <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center mx-auto mb-3">
                                            <i class="fas fa-book-open text-2xl text-gray-300"></i>
                                        </div>
                                        <p class="font-medium text-gray-900 mb-1">No subjects found</p>
                                        <p class="text-sm">Click "Register Subject" to begin adding your curriculum.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Add Subject Modal -->
    <div id="addSubjectModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm transition-opacity">
        <div class="bg-white rounded-xl shadow-xl border border-gray-100 w-full max-w-lg overflow-hidden relative">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="font-bold text-gray-900"><i class="fas fa-plus text-indigo-500 mr-2"></i> Register New Subject</h3>
                <button onclick="toggleModal('addSubjectModal')" class="text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <form action="" method="POST" class="p-6">
                <input type="hidden" name="action" value="add_subject">
                
                <div class="space-y-4">
                    <div>
                        <label for="subject_name" class="block text-sm font-semibold text-gray-700 mb-1">Subject Name <span class="text-red-500">*</span></label>
                        <input type="text" id="subject_name" name="subject_name" required placeholder="e.g., General Science"
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors">
                    </div>
                    <div>
                        <label for="subject_code" class="block text-sm font-semibold text-gray-700 mb-1">Subject Code <span class="text-red-500">*</span></label>
                        <input type="text" id="subject_code" name="subject_code" required placeholder="e.g., SCI-101"
                               class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors font-mono">
                        <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-wider">Must be unique across the system</p>
                    </div>
                    <div>
                        <label for="description" class="block text-sm font-semibold text-gray-700 mb-1">Description (Optional)</label>
                        <textarea id="description" name="description" rows="3" placeholder="Brief overview of the subject..."
                                  class="w-full px-4 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 transition-colors resize-none"></textarea>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-gray-100 flex justify-end gap-3">
                    <button type="button" onclick="toggleModal('addSubjectModal')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 shadow-sm transition-colors flex items-center gap-2">
                        <i class="fas fa-save"></i> Save Subject
                    </button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
