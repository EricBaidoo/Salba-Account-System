<?php
/**
 * Student Data Migration Script
 * Upload students from local database to Hostinger
 */

include 'includes/db_connect.php';
include 'includes/auth_functions.php';

// Check if user is logged in
if (!is_logged_in()) {
    header('Location: pages/login.php');
    exit;
}

echo '<html><head><title>Student Data Migration</title>';
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
echo '</head><body class="container mt-4">';

echo '<h1>📚 Student Data Migration</h1>';

// Sample student data - replace this with your actual student data
$sample_students = [
    ['John', 'Doe', 'KG 1', '2018-05-15', '0241234567'],
    ['Jane', 'Smith', 'KG 2', '2017-08-22', '0502345678'],
    ['Michael', 'Johnson', 'Basic 1', '2016-12-03', '0203456789'],
    // Add more students here...
];

if (isset($_POST['upload_students'])) {
    echo '<h2>🚀 Uploading Students...</h2>';
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($sample_students as $student) {
        $stmt = $conn->prepare("INSERT INTO students (first_name, last_name, class, date_of_birth, parent_contact) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $student[0], $student[1], $student[2], $student[3], $student[4]);
        
        if ($stmt->execute()) {
            echo "<p>✅ Added: {$student[0]} {$student[1]} ({$student[2]})</p>";
            $success_count++;
        } else {
            echo "<p>❌ Error adding {$student[0]} {$student[1]}: " . $conn->error . "</p>";
            $error_count++;
        }
    }
    
    echo "<div class='alert alert-success'>";
    echo "<h3>📊 Upload Summary</h3>";
    echo "<p>✅ Successfully added: <strong>$success_count</strong> students</p>";
    echo "<p>❌ Errors: <strong>$error_count</strong></p>";
    echo "</div>";
    
    echo "<p><a href='pages/view_students.php' class='btn btn-primary'>View Students</a></p>";
    
} else {
    // Show upload form
    echo '<div class="alert alert-info">';
    echo '<h3>📋 Instructions:</h3>';
    echo '<ol>';
    echo '<li><strong>Edit this script:</strong> Replace the sample data with your actual student data</li>';
    echo '<li><strong>Format:</strong> [FirstName, LastName, Class, DateOfBirth(YYYY-MM-DD), ParentContact]</li>';
    echo '<li><strong>Upload:</strong> Click the button below to add all students</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '<div class="alert alert-warning">';
    echo '<h4>⚠️ Current Sample Data (' . count($sample_students) . ' students):</h4>';
    echo '<ul>';
    foreach ($sample_students as $student) {
        echo "<li>{$student[0]} {$student[1]} - {$student[2]} (DOB: {$student[3]}, Contact: {$student[4]})</li>";
    }
    echo '</ul>';
    echo '</div>';
    
    echo '<form method="POST">';
    echo '<button type="submit" name="upload_students" class="btn btn-success btn-lg">📤 Upload All Students</button>';
    echo '</form>';
    
    echo '<hr>';
    echo '<h3>🔄 Alternative Methods:</h3>';
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<div class="card">';
    echo '<div class="card-body">';
    echo '<h5>🔢 Manual Entry</h5>';
    echo '<p>Add students one by one through the system</p>';
    echo '<a href="pages/add_student_form.php" class="btn btn-primary">Add Students Manually</a>';
    echo '</div></div></div>';
    
    echo '<div class="col-md-6">';
    echo '<div class="card">';
    echo '<div class="card-body">';
    echo '<h5>📊 CSV Import</h5>';
    echo '<p>Use the built-in CSV bulk upload feature</p>';
    echo '<a href="pages/bulk_upload_students.php" class="btn btn-info">Bulk CSV Upload</a>';
    echo '</div></div></div>';
    echo '</div>';
}

echo '</body></html>';
?>