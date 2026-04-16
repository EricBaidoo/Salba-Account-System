<?php
/**
 * SALBA Montessori - Staff Data Bulk Importer
 * This script imports staff data from a specific Google Forms CSV export.
 * USAGE: Access via browser or run via CLI for testing.
 */

session_start();
require_once dirname(__FILE__) . '/../../../includes/db_connect.php';
require_once dirname(__FILE__) . '/../../../includes/auth_functions.php';

// Verify admin access
if (php_sapi_name() !== 'cli') {
    if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
        die("Unauthorized access.");
    }
}

$csv_file = 'SALBA MONTESSORI INTERNATIONAL SCHOOL. - STAFF DATA - Form responses 1.csv';
$csv_path = __DIR__ . '/' . $csv_file;

if (!file_exists($csv_path)) {
    die("CSV file not found at: $csv_path");
}

echo "Starting import from: $csv_file\n";

$handle = fopen($csv_path, 'r');
if (!$handle) {
    die("Could not open CSV file.");
}

// Skip header
$header = fgetcsv($handle);
$columns = count($header);
echo "Detected $columns columns in CSV.\n";

$imported = 0;
$skipped = 0;
$errors = [];

$default_password = password_hash('Salba2024', PASSWORD_DEFAULT);

while (($data = fgetcsv($handle)) !== false) {
    // Basic data cleaning
    foreach ($data as $key => $value) {
        $data[$key] = trim($value);
    }

    $full_name = $data[2];
    if (empty($full_name)) {
        echo "Skipping empty name row.\n";
        $skipped++;
        continue;
    }

    // Check for existing staff by name
    $stmt = $conn->prepare("SELECT id FROM staff_profiles WHERE full_name = ? LIMIT 1");
    $stmt->bind_param("s", $full_name);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo "Skipping existing staff: $full_name\n";
        $skipped++;
        continue;
    }
    $stmt->close();

    echo "Importing: $full_name... ";

    // Map CSV data
    $photo_url = $data[1];
    $dob = format_date($data[3]);
    $marital_status = $data[4];
    $emergency_info = $data[5];
    $nationality = $data[6];
    $phone = $data[7];
    $ghana_card = $data[8];
    $ssnit = $data[9];
    $address = $data[10];
    $landmark = $data[11];
    $religion = $data[12];
    $languages = $data[13];
    $qualification = $data[14];
    $entry_qualification = $data[15];
    $appointment_date = format_date($data[16]);
    $guarantor1_name = $data[17];
    $guarantor1_phone = $data[18];
    $guarantor1_loc = $data[19];
    $guarantor2_name = $data[20];
    $guarantor2_phone = $data[21];
    $guarantor2_loc = $data[22];
    $hometown = $data[23];
    $bank_details = $data[24];

    // Split emergency info if possible (Name and Phone)
    $emergency_name = $emergency_info;
    $emergency_phone = '';
    if (preg_match('/^(.*?)[(\s]+(\d+)[)\s]*$/', $emergency_info, $matches)) {
        $emergency_name = trim($matches[1]);
        $emergency_phone = trim($matches[2]);
    }

    // Smart Mapping for Staff Type (Teaching vs Non-Teaching)
    // Job title often includes "Teacher", "Instructor", "Security", etc.
    // In this specific CSV, Job Title isn't a separate column but might be inferred or we'll default based on text.
    // Actually, searching QUALIFICATION or other text for clues.
    $job_title = ''; // Not explicitly in this CSV as a separate column, but might be part of QUALIFICATION or entry.
    // For now, default to teaching unless obvious non-teaching keywords appear in qualification.
    $staff_type = 'teaching';
    $search_text = strtolower($qualification . ' ' . $entry_qualification . ' ' . $full_name);
    if (strpos($search_text, 'security') !== false || 
        strpos($search_text, 'cook') !== false || 
        strpos($search_text, 'cleaner') !== false || 
        strpos($search_text, 'driver') !== false ||
        strpos($search_text, 'maintenance') !== false) {
        $staff_type = 'non-teaching';
    }

    // Insert into staff_profiles (initially with user_id = null)
    $q = "INSERT INTO staff_profiles (
            full_name, date_of_birth, marital_status, nationality, religion, ghanaian_languages,
            phone_number, telephone_number, ghana_card_no, ssnit_number, photo_path,
            address, place_of_stay_address, land_mark, home_town,
            highest_qualification, entry_qualification, first_appointment_date,
            bank_account_details, emergency_name, emergency_phone,
            guarantor1_name, guarantor1_phone, guarantor1_address,
            guarantor2_name, guarantor2_phone, guarantor2_address,
            staff_type, employment_status
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active')";
    
    // Note: My inspection showed phone_number AND telephone_number exist.
    // Inspection also showed address AND place_of_stay_address.
    
    $stmt = $conn->prepare("INSERT INTO staff_profiles (
        full_name, date_of_birth, marital_status, nationality, religion, ghanaian_languages,
        phone_number, telephone_number, ghana_card_no, ssnit_number, photo_path,
        address, place_of_stay_address, land_mark, home_town,
        highest_qualification, entry_qualification, first_appointment_date,
        bank_account_details, emergency_name, emergency_phone,
        guarantor1_name, guarantor1_phone, guarantor1_address,
        guarantor2_name, guarantor2_phone, guarantor2_address,
        staff_type, employment_status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active')");

    $stmt->bind_param(
        "ssssssssssssssssssssssssssss",
        $full_name, $dob, $marital_status, $nationality, $religion, $languages,
        $phone, $phone, $ghana_card, $ssnit, $photo_url,
        $address, $address, $landmark, $hometown,
        $qualification, $entry_qualification, $appointment_date,
        $bank_details, $emergency_name, $emergency_phone,
        $guarantor1_name, $guarantor1_phone, $guarantor1_loc,
        $guarantor2_name, $guarantor2_phone, $guarantor2_loc,
        $staff_type
    );

    if ($stmt->execute()) {
        $staff_id = $stmt->insert_id;

        // Generate Staff ID (SMISXXX-YY)
        $year_suffix = $appointment_date ? date('y', strtotime($appointment_date)) : date('y');
        $staff_code = 'SMIS' . str_pad($staff_id, 3, '0', STR_PAD_LEFT) . '-' . $year_suffix;
        $conn->query("UPDATE staff_profiles SET staff_code = '$staff_code' WHERE id = $staff_id");

        // Create User Account
        $username = generate_username($full_name);
        $user_role = 'staff'; // Default role

        $u_stmt = $conn->prepare("INSERT INTO users (username, password, role, is_active, staff_id) VALUES (?,?,?,1,?)");
        $u_stmt->bind_param("sssi", $username, $default_password, $user_role, $staff_id);
        
        if ($u_stmt->execute()) {
            $user_id = $u_stmt->insert_id;
            // Link user back to profile
            $conn->query("UPDATE staff_profiles SET user_id = $user_id WHERE id = $staff_id");
            echo "Success (ID: $staff_code, User: $username)\n";
            $imported++;
        } else {
            echo "Profile created, but user creation failed: " . $conn->error . "\n";
            $errors[] = "User creation failed for $full_name";
        }
        $u_stmt->close();
    } else {
        echo "Failed: " . $conn->error . "\n";
        $errors[] = "Import failed for $full_name: " . $conn->error;
    }
    $stmt->close();
}

fclose($handle);

echo "\n Import Summary:\n";
echo "----------------\n";
echo "Imported: $imported\n";
echo "Skipped:  $skipped\n";
echo "Errors:   " . count($errors) . "\n";

if (!empty($errors)) {
    echo "\nDetailed Errors:\n";
    foreach ($errors as $e) echo "- $e\n";
}

// --- Helper Functions ---

/**
 * Convert DD/MM/YYYY to YYYY-MM-DD
 */
function format_date($date_str) {
    if (empty($date_str) || $date_str === '01/01/0001' || $date_str === '0' || $date_str === '-') return null;
    
    // Try DD/MM/YYYY
    if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $date_str, $m)) {
        return sprintf("%04d-%02d-%02d", $m[3], $m[2], $m[1]);
    }
    
    // Fallback try strtotime
    $time = strtotime($date_str);
    return $time ? date('Y-m-d', $time) : null;
}

/**
 * Generate a clean username from full name
 */
function generate_username($name) {
    global $conn;
    $base = strtolower(preg_replace('/[^a-zA-Z]/', '', $name));
    if (empty($base)) $base = 'staff' . rand(100, 999);
    
    $username = $base;
    $count = 1;
    
    while (true) {
        $chk = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($chk->num_rows === 0) break;
        $username = $base . $count++;
    }
    
    return $username;
}
?>
