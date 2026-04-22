<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include_once '../../../includes/system_settings.php';

// Enforce admin only
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

$success_data = $_SESSION['last_student_registered'] ?? null;
unset($_SESSION['last_student_registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $parent_contact = !empty($_POST['parent_contact']) ? trim($_POST['parent_contact']) : null;

    if (empty($first_name) || empty($last_name) || empty($class)) {
        $_SESSION['flash_error'] = "Required fields are missing.";
        header('Location: add_student_form');
        exit;
    } else {
        $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, class, date_of_birth, parent_contact, status) VALUES (?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("sssss", $first_name, $last_name, $class, $date_of_birth, $parent_contact);
        
        if ($stmt->execute()) {
            $student_id = $conn->insert_id;
            $_SESSION['last_student_registered'] = [
                'id' => $student_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'class' => $class
            ];
            log_activity($conn, 'Admissions', "New student enrolled: $first_name $last_name (#$student_id)");
            header('Location: add_student');
            exit;
        } else {
            $_SESSION['flash_error'] = "Database error: " . $stmt->error;
            header('Location: add_student_form');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enrollment Result - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen flex items-center justify-center p-8">
        <div class="max-w-md w-full">
            <?php if ($success_data): ?>
                <div class="bg-white rounded-[2.5rem] shadow-2xl shadow-indigo-200/50 border border-indigo-50 overflow-hidden text-center p-10 animate-fade-in">
                    <div class="w-24 h-24 bg-indigo-600 text-white rounded-[2rem] flex items-center justify-center mx-auto mb-8 text-4xl shadow-lg shadow-indigo-200">
                        <i class="fas fa-user-check"></i>
                    </div>
                    
                    <h2 class="text-2xl font-black text-gray-900 mb-2">Student Enrolled</h2>
                    <p class="text-gray-500 font-medium mb-8">Successfully created record for <br><span class="text-indigo-600 font-bold"><?php echo htmlspecialchars($success_data['first_name'] . ' ' . $success_data['last_name']); ?></span></p>

                    <div class="space-y-3">
                        <a href="view_students" class="block w-full bg-gray-900 text-white py-4 rounded-2xl font-black text-[0.6875rem] uppercase tracking-widest hover:bg-emerald-600 transition-all active:scale-95">
                            View Directory
                        </a>
                        <a href="add_student_form" class="block w-full bg-indigo-50 text-indigo-600 py-4 rounded-2xl font-black text-[0.6875rem] uppercase tracking-widest hover:bg-indigo-100 transition-all active:scale-95">
                            Enroll Another
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-[2.5rem] shadow-xl p-12 text-center border border-slate-100">
                    <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-[2rem] flex items-center justify-center mx-auto mb-6 text-3xl">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h2 class="text-xl font-black text-slate-900 mb-2">Registration Module</h2>
                    <p class="text-slate-500 text-sm mb-8 font-medium">No recent enrollment data found.</p>
                    <a href="add_student_form" class="inline-flex items-center gap-2 px-8 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition-all">
                        <i class="fas fa-arrow-left"></i> Go to Form
                    </a>
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
