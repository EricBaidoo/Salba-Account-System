<?php 
include '../includes/auth_check.php';
include '../includes/db_connect.php';

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
    <title>Edit Student - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
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
                <h1 class="clean-page-title"><i class="fas fa-user-edit me-2"></i>Edit Student Information</h1>
                <p class="clean-page-subtitle">Update student details and manage their status</p>
            </div>
        </div>
    </div>

    <div class="container-fluid px-4 py-4">

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($error): ?>
                    <div class="clean-alert clean-alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                    <div class="text-center">
                        <a href="view_students.php" class="btn-clean-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Students
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Current Student Info -->
                    <div class="student-info-card">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="text-primary mb-2">
                                    <i class="fas fa-user me-2"></i>Editing: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                </h5>
                                <p class="mb-0 text-muted">
                                    Student ID: #<?php echo $student['id']; ?> | 
                                    Current Class: <?php echo htmlspecialchars($student['class']); ?> | 
                                    Status: 
                                    <?php if ($student['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="avatar-placeholder avatar-md bg-primary text-white mx-auto">
                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="edit-wizard">
                        <div class="p-4">
                            <form action="update_student.php" method="POST" id="editStudentForm">
                                <input type="hidden" name="student_id" value="<?php echo $student['id']; ?>">
                                
                                <!-- Required Fields Section -->
                                <div class="mb-4">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-star me-2"></i>Required Information
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                                       value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                                                <label for="first_name" class="required-field">First Name</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                                       value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                                                <label for="last_name" class="required-field">Last Name</label>
                                            </div>
                                        </div>
                                        <div class="col-md-8">
                                            <div class="form-floating">
                                                <select class="form-select" id="class" name="class" required>
                                                    <option value="">Select Class/Grade</option>
                                                    <option value="Creche" <?php echo $student['class'] === 'Creche' ? 'selected' : ''; ?>>Creche</option>
                                                    <option value="Nursery 1" <?php echo $student['class'] === 'Nursery 1' ? 'selected' : ''; ?>>Nursery 1</option>
                                                    <option value="Nursery 2" <?php echo $student['class'] === 'Nursery 2' ? 'selected' : ''; ?>>Nursery 2</option>
                                                    <option value="KG 1" <?php echo $student['class'] === 'KG 1' ? 'selected' : ''; ?>>KG 1</option>
                                                    <option value="KG 2" <?php echo $student['class'] === 'KG 2' ? 'selected' : ''; ?>>KG 2</option>
                                                    <option value="Basic 1" <?php echo $student['class'] === 'Basic 1' ? 'selected' : ''; ?>>Basic 1</option>
                                                    <option value="Basic 2" <?php echo $student['class'] === 'Basic 2' ? 'selected' : ''; ?>>Basic 2</option>
                                                    <option value="Basic 3" <?php echo $student['class'] === 'Basic 3' ? 'selected' : ''; ?>>Basic 3</option>
                                                    <option value="Basic 4" <?php echo $student['class'] === 'Basic 4' ? 'selected' : ''; ?>>Basic 4</option>
                                                    <option value="Basic 5" <?php echo $student['class'] === 'Basic 5' ? 'selected' : ''; ?>>Basic 5</option>
                                                    <option value="Basic 6" <?php echo $student['class'] === 'Basic 6' ? 'selected' : ''; ?>>Basic 6</option>
                                                    <option value="Basic 7" <?php echo $student['class'] === 'Basic 7' ? 'selected' : ''; ?>>Basic 7</option>
                                                </select>
                                                <label for="class" class="required-field">Class/Grade</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating">
                                                <select class="form-select" id="status" name="status" required>
                                                    <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                </select>
                                                <label for="status" class="required-field">Status</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Optional Fields Section -->
                                <div class="mb-4">
                                    <h5 class="text-secondary mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Additional Information
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                                       value="<?php echo $student['date_of_birth']; ?>">
                                                <label for="date_of_birth">Date of Birth</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-floating">
                                                <input type="text" class="form-control" id="parent_contact" name="parent_contact" 
                                                       value="<?php echo htmlspecialchars($student['parent_contact']); ?>">
                                                <label for="parent_contact">Parent Contact</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-exclamation-triangle me-1"></i>
                                        Fields marked with <span class="text-danger fw-bold">*</span> are required
                                    </small>
                                    <div class="gap-2 d-flex">
                                        <a href="view_students.php" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-2"></i>Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Student
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="text-center mt-4">
            <div class="d-flex justify-content-center gap-3">
                <a href="view_students.php" class="btn btn-outline-primary">
                    <i class="fas fa-users me-2"></i>All Students
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('editStudentForm').addEventListener('submit', function(e) {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const studentClass = document.getElementById('class').value;

            if (!firstName || !lastName || !studentClass) {
                e.preventDefault();
                alert('Please fill in all required fields: First Name, Last Name, and Class.');
                return false;
            }
        });
    </script>
</body>
</html>