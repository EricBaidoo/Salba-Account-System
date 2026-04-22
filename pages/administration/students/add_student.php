<?php 
include '../../../includes/auth_functions.php';
include '../../../includes/db_connect.php';

// Session is started in auth_functions.php

$success_data = $_SESSION['last_student_registered'] ?? null;
unset($_SESSION['last_student_registered']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $parent_contact = !empty($_POST['parent_contact']) ? trim($_POST['parent_contact']) : null;

    if (empty($first_name) || empty($last_name) || empty($class)) {
        redirect('add_student.php', 'error', "First name, last name, and class are required fields.");
    } else {
        $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, class, date_of_birth, parent_contact) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $first_name, $last_name, $class, $date_of_birth, $parent_contact);
        
        if ($stmt->execute()) {
            $student_id = $conn->insert_id;
            $_SESSION['last_student_registered'] = [
                'id' => $student_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'class' => $class,
                'date_of_birth' => $date_of_birth,
                'parent_contact' => $parent_contact
            ];
            
            // AUDIT LOG
            log_activity($conn, 'Admissions', "New student enrolled: $first_name $last_name (#$student_id)", null, $_SESSION['last_student_registered']);
            
            redirect('add_student.php', 'success', "Student enrolled successfully.");
        } else {
            redirect('add_student.php', 'error', "Database error: " . $stmt->error);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration Result | Salba Montessori</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        .fade-in { animation: fadeInUp 0.6s ease; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(1.875rem); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/top_nav.php'; ?>

    <main class="p-4 md:p-8 min-h-screen">
        <div class="max-w-xl mx-auto py-12">
            
            <?php if ($success_data): ?>
                <!-- Success Receipt Card -->
                <div class="bg-white rounded-[3rem] shadow-2xl overflow-hidden border border-emerald-100 fade-in">
                    <div class="bg-emerald-600 p-12 text-center text-white relative">
                        <div class="absolute inset-0 opacity-10 bg-[url('https://www.transparenttextures.com/patterns/graphy.png')]"></div>
                        <div class="w-24 h-24 bg-white/20 rounded-[2rem] flex items-center justify-center mx-auto mb-6 text-4xl shadow-inner">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2 class="text-3xl font-black tracking-tight leading-none mb-2">Enrollment Confirmed</h2>
                        <p class="text-emerald-100 font-bold opacity-80 text-sm">Institutional record created successfully</p>
                    </div>
                    
                    <div class="p-12 space-y-8">
                        <div class="text-center">
                            <h3 class="text-2xl font-black text-slate-900 mb-1">
                                <?= htmlspecialchars($success_data['first_name'] . ' ' . $success_data['last_name']) ?>
                            </h3>
                            <span class="px-4 py-1.5 bg-indigo-50 text-indigo-700 rounded-full text-[0.625rem] font-black uppercase tracking-widest border border-indigo-100">
                                Student ID: #<?= $success_data['id'] ?>
                            </span>
                        </div>

                        <div class="grid grid-cols-2 gap-6 pt-6 border-t border-slate-50">
                            <div>
                                <p class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Class Assignment</p>
                                <p class="font-bold text-slate-700"><?= htmlspecialchars($success_data['class']) ?></p>
                            </div>
                            <div>
                                <p class="text-[0.5625rem] font-black text-slate-400 uppercase tracking-widest mb-1">Guardian Contact</p>
                                <p class="font-bold text-slate-700"><?= htmlspecialchars($success_data['parent_contact'] ?: 'Not Provided') ?></p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-3 pt-8">
                            <a href="add_student_form.php" class="w-full bg-slate-900 text-white py-4 rounded-2xl font-black text-[0.625rem] uppercase tracking-widest text-center hover:bg-indigo-600 transition-all shadow-lg active:scale-95">
                                <i class="fas fa-plus mr-2"></i> Enroll Another Student
                            </a>
                            <a href="view_students.php" class="w-full bg-slate-100 text-slate-600 py-4 rounded-2xl font-black text-[0.625rem] uppercase tracking-widest text-center hover:bg-slate-200 transition-all active:scale-95">
                                <i class="fas fa-users mr-2"></i> Return to Directory
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Default state (no action details) -->
                <div class="bg-white rounded-[3rem] shadow-xl p-12 text-center border border-slate-100 fade-in">
                    <div class="w-20 h-20 bg-slate-50 text-slate-300 rounded-[2rem] flex items-center justify-center mx-auto mb-6 text-3xl">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h2 class="text-xl font-black text-slate-900 mb-2">Registration Module</h2>
                    <p class="text-slate-500 text-sm mb-8 font-medium">Please use the enrollment form to register new students.</p>
                    <a href="add_student_form.php" class="inline-flex items-center gap-2 px-8 py-3 bg-indigo-600 text-white rounded-xl font-bold text-sm hover:bg-indigo-700 transition-all">
                        <i class="fas fa-arrow-left"></i> Go to Form
                    </a>
                </div>
            <?php endif; ?>

        </div>
    </main>
</body>
</html>
