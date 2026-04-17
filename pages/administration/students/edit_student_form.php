<?php 
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

$student = null;
$error = '';

if (isset($_GET['id'])) {
    $student_id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
    } else {
        $error = "Student not found.";
    }
    $stmt->close();
} else {
    $error = "No student ID provided.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="lg:ml-72 min-h-screen">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6">
            <div class="flex items-center gap-3 mb-4">
                <a href="view_students.php" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Directory
                </a>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-user-edit text-blue-600"></i> Edit Student Profile
                </h1>
                <p class="text-gray-500 mt-2 text-sm">
                    Modify existing student records and manage active status.
                </p>
            </div>
        </div>

        <div class="p-8">
            <?php if ($error): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg flex items-center gap-3 mb-6 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
            <?php else: ?>
                <div class="max-w-3xl">
                    <!-- Current Overview -->
                    <div class="bg-white rounded-t-xl border border-gray-100 shadow-sm p-6 border-b-0 flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">
                                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                            </h2>
                            <div class="flex items-center gap-3 mt-2 text-sm text-gray-500">
                                <span><i class="fas fa-hashtag"></i> <?php echo str_pad($student['id'], 3, '0', STR_PAD_LEFT); ?></span>
                                <span>&bull;</span>
                                <span><i class="fas fa-chalkboard"></i> <?php echo htmlspecialchars($student['class']); ?></span>
                                <span>&bull;</span>
                                <?php if ($student['status'] === 'active'): ?>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-100">
                                        Active
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-700 border border-gray-200">
                                        Inactive
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-full flex items-center justify-center text-white text-xl font-bold shadow-md">
                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                        </div>
                    </div>

                    <div class="bg-white rounded-b-xl border border-gray-100 shadow-sm overflow-hidden">
                        <form action="update_student.php" method="POST" id="editStudentForm" class="p-6">
                            <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                            
                            <!-- Required Information -->
                            <div class="mb-8">
                                <h6 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">
                                    Identity Profile
                                </h6>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-1">First Name <span class="text-red-500">*</span></label>
                                        <input type="text" id="first_name" name="first_name" required
                                               value="<?php echo htmlspecialchars($student['first_name']); ?>"
                                               class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
                                    </div>
                                    <div>
                                        <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-1">Last Name <span class="text-red-500">*</span></label>
                                        <input type="text" id="last_name" name="last_name" required
                                               value="<?php echo htmlspecialchars($student['last_name']); ?>"
                                               class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="class" class="block text-sm font-semibold text-gray-700 mb-1">Class / Grade <span class="text-red-500">*</span></label>
                                        <select id="class" name="class" required
                                                class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors appearance-none">
                                            <?php 
                                            $classesList = ['Creche','Nursery 1','Nursery 2','KG 1','KG 2','Basic 1','Basic 2','Basic 3','Basic 4','Basic 5','Basic 6','Basic 7'];
                                            foreach($classesList as $c): 
                                            ?>
                                                <option value="<?php echo $c; ?>" <?php echo $student['class'] === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="md:col-span-2">
                                        <label for="status" class="block text-sm font-semibold text-gray-700 mb-1">Account Status <span class="text-red-500">*</span></label>
                                        <select id="status" name="status" required
                                                class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors appearance-none">
                                            <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                            <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive (Suspending student logic)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Optional Information -->
                            <div class="mb-8">
                                <h6 class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-4">
                                    Extra Details
                                </h6>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div>
                                        <label for="date_of_birth" class="block text-sm font-semibold text-gray-700 mb-1">Date of Birth</label>
                                        <input type="date" id="date_of_birth" name="date_of_birth"
                                               value="<?php echo $student['date_of_birth']; ?>"
                                               class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
                                    </div>
                                    <div>
                                        <label for="parent_contact" class="block text-sm font-semibold text-gray-700 mb-1">Parent Contact</label>
                                        <input type="text" id="parent_contact" name="parent_contact"
                                               value="<?php echo htmlspecialchars($student['parent_contact'] ?? ''); ?>"
                                               class="w-full px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:bg-white transition-colors">
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-gray-100 flex items-center justify-between">
                                <p class="text-xs text-gray-500">
                                    <i class="fas fa-asterisk text-red-500 mr-1"></i> Required fields
                                </p>
                                <div class="flex gap-3">
                                    <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 shadow-sm shadow-blue-200 transition-all flex items-center gap-2">
                                        <i class="fas fa-save"></i> Save Changes
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
