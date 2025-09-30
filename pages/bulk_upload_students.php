<?php
include '../includes/auth_check.php';
include '../includes/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csvFile'])) {
    $uploadedFile = $_FILES['csvFile'];
    
    // Validate file
    if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload error.";
    } elseif (pathinfo($uploadedFile['name'], PATHINFO_EXTENSION) !== 'csv') {
        $error = "Please upload a CSV file.";
    } else {
        // Process CSV file
        $handle = fopen($uploadedFile['tmp_name'], 'r');
        
        if ($handle) {
            $success_count = 0;
            $error_count = 0;
            $errors = [];
            $row_number = 0;
            
            // Skip header row
            $header = fgetcsv($handle);
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                $row_number++;
                
                // Validate required fields
                if (empty($data[0]) || empty($data[1]) || empty($data[2])) {
                    $error_count++;
                    $errors[] = "Row $row_number: Missing required fields (first_name, last_name, class)";
                    continue;
                }
                
                $first_name = trim($data[0]);
                $last_name = trim($data[1]);
                $class = trim($data[2]);
                $date_of_birth = !empty($data[3]) ? $data[3] : null;
                $parent_contact = !empty($data[4]) ? trim($data[4]) : null;
                
                // Insert into database
                $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, class, date_of_birth, parent_contact) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("sssss", $first_name, $last_name, $class, $date_of_birth, $parent_contact);
                
                if ($stmt->execute()) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = "Row $row_number: Database error - " . $stmt->error;
                }
                $stmt->close();
            }
            
            fclose($handle);
        } else {
            $error = "Could not read the uploaded file.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Upload Results - Salba Montessori Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap me-2"></i>
                <strong>Salba Montessori</strong>
            </a>
        </div>
    </nav>

    <div class="container mt-5">
        <div class="page-header text-center mb-5">
            <h1><i class="fas fa-upload me-3"></i>Bulk Upload Results</h1>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                        <div>
                            <h5>Upload Failed</h5>
                            <p class="mb-0"><?php echo htmlspecialchars($error); ?></p>
                        </div>
                    </div>
                <?php elseif (isset($success_count)): ?>
                    <!-- Success Summary -->
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Upload Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="row text-center">
                                <div class="col-md-4">
                                    <h3 class="text-success"><?php echo $success_count; ?></h3>
                                    <p class="text-muted">Students Added</p>
                                </div>
                                <div class="col-md-4">
                                    <h3 class="text-danger"><?php echo $error_count; ?></h3>
                                    <p class="text-muted">Errors</p>
                                </div>
                                <div class="col-md-4">
                                    <h3 class="text-info"><?php echo $success_count + $error_count; ?></h3>
                                    <p class="text-muted">Total Processed</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Error Details -->
                    <?php if (!empty($errors)): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Error Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-warning">
                                    <small>The following rows could not be processed:</small>
                                </div>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($errors as $error_msg): ?>
                                        <li class="list-group-item text-danger">
                                            <i class="fas fa-times-circle me-2"></i>
                                            <?php echo htmlspecialchars($error_msg); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Action Buttons -->
                <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                    <a href="add_student_form.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add More Students
                    </a>
                    <a href="view_students.php" class="btn btn-success">
                        <i class="fas fa-eye me-2"></i>View All Students
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i>Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>