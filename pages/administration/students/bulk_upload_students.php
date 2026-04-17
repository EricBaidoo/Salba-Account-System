<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

$success_count = null;
$error_count = null;
$error = null;
$errors = [];

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
    <title>Bulk Upload Results - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6">
            <div class="flex items-center gap-3 mb-4">
                <a href="view_students.php" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-sm font-medium">
                    <i class="fas fa-arrow-left"></i> Back to Directory
                </a>
            </div>
            <div>
                <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-cloud-upload-alt text-green-500"></i> Bulk Upload Results
                </h1>
                <p class="text-gray-500 mt-2 text-sm">
                    Review the processing details of your recent CSV student upload.
                </p>
            </div>
        </div>

        <div class="p-8 max-w-4xl">
            <?php if (isset($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 p-6 rounded-xl flex items-center gap-4 mb-6 shadow-sm">
                    <i class="fas fa-exclamation-triangle text-red-500 text-3xl"></i>
                    <div>
                        <h5 class="font-bold text-lg">Upload Failed</h5>
                        <p class="text-sm mt-1"><?php echo htmlspecialchars($error); ?></p>
                    </div>
                </div>
            <?php elseif ($success_count !== null): ?>
                
                <!-- Summary Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 text-center">
                        <div class="text-3xl font-bold text-emerald-600 mb-1"><?php echo $success_count; ?></div>
                        <div class="text-xs uppercase font-bold text-gray-400 tracking-wider">Students Added</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 text-center">
                        <div class="text-3xl font-bold text-red-500 mb-1"><?php echo $error_count; ?></div>
                        <div class="text-xs uppercase font-bold text-gray-400 tracking-wider">Failed Rows</div>
                    </div>
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 text-center">
                        <div class="text-3xl font-bold text-blue-600 mb-1"><?php echo $success_count + $error_count; ?></div>
                        <div class="text-xs uppercase font-bold text-gray-400 tracking-wider">Total Processed</div>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="bg-white rounded-xl border border-red-100 shadow-sm overflow-hidden mb-8">
                        <div class="bg-red-50/50 px-6 py-4 border-b border-red-100">
                            <h5 class="font-bold text-red-800 flex items-center gap-2">
                                <i class="fas fa-exclamation-circle text-red-500"></i> Error Log Analysis
                            </h5>
                        </div>
                        <div class="p-6">
                            <p class="text-sm text-gray-600 mb-4">The following rows in your CSV could not be imported and were skipped:</p>
                            <ul class="space-y-2">
                                <?php foreach ($errors as $error_msg): ?>
                                    <li class="flex items-start gap-3 text-sm text-red-700 bg-red-50 px-4 py-2 rounded-lg">
                                        <i class="fas fa-times mt-1 text-red-400"></i>
                                        <span><?php echo htmlspecialchars($error_msg); ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-6 rounded-xl flex items-center gap-4 mb-8 shadow-sm">
                        <i class="fas fa-check-circle text-emerald-500 text-3xl"></i>
                        <div>
                            <h5 class="font-bold text-lg">Upload Successful</h5>
                            <p class="text-sm mt-1">All <?php echo $success_count; ?> rows were imported into the database without any errors.</p>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <div class="flex flex-wrap gap-4 pt-4 border-t border-gray-200 mt-4">
                <a href="view_students.php" class="px-5 py-2.5 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors shadow-sm flex items-center gap-2">
                    <i class="fas fa-users"></i> View Directory
                </a>
                <a href="add_student_form.php" class="px-5 py-2.5 bg-white border border-gray-200 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition-colors shadow-sm flex items-center gap-2">
                    <i class="fas fa-undo"></i> Upload Another
                </a>
            </div>
        </div>
    </main>
</body>
</html>
