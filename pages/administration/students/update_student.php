<?php
include '../../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../../../includes/db_connect.php';

$success = false;
$error = '';
$student_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $class = trim($_POST['class']);
    $status = trim($_POST['status']);
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $parent_contact = !empty($_POST['parent_contact']) ? trim($_POST['parent_contact']) : null;

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($class) || empty($status)) {
        $error = "First name, last name, class, and status are required fields.";
    } elseif (!in_array($status, ['active', 'inactive'])) {
        $error = "Invalid status selected.";
    } else {
        // Update student
        $stmt = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, class = ?, status = ?, date_of_birth = ?, parent_contact = ? WHERE id = ?");
        $stmt->bind_param("ssssssi", $first_name, $last_name, $class, $status, $date_of_birth, $parent_contact, $student_id);
        
        if ($stmt->execute()) {
            $success = true;
            $student_data = [
                'id' => $student_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'class' => $class,
                'status' => $status,
                'date_of_birth' => $date_of_birth,
                'parent_contact' => $parent_contact
            ];
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
} else {
    $error = "Invalid request method.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Student Result - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/../../../css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/../../../css/all.min.css">
    .student-details {
            background: linear-gradient(145deg, #f8f9ff 0%, #e8f0fe 100%);
            border-radius: 0.9375rem;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .action-px-3 py-2 rounded {
            border-radius: 1.5625rem;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .action-px-3 py-2 rounded:hover {
            transform: translateY(-0.125rem);
            box-shadow: 0 0.3125rem 0.9375rem rgba(0, 0, 0, 0.2);
        }
        
        .fade-in {
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(1.875rem);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="clean-page">

    <div class="clean-page-header">
        <div class="w-full px-4">
            <div class="flex justify-between items-center mb-">
                <a href="view_students.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-save mr-2"></i>Update Student Result</h1>
            </div>
        </div>
    </div>

    <div class="w-full px-4 py-4">
        <div class="flex flex-wrap justify-center">
            <div class="col-lg-7">
                <?php if ($success): ?>
                    <div class="bg-white rounded shadow result-bg-white rounded shadow fade-in">
                        <div class="bg-white rounded shadow-header success-header text-center py-4">
                            <i class="fas fa-check-circle success-icon fa-3x mb-"></i>
                            <h3 class="mb-">Student Updated Successfully!</h3>
                        </div>
                        <div class="bg-white rounded shadow-body text-center p-4">
                            <h4 class="text-primary mb-">
                                <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?>
                            </h4>
                            
                            <div class="student-details">
                                <div class="flex flex-wrap text-left">
                                    <div class="md:col-span-6">
                                        <p class="mb-">
                                            <i class="fas fa-id-badge text-primary mr-2"></i>
                                            <strong>Student ID:</strong> #<?php echo $student_data['id']; ?>
                                        </p>
                                        <p class="mb-">
                                            <i class="fas fa-graduation-cap text-primary mr-2"></i>
                                            <strong>Class:</strong> <?php echo htmlspecialchars($student_data['class']); ?>
                                        </p>
                                        <p class="mb-">
                                            <i class="fas fa-toggle-on text-primary mr-2"></i>
                                            <strong>Status:</strong> 
                                            <?php if ($student_data['status'] === 'active'): ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">Active</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">Inactive</span>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <div class="md:col-span-6">
                                        <?php if ($student_data['date_of_birth']): ?>
                                        <p class="mb-">
                                            <i class="fas fa-birthday-cake text-primary mr-2"></i>
                                            <strong>Date of Birth:</strong> <?php echo date('F j, Y', strtotime($student_data['date_of_birth'])); ?>
                                        </p>
                                        <?php endif; ?>
                                        <?php if ($student_data['parent_contact']): ?>
                                        <p class="mb-">
                                            <i class="fas fa-phone text-primary mr-2"></i>
                                            <strong>Parent Contact:</strong> <?php echo htmlspecialchars($student_data['parent_contact']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid gap-3 md:flex md:justify-center mt-4">
                                <a href="view_students.php" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 action-px-3 py-2 rounded">
                                    <i class="fas fa-users mr-2"></i>View All Students
                                </a>
                                <a href="edit_student_form.php?id=<?php echo $student_data['id']; ?>" class="px-4 py-2 border border-gray-300 rounded action-px-3 py-2 rounded">
                                    <i class="fas fa-edit mr-2"></i>Edit Again
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded shadow result-bg-white rounded shadow fade-in">
                        <div class="bg-white rounded shadow-header error-header text-center py-4">
                            <i class="fas fa-exclamation-triangle error-icon fa-3x mb-"></i>
                            <h3 class="mb-">Update Failed</h3>
                        </div>
                        <div class="bg-white rounded shadow-body text-center p-4">
                            <div class="p-4 bg-red-100 text-red-700 rounded border border-red-200 flex items-center">
                                <i class="fas fa-exclamation-circle mr-3"></i>
                                <div>
                                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="text-gray-600 mb-">What would you like to do?</h6>
                                <div class="grid gap-3 md:flex md:justify-center">
                                    <a href="javascript:history.back()" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 action-px-3 py-2 rounded">
                                        <i class="fas fa-redo mr-2"></i>Try Again
                                    </a>
                                    <a href="view_students.php" class="px-4 py-2 border border-gray-300 rounded action-px-3 py-2 rounded">
                                        <i class="fas fa-users mr-2"></i>All Students
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Navigation -->
                <div class="text-center mt-4">
                    <div class="flex justify-center gap-3">
                        <a href="view_students.php" class="px-3 py-2 rounded px-3 py-2 rounded-outline-primary">
                            <i class="fas fa-list mr-2"></i>All Students
                        </a>
                        <a href="add_student_form.php" class="px-3 py-2 rounded px-3 py-2 rounded-outline-success">
                            <i class="fas fa-user-plus mr-2"></i>Add Student
                        </a>
                        <a href="../dashboard.php" class="px-4 py-2 border border-gray-300 rounded">
                            <i class="fas fa-home mr-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

        <script>
        // Auto-redirect after successful update
        <?php if ($success): ?>
        setTimeout(function() {
            window.location.href = 'view_students.php';
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>
