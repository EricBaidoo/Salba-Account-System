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
    $first_name        = trim($_POST['first_name'] ?? '');
    $last_name         = trim($_POST['last_name'] ?? '');
    $class             = trim($_POST['class'] ?? '');
    $date_of_birth     = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $place_of_birth    = trim($_POST['place_of_birth'] ?? '');
    $date_admitted     = !empty($_POST['date_admitted']) ? $_POST['date_admitted'] : null;
    $previous_school   = trim($_POST['previous_school'] ?? '');
    $address           = trim($_POST['address'] ?? '');
    $landmark          = trim($_POST['landmark'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    
    // Parent Info
    $existing_mother_id = $_POST['existing_mother_id'] ?? '';
    $mother_name    = trim($_POST['mother_name'] ?? '');
    $mother_contact = trim($_POST['mother_contact'] ?? '');
    
    $existing_father_id = $_POST['existing_father_id'] ?? '';
    $father_name    = trim($_POST['father_name'] ?? '');
    $father_contact = trim($_POST['father_contact'] ?? '');

    // For backwards compatibility on old forms/lists, use the first available contact as the student's primary contact
    $parent_contact_fallback = !empty($mother_contact) ? $mother_contact : (!empty($father_contact) ? $father_contact : $emergency_contact);

    if (empty($first_name) || empty($last_name) || empty($class)) {
        $_SESSION['flash_error'] = "Required fields are missing.";
        header('Location: add_student_form');
        exit;
    }

    // Photo Upload
    $photo_path = '';
    if (!empty($_FILES['photo']['name'])) {
        $upload_dir = '../../../assets/uploads/students/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
            $filename = 'student_' . time() . '_' . rand(100,999) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename);
            $photo_path = 'assets/uploads/students/' . $filename;
        }
    }

    try {
        $conn->begin_transaction();

        // 1. Insert Student
        $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, class, date_of_birth, parent_contact, status, address, place_of_birth, emergency_contact, photo_path, landmark, date_admitted, previous_school) VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssssss", $first_name, $last_name, $class, $date_of_birth, $parent_contact_fallback, $address, $place_of_birth, $emergency_contact, $photo_path, $landmark, $date_admitted, $previous_school);
        
        if (!$stmt->execute()) {
            throw new Exception("Student Insert Error: " . $stmt->error);
        }
        $student_id = $conn->insert_id;
        $stmt->close();

        // 2. Handle Mother
        $primary_set = false;
        if (!empty($existing_mother_id)) {
            $link_m = $conn->query("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES ($student_id, " . intval($existing_mother_id) . ", 'Mother')");
        } elseif (!empty($mother_name)) {
            $is_primary = (!empty($mother_contact) && !$primary_set) ? 1 : 0;
            if ($is_primary) $primary_set = true;
            
            $m_stmt = $conn->prepare("INSERT INTO parents (title, first_name, last_name, phone, address, is_primary) VALUES ('Mrs.', '', ?, ?, ?, ?)");
            $m_stmt->bind_param("sssi", $mother_name, $mother_contact, $address, $is_primary);
            $m_stmt->execute();
            $mother_id = $conn->insert_id;
            $m_stmt->close();

            $link_m = $conn->query("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES ($student_id, $mother_id, 'Mother')");
        }

        // 3. Handle Father
        if (!empty($existing_father_id)) {
            $link_f = $conn->query("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES ($student_id, " . intval($existing_father_id) . ", 'Father')");
        } elseif (!empty($father_name)) {
            $is_primary = (!empty($father_contact) && !$primary_set) ? 1 : 0;
            
            $f_stmt = $conn->prepare("INSERT INTO parents (title, first_name, last_name, phone, address, is_primary) VALUES ('Mr.', '', ?, ?, ?, ?)");
            $f_stmt->bind_param("sssi", $father_name, $father_contact, $address, $is_primary);
            $f_stmt->execute();
            $father_id = $conn->insert_id;
            $f_stmt->close();

            $link_f = $conn->query("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES ($student_id, $father_id, 'Father')");
        }

        $conn->commit();

        $_SESSION['last_student_registered'] = [
            'id' => $student_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'class' => $class
        ];
        log_activity($conn, 'Admissions', "New student enrolled: $first_name $last_name (#$student_id)");
        header('Location: add_student');
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['flash_error'] = $e->getMessage();
        header('Location: add_student_form');
        exit;
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
