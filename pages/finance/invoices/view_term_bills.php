<?php
include '../../includes/db_connect.php';
include '../../includes/auth_functions.php';
if (!is_logged_in()) {
    header('Location: login.php');
    exit;
}

// Get all terms that have assigned fees
$terms_query = "SELECT DISTINCT semester FROM student_fees WHERE status != 'cancelled' ORDER BY 
    CASE semester 
        WHEN 'First Semester' THEN 1
        WHEN 'Second Semester' THEN 2
        WHEN 'Third Semester' THEN 3
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
    <title>View Semester Bills - Salba Montessori</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body>
    <div class="max-w-7xl mx-auto mt-5">
        <div class="mb-">
            <a href="../dashboard.php" class="px-3 py-2 rounded px-3 py-2 rounded-outline-primary"><i class="fas fa-arrow-left mr-1"></i>Back to Dashboard</a>
        </div>
        
        <div class="page-header rounded shadow-sm mb- p-4 text-center">
            <h2 class="mb-"><i class="fas fa-file-invoice mr-2"></i>View & Reprint Semester Bills</h2>
            <p class="lead mb-">Access previously generated bills for printing</p>
        </div>

        <div class="flex flex-wrap gap-4">
            <!-- Bulk Bills by Semester/Class -->
            <div class="md:col-span-6">
                <div class="bg-white rounded shadow shadow-sm h-full">
                    <div class="bg-white rounded shadow-header bg-primary text-white">
                        <h5 class="mb-"><i class="fas fa-users mr-2"></i>Print Bulk Bills</h5>
                    </div>
                    <div class="bg-white rounded shadow-body">
                        <p>Generate bills for all students in a specific semester/class</p>
                        <form method="GET" action="term_invoice.php" target="_blank">
                            <div class="mb-">
                                <label for="bulk_term" class="block text-sm font-medium mb- fw-bold">
                                    <i class="fas fa-calendar-alt mr-1"></i>Select Semester *
                                </label>
                                <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="bulk_term" name="semester" required>
                                    <option value="">Choose Semester...</option>
                                    <?php 
                                    $terms_result->data_seek(0);
                                    while ($semester = $terms_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($semester['semester']); ?>">
                                            <?php echo htmlspecialchars($semester['semester']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-">
                                <label for="bulk_class" class="block text-sm font-medium mb- fw-bold">
                                    <i class="fas fa-school mr-1"></i>Filter by Class
                                </label>
                                <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="bulk_class" name="class">
                                    <option value="all">All Classes</option>
                                    <?php while ($class = $classes_result->fetch_assoc()): ?>
                                        <option value="<?php echo htmlspecialchars($class['name']); ?>">
                                            <?php echo htmlspecialchars($class['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 w-full">
                                <i class="fas fa-print mr-2"></i>View & Print Bulk Bills
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Individual Student Bill -->
            <div class="md:col-span-6">
                <div class="bg-white rounded shadow shadow-sm h-full">
                    <div class="bg-white rounded shadow-header bg-success text-white">
                        <h5 class="mb-"><i class="fas fa-user mr-2"></i>Print Individual Bill</h5>
                    </div>
                    <div class="bg-white rounded shadow-body">
                        <p>Select a student to view and print their bill</p>
                        <form method="GET" action="term_invoice.php" target="_blank">
                            <div class="mb-">
                                <label for="student_term" class="block text-sm font-medium mb- fw-bold">
                                    <i class="fas fa-calendar-alt mr-1"></i>Select Semester *
                                </label>
                                <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="student_term" name="semester" required>
                                    <option value="">Choose Semester...</option>
                                    <?php 
                                    $terms_result->data_seek(0);
                                    while ($semester = $terms_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo htmlspecialchars($semester['semester']); ?>">
                                            <?php echo htmlspecialchars($semester['semester']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="mb-">
                                <label for="student_id" class="block text-sm font-medium mb- fw-bold">
                                    <i class="fas fa-user-graduate mr-1"></i>Select Student *
                                </label>
                                <select class="border border-gray-300 rounded px-3 py-2 bg-white" id="student_id" name="student_id" required>
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
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 w-full">
                                <i class="fas fa-print mr-2"></i>View & Print Student Bill
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-info mt-4">
            <i class="fas fa-info-circle mr-2"></i>
            <strong>Note:</strong> Bills will open in a new window/tab ready for printing. 
            You can generate bills as many times as needed - the system will always show the current balance.
        </div>
    </div>

    </body>
</html>

