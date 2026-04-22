<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include_once '../../../includes/system_settings.php';

$success = false;
$error = '';
$student_id = 0;
$student_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $parent_contact = !empty($_POST['parent_contact']) ? trim($_POST['parent_contact']) : null;

    if (empty($first_name) || empty($last_name) || empty($class) || empty($status)) {
        $error = "Required fields are missing.";
    } else {
        $stmt = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, class = ?, status = ?, date_of_birth = ?, parent_contact = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $first_name, $last_name, $class, $status, $date_of_birth, $parent_contact, $student_id);
        
        if ($stmt->execute()) {
            $success = true;
            $student_name = "$first_name $last_name";
            log_activity($conn, 'Admissions', "Updated student profile: $student_name (#$student_id)");
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    header('Location: view_students');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Result - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen flex items-center justify-center p-8">
        <div class="max-w-md w-full">
            <?php if ($success): ?>
                <div class="bg-white rounded-[2.5rem] shadow-2xl shadow-emerald-200/50 border border-emerald-50 overflow-hidden text-center p-10 animate-fade-in">
                    <div class="w-24 h-24 bg-emerald-500 text-white rounded-[2rem] flex items-center justify-center mx-auto mb-8 text-4xl shadow-lg shadow-emerald-200">
                        <i class="fas fa-check"></i>
                    </div>
                    
                    <h2 class="text-2xl font-black text-gray-900 mb-2">Profile Updated</h2>
                    <p class="text-gray-500 font-medium mb-8">Successfully updated information for <br><span class="text-emerald-600 font-bold"><?php echo htmlspecialchars($student_name); ?></span></p>

                    <div class="space-y-3">
                        <a href="view_students" class="block w-full bg-gray-900 text-white py-4 rounded-2xl font-black text-[0.6875rem] uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95">
                            Return to Directory
                        </a>
                        <a href="edit_student_form?id=<?php echo $student_id; ?>" class="block w-full bg-gray-50 text-gray-500 py-4 rounded-2xl font-black text-[0.6875rem] uppercase tracking-widest hover:bg-gray-100 transition-all active:scale-95">
                            Edit Again
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-[2.5rem] shadow-2xl shadow-red-200/50 border border-red-50 overflow-hidden text-center p-10">
                    <div class="w-24 h-24 bg-red-500 text-white rounded-[2rem] flex items-center justify-center mx-auto mb-8 text-4xl shadow-lg shadow-red-200">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    
                    <h2 class="text-2xl font-black text-gray-900 mb-2">Update Failed</h2>
                    <p class="text-red-500 font-medium mb-8"><?php echo htmlspecialchars($error); ?></p>

                    <button onclick="history.back()" class="block w-full bg-gray-900 text-white py-4 rounded-2xl font-black text-[0.6875rem] uppercase tracking-widest hover:bg-red-600 transition-all active:scale-95">
                        <i class="fas fa-arrow-left mr-2"></i> Try Again
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(1rem); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s ease-out forwards;
        }
    </style>
</body>
</html>
