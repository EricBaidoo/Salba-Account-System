<?php
/**
 * Standalone Student Data Exporter
 * This script connects directly to MySQL and exports student data to CSV
 * Run this from command line: php export_students.php
 */

// Database connection settings - update these to match your local database
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'accounting';

try {
    // Try different connection methods
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Successfully connected to database!\n";
    
    // Get all student data
    $stmt = $pdo->query("SELECT * FROM students ORDER BY student_id");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($students)) {
        echo "No students found in the database.\n";
        exit;
    }
    
    echo "Found " . count($students) . " students.\n";
    
    // Create CSV file
    $filename = 'students_export_' . date('Y-m-d_H-i-s') . '.csv';
    $file = fopen($filename, 'w');
    
    // Add CSV header
    if (!empty($students)) {
        fputcsv($file, array_keys($students[0]));
        
        // Add student data
        foreach ($students as $student) {
            fputcsv($file, $student);
        }
    }
    
    fclose($file);
    
    echo "\nExport completed! File saved as: $filename\n";
    echo "You can now upload this CSV file to your hosted system.\n\n";
    
    // Display sample data
    echo "Sample data (first 3 students):\n";
    echo "================================\n";
    for ($i = 0; $i < min(3, count($students)); $i++) {
        echo "Student " . ($i + 1) . ":\n";
        foreach ($students[$i] as $key => $value) {
            echo "  $key: $value\n";
        }
        echo "\n";
    }
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    echo "\nTrying alternative connection methods...\n";
    
    // Try different ports
    $ports = [3306, 3307, 3308];
    foreach ($ports as $port) {
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            echo "Connected successfully on port $port!\n";
            // If successful, you can copy the export code here
            break;
        } catch (PDOException $e) {
            echo "Port $port failed: " . $e->getMessage() . "\n";
        }
    }
    
    if (!isset($pdo)) {
        echo "\nCould not connect to MySQL. Here are some options:\n";
        echo "1. Start XAMPP Control Panel and start MySQL service\n";
        echo "2. Check if MySQL is running on a different port\n";
        echo "3. Use PHPMyAdmin if the web server is working\n";
        echo "4. Manually re-enter student data in the hosted system\n";
    }
}
?>