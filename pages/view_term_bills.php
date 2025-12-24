<?php
include '../includes/db_connect.php';
include '../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Get all terms that have assigned fees
$terms_query = "SELECT DISTINCT term FROM student_fees WHERE status != 'cancelled' ORDER BY 
    CASE term 
        WHEN 'First Term' THEN 1
        WHEN 'Second Term' THEN 2
        WHEN 'Third Term' THEN 3
        ELSE 4
    END";
$terms_result = $conn->query($terms_query);

// Get all classes
$classes_result = $conn->query("SELECT DISTINCT name FROM classes ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Term Bills - Salba Montessori</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container mt-5">
        <div class="mb-3">
            <a href="dashboard.php" class="btn btn-outline-primary"><i class="fas fa-arrow-left me-1"></i>Back to Dashboard</a>
        </div>
        
        <div class="page-header rounded shadow-sm mb-4 p-4 text-center">
            <h2 class="mb-2"><i class="fas fa-file-invoice me-2"></i>View & Reprint Term Bills</h2>
            <p class="lead mb-0">Access previously generated bills for printing</p>
        </div>

        <div class="row g-4">
            <!-- Bulk Bills by Term/Class -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-users me-2"></i>Print Bulk Bills</h5>
                    </div>
                    <div class="card-body">
                        <p>Generate bills for all students in a specific term/class</p>
                        <form method="GET" action="term_invoice.php" target="_blank">
                            <div class="mb-3">
                                <label for="bulk_term" class="form-label fw-bold">
                                    <i class="fas fa-calendar-alt me-1"></i>Select Term *
                                </label>
                                <select class="form-select" id="bulk_term" name="term" required>
                                    <option value="">Choose Term...</option>
                                    <?php 
                                    $terms_result->data_seek(0);
                                    while ($term = $terms_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($term['term']); ?>">
                                            <?php echo htmlspecialchars($term['term']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="bulk_class" class="form-label fw-bold">
                                    <i class="fas fa-school me-1"></i>Filter by Class
                                </label>
                                <select class="form-select" id="bulk_class" name="class">
                                    <option value="all">All Classes</option>
                                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($class['name']); ?>">
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-print me-2"></i>View & Print Bulk Bills
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Individual Student Bill -->
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-user me-2"></i>Print Individual Bill</h5>
                    </div>
                    <div class="card-body">
                        <p>Select a student to view and print their bill</p>
                        <form method="GET" action="term_invoice.php" target="_blank">
                            <div class="mb-3">
                                <label for="student_term" class="form-label fw-bold">
                                    <i class="fas fa-calendar-alt me-1"></i>Select Term *
                                </label>
                                <select class="form-select" id="student_term" name="term" required>
                                    <option value="">Choose Term...</option>
                                    <?php 
                                    $terms_result->data_seek(0);
                                    while ($term = $terms_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($term['term']); ?>">
                                            <?php echo htmlspecialchars($term['term']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="student_id" class="form-label fw-bold">
                                    <i class="fas fa-user-graduate me-1"></i>Select Student *
                                </label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Choose Student...</option>
                                    <?php
                                    $students_query = "SELECT id, first_name, last_name, class FROM students WHERE status = 'active' ORDER BY class, first_name, last_name";
                                    $students_result = $conn->query($students_query);
                                    while ($student = $students_result->fetch_assoc()):
                                    ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['class'] . ')'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-print me-2"></i>View & Print Student Bill
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Note:</strong> Bills will open in a new window/tab ready for printing. 
            You can generate bills as many times as needed - the system will always show the current balance.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
