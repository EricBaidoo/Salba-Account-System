<?php
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: ../pages/login.php');
    exit;
}
include '../includes/db_connect.php';

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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .result-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: none;
            overflow: hidden;
        }
        
        .success-header {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        
        .error-header {
            background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
            color: white;
        }
        
        .success-icon {
            animation: successPulse 2s ease-in-out infinite;
        }
        
        .error-icon {
            animation: shake 0.82s cubic-bezier(.36,.07,.19,.97) both;
        }
        
        @keyframes successPulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }
        
        .student-details {
            background: linear-gradient(145deg, #f8f9ff 0%, #e8f0fe 100%);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
        }
        
        .action-btn {
            border-radius: 25px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
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
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <strong>Salba Montessori</strong>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="view_students.php">
                    <i class="fas fa-arrow-left me-2"></i>Back to Students
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <?php if ($success): ?>
                    <div class="card result-card fade-in">
                        <div class="card-header success-header text-center py-4">
                            <i class="fas fa-check-circle success-icon fa-3x mb-3"></i>
                            <h3 class="mb-0">Student Updated Successfully!</h3>
                        </div>
                        <div class="card-body text-center p-4">
                            <h4 class="text-primary mb-3">
                                <?php echo htmlspecialchars($student_data['first_name'] . ' ' . $student_data['last_name']); ?>
                            </h4>
                            
                            <div class="student-details">
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
                                        <p class="mb-2">
                                            <i class="fas fa-toggle-on text-primary me-2"></i>
                                            <strong>Status:</strong> 
                                            <?php if ($student_data['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
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
                                <a href="view_students.php" class="btn btn-primary action-btn">
                                    <i class="fas fa-users me-2"></i>View All Students
                                </a>
                                <a href="edit_student_form.php?id=<?php echo $student_data['id']; ?>" class="btn btn-outline-secondary action-btn">
                                    <i class="fas fa-edit me-2"></i>Edit Again
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card result-card fade-in">
                        <div class="card-header error-header text-center py-4">
                            <i class="fas fa-exclamation-triangle error-icon fa-3x mb-3"></i>
                            <h3 class="mb-0">Update Failed</h3>
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
                                    <a href="javascript:history.back()" class="btn btn-primary action-btn">
                                        <i class="fas fa-redo me-2"></i>Try Again
                                    </a>
                                    <a href="view_students.php" class="btn btn-outline-secondary action-btn">
                                        <i class="fas fa-users me-2"></i>All Students
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Navigation -->
                <div class="text-center mt-4">
                    <div class="d-flex justify-content-center gap-3">
                        <a href="view_students.php" class="btn btn-outline-primary">
                            <i class="fas fa-list me-2"></i>All Students
                        </a>
                        <a href="add_student_form.php" class="btn btn-outline-success">
                            <i class="fas fa-user-plus me-2"></i>Add Student
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
        // Auto-redirect after successful update
        <?php if ($success): ?>
        setTimeout(function() {
            window.location.href = 'view_students.php';
        }, 3000);
        <?php endif; ?>
    </script>
</body>
</html>