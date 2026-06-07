<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include_once '../../../includes/system_settings.php';

$success = false;
$error = '';
$student_id = 0;
$student_name = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id        = intval($_POST['student_id'] ?? 0);
    $first_name        = trim($_POST['first_name'] ?? '');
    $last_name         = trim($_POST['last_name'] ?? '');
    $class             = trim($_POST['class'] ?? '');
    $status            = trim($_POST['status'] ?? 'active');
    $date_of_birth     = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $place_of_birth    = trim($_POST['place_of_birth'] ?? '');
    $date_admitted     = !empty($_POST['date_admitted']) ? $_POST['date_admitted'] : null;
    $previous_school   = trim($_POST['previous_school'] ?? '');
    $address           = trim($_POST['address'] ?? '');
    $landmark          = trim($_POST['landmark'] ?? '');
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');

    // Parent Info
    $existing_mother_id = $_POST['existing_mother_id'] ?? '';
    $mother_name        = trim($_POST['mother_name'] ?? '');
    $mother_contact     = trim($_POST['mother_contact'] ?? '');
    
    $existing_father_id = $_POST['existing_father_id'] ?? '';
    $father_name        = trim($_POST['father_name'] ?? '');
    $father_contact     = trim($_POST['father_contact'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($class) || empty($status)) {
        $error = "Required fields are missing.";
    } else {
        // Fetch current photo_path and parent_contact fallback
        $photo_path = '';
        $parent_contact_fallback = '';
        $curr_res = $conn->query("SELECT photo_path, parent_contact FROM students WHERE id = $student_id");
        if ($curr_res && $curr_row = $curr_res->fetch_assoc()) {
            $photo_path = $curr_row['photo_path'];
            $parent_contact_fallback = $curr_row['parent_contact'];
        }

        // Set parent contact fallback if new ones are typed
        if (!empty($mother_contact)) {
            $parent_contact_fallback = $mother_contact;
        } elseif (!empty($father_contact)) {
            $parent_contact_fallback = $father_contact;
        } elseif (!empty($emergency_contact)) {
            $parent_contact_fallback = $emergency_contact;
        }

        // Photo Upload Handling
        if (!empty($_FILES['photo']['name'])) {
            $upload_dir = '../../../assets/uploads/students/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                $filename = 'student_' . time() . '_' . rand(100,999) . '.' . $ext;
                if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename)) {
                    // Remove old photo if exists
                    if (!empty($photo_path) && file_exists('../../../' . $photo_path)) {
                        @unlink('../../../' . $photo_path);
                    }
                    $photo_path = 'assets/uploads/students/' . $filename;
                }
            }
        }

        try {
            $conn->begin_transaction();

            // 1. Update Student Profile
            $stmt = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, class = ?, status = ?, date_of_birth = ?, parent_contact = ?, address = ?, place_of_birth = ?, emergency_contact = ?, photo_path = ?, landmark = ?, date_admitted = ?, previous_school = ? WHERE id = ?");
            $stmt->bind_param("sssssssssssssi", $first_name, $last_name, $class, $status, $date_of_birth, $parent_contact_fallback, $address, $place_of_birth, $emergency_contact, $photo_path, $landmark, $date_admitted, $previous_school, $student_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Student Update Error: " . $stmt->error);
            }
            $stmt->close();

            // 2. Clear Old Parent-Student Mother/Father Links
            $conn->query("DELETE FROM student_parents WHERE student_id = $student_id AND relationship IN ('Mother', 'Father')");

            // 3. Handle Mother Mappings
            $primary_set = false;
            if (!empty($existing_mother_id)) {
                $conn->query("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES ($student_id, " . intval($existing_mother_id) . ", 'Mother')");
            } elseif (!empty($mother_name)) {
                $is_primary = (!empty($mother_contact) && !$primary_set) ? 1 : 0;
                if ($is_primary) $primary_set = true;
                
                $m_stmt = $conn->prepare("INSERT INTO parents (title, first_name, last_name, phone, address, is_primary) VALUES ('Mrs.', '', ?, ?, ?, ?)");
                $m_stmt->bind_param("sssi", $mother_name, $mother_contact, $address, $is_primary);
                $m_stmt->execute();
                $mother_id = $conn->insert_id;
                $m_stmt->close();

                $conn->query("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES ($student_id, $mother_id, 'Mother')");
            }

            // 4. Handle Father Mappings
            if (!empty($existing_father_id)) {
                $conn->query("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES ($student_id, " . intval($existing_father_id) . ", 'Father')");
            } elseif (!empty($father_name)) {
                $is_primary = (!empty($father_contact) && !$primary_set) ? 1 : 0;
                
                $f_stmt = $conn->prepare("INSERT INTO parents (title, first_name, last_name, phone, address, is_primary) VALUES ('Mr.', '', ?, ?, ?, ?)");
                $f_stmt->bind_param("sssi", $father_name, $father_contact, $address, $is_primary);
                $f_stmt->execute();
                $father_id = $conn->insert_id;
                $f_stmt->close();

                $conn->query("INSERT INTO student_parents (student_id, parent_id, relationship) VALUES ($student_id, $father_id, 'Father')");
            }

            $conn->commit();
            $success = true;
            $student_name = "$first_name $last_name";
            log_activity($conn, 'Admissions', "Updated student profile: $student_name (#$student_id)");

        } catch (Exception $e) {
            $conn->rollback();
            $error = "Transaction failed: " . $e->getMessage();
        }
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
