<?php 
include '../includes/auth_check.php';
include '../includes/db_connect.php';

$success = false;
$error = '';
$student_data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $class = trim($_POST['class']);
    $date_of_birth = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $parent_contact = !empty($_POST['parent_contact']) ? trim($_POST['parent_contact']) : null;

    // Validate required fields
    if (empty($first_name) || empty($last_name) || empty($class)) {
        $error = "First name, last name, and class are required fields.";
    } else {
        $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, class, date_of_birth, parent_contact) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $first_name, $last_name, $class, $date_of_birth, $parent_contact);
        
        if ($stmt->execute()) {
            $success = true;
            $student_id = $conn->insert_id;
            $student_data = [
                'id' => $student_id,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'class' => $class,
                'date_of_birth' => $date_of_birth,
                'parent_contact' => $parent_contact
            ];
        } else {
            $error = "Database error: " . $stmt->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration Result - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            border: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .fade-in {
            animation: fadeInUp 0.6s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body class="clean-page">

    <!-- Clean Page Header -->
    <div class="clean-page-header">
        <div class="container-fluid px-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <a href="view_students.php" class="clean-back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Students
                </a>
            </div>
            <div class="text-center">
                <h1 class="clean-page-title"><i class="fas fa-user-check me-2"></i>Student Registration</h1>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">\n        <div class="row justify-content-center">\n            <div class="col-lg-7">\n                <?php if ($success): ?>\n                    <div class="clean-card fade-in">\n                        <div class="p-5 text-center success-header">\n                            <i class="fas fa-check-circle text-success fa-4x mb-3"></i>\n                            <h3 class="mb-0">Student Enrolled Successfully!</h3>\n                        </div>\n                        <div class="p-4 text-center">\n                            <h4 class="text-primary mb-3">\n                                <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?>\n                            </h4>\n                            \n                            <div class="student-details">
                                <div class="row text-start">
                                    <div class="col-md-6">
                                        <p class="mb-2">
                                            <i class="fas fa-id-badge text-primary me-2"></i>
                                            <strong>Student ID:</strong> #<?php echo $student_data['id']; ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="fas fa-graduation-cap text-primary me-2"></i>
                                            <strong>Class:</strong> <?php echo htmlspecialchars($student_data['class']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($student_data['date_of_birth']): ?>
                                        <p class="mb-2">
                                            <i class="fas fa-birthday-cake text-primary me-2"></i>
                                            <strong>Date of Birth:</strong> <?php echo date('F j, Y', strtotime($student_data['date_of_birth'])); ?>
                                        </p>
                                        <?php endif; ?>
                                        <?php if ($student_data['parent_contact']): ?>
                                        <p class="mb-2">
                                            <i class="fas fa-phone text-primary me-2"></i>
                                            <strong>Parent Contact:</strong> <?php echo htmlspecialchars($student_data['parent_contact']); ?>
                                        </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-3 d-md-flex justify-content-md-center mt-4">
                                <a href="add_student_form.php" class="btn btn-primary action-btn">
                                    <i class="fas fa-user-plus me-2"></i>Add Another Student
                                </a>
                                <a href="view_students.php" class="btn btn-success action-btn">
                                    <i class="fas fa-users me-2"></i>View All Students
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card result-card fade-in">
                        <div class="card-header error-header text-center py-4">
                            <i class="fas fa-exclamation-triangle error-icon fa-3x mb-3"></i>
                            <h3 class="mb-0">Registration Failed</h3>
                        </div>
                        <div class="card-body text-center p-4">
                            <div class="alert alert-danger d-flex align-items-center">
                                <i class="fas fa-exclamation-circle me-3"></i>
                                <div>
                                    <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <h6 class="text-muted mb-3">What would you like to do?</h6>
                                <div class="d-grid gap-3 d-md-flex justify-content-md-center">
                                    <a href="add_student_form.php" class="btn btn-primary action-btn">
                                        <i class="fas fa-redo me-2"></i>Try Again
                                    </a>
                                    <a href="dashboard.php" class="btn btn-outline-secondary action-btn">
                                        <i class="fas fa-home me-2"></i>Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Navigation -->
                <div class="text-center mt-4">
                    <div class="d-flex justify-content-center gap-3">
                        <a href="add_student_form.php" class="btn btn-outline-primary">
                            <i class="fas fa-user-plus me-2"></i>New Student
                        </a>
                        <a href="view_students.php" class="btn btn-outline-success">
                            <i class="fas fa-list me-2"></i>All Students
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-2"></i>Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect to add another student after success (optional)
        <?php if ($success): ?>
        setTimeout(function() {
            // Optional: Show a toast notification after 3 seconds
            const toast = document.createElement('div');
            toast.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong class="me-auto">Quick Tip</strong>
                    </div>
                    <div class="toast-body">
                        Student successfully added to the system. You can now assign fees or add more students.
                    </div>
                </div>
            `;
            document.body.appendChild(toast);
        }, 2000);
        <?php endif; ?>
    </script>
</body>
</html>