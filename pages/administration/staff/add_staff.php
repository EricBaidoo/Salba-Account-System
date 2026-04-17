<?php
session_start();
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/staff_migration.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../includes/login.php'); exit;
}

// Run migration to ensure tables exist
run_staff_migration($conn);

$errors = [];
$success = '';

// Handle photo upload
$photo_path = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect all fields
    $staff_code             = trim($_POST['staff_code'] ?? '');
    $full_name              = trim($_POST['full_name'] ?? '');
    $gender                 = $_POST['gender'] ?? 'Male';
    $date_of_birth          = !empty($_POST['date_of_birth']) ? $_POST['date_of_birth'] : null;
    $marital_status         = $_POST['marital_status'] ?? '';
    $nationality            = trim($_POST['nationality'] ?? 'Ghanaian');
    $religion               = trim($_POST['religion'] ?? '');
    $languages_spoken       = trim($_POST['languages_spoken'] ?? '');
    $phone_number           = trim($_POST['phone_number'] ?? '');
    $ghana_card_no          = trim($_POST['ghana_card_no'] ?? '');
    $ssnit_number           = trim($_POST['ssnit_number'] ?? '');
    $address                = trim($_POST['address'] ?? '');
    $landmark               = trim($_POST['landmark'] ?? '');
    $hometown               = trim($_POST['hometown'] ?? '');
    $job_title              = trim($_POST['job_title'] ?? '');
    $department             = trim($_POST['department'] ?? '');
    $highest_qualification  = trim($_POST['highest_qualification'] ?? '');
    $entry_qualification    = trim($_POST['entry_qualification'] ?? '');
    $first_appointment_date = !empty($_POST['first_appointment_date']) ? $_POST['first_appointment_date'] : null;
    $bank_details           = trim($_POST['bank_details'] ?? '');
    $emergency_contact      = trim($_POST['emergency_contact'] ?? '');
    $guarantor1_name        = trim($_POST['guarantor1_name'] ?? '');
    $guarantor1_phone       = trim($_POST['guarantor1_phone'] ?? '');
    $guarantor1_address     = trim($_POST['guarantor1_address'] ?? '');
    $guarantor2_name        = trim($_POST['guarantor2_name'] ?? '');
    $guarantor2_phone       = trim($_POST['guarantor2_phone'] ?? '');
    $guarantor2_address     = trim($_POST['guarantor2_address'] ?? '');
    
    // Categorization
    $functional_areas = $_POST['staff_type'] ?? [];
    $staff_type      = !empty($functional_areas) ? implode(',', $functional_areas) : 'teaching';

    // Validation
    if (!$full_name) $errors[] = 'Full Name is required.';

    if (!empty($staff_code)) {
        // Basic format check if you want to enforce it, or just uniqueness
        if (!preg_match('/^SMIS\d+-\d{2}$/', $staff_code)) {
            // $errors[] = 'Staff ID must follow the format SMIS001-25 (Optional: you can remove this restriction if you want literal custom IDs)';
        }
        
        $chk = $conn->prepare("SELECT id FROM staff_profiles WHERE staff_code = ? LIMIT 1");
        $chk->bind_param('s', $staff_code);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $errors[] = "Staff ID '$staff_code' is already assigned to another member.";
        }
        $chk->close();
    }

    // Photo Upload
    if (!empty($_FILES['photo']['name'])) {
        $upload_dir = '../../../assets/uploads/staff/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','webp'])) {
            $errors[] = 'Photo must be JPG, PNG, or WEBP.';
        } else {
            $filename = 'staff_' . time() . '_' . rand(100,999) . '.' . $ext;
            move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $filename);
            $photo_path = 'assets/uploads/staff/' . $filename;
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO staff_profiles 
            (full_name, date_of_birth, marital_status, nationality, religion, languages_spoken,
             phone_number, ghana_card_no, ssnit_number, address, landmark, hometown,
             job_title, department, highest_qualification, entry_qualification, first_appointment_date,
             bank_details, emergency_contact,
             guarantor1_name, guarantor1_phone, guarantor1_address,
             guarantor2_name, guarantor2_phone, guarantor2_address, photo_path, staff_code, staff_type, gender)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->bind_param(
            "sssssssssssssssssssssssssssss",
            $full_name, $date_of_birth, $marital_status, $nationality, $religion, $languages_spoken,
            $phone_number, $ghana_card_no, $ssnit_number, $address, $landmark, $hometown,
            $job_title, $department, $highest_qualification, $entry_qualification, $first_appointment_date,
            $bank_details, $emergency_contact,
            $guarantor1_name, $guarantor1_phone, $guarantor1_address,
            $guarantor2_name, $guarantor2_phone, $guarantor2_address, $photo_path, $staff_code, $staff_type, $gender
        );
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            
            // If staff_code was empty, generate it now
            if (empty($staff_code)) {
                $join_year = !empty($first_appointment_date) ? date('y', strtotime($first_appointment_date)) : date('y');
                $staff_code = 'SMIS' . str_pad($new_id, 3, '0', STR_PAD_LEFT) . '-' . $join_year;
                $conn->query("UPDATE staff_profiles SET staff_code = '$staff_code' WHERE id = $new_id");
            }
            
            header("Location: view_staff.php?success=Staff+$staff_code+created+successfully");
            exit;
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Staff Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .section-card { background: white; border-radius: 16px; border: 1px solid #f0f0f5; box-shadow: 0 1px 4px rgba(0,0,0,0.04); }
        .section-header { display: flex; align-items: center; gap: 12px; padding: 18px 24px; border-bottom: 1px solid #f5f5fa; }
        .section-icon { width: 36px; height: 36px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
        .field-label { display: block; font-size: 11px; font-weight: 700; color: #8b8fa8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 6px; }
        .field-input { width: 100%; padding: 10px 14px; border: 1.5px solid #e8e8f0; border-radius: 10px; font-size: 14px; font-weight: 500; color: #1f2937; background: #fafafa; transition: all 0.2s; outline: none; }
        .field-input:focus { border-color: #6366f1; background: white; box-shadow: 0 0 0 3px rgba(99,102,241,0.08); }
        .field-input::placeholder { color: #c4c6d5; font-weight: 400; }
        select.field-input { cursor: pointer; }
        .photo-upload { width: 110px; height: 130px; border: 2.5px dashed #d1d5db; border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; overflow: hidden; background: #f8f9fc; }
        .photo-upload:hover { border-color: #6366f1; background: #f0f0ff; }
        .photo-preview { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 min-h-screen p-8">

        <!-- Page Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <a href="view_staff.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-wider flex items-center gap-1 mb-2">
                    <i class="fas fa-arrow-left"></i> Back to Staff Directory
                </a>
                <h1 class="text-3xl font-extrabold text-gray-900">Add New Staff Member</h1>
                <p class="text-gray-500 mt-1 font-medium">Complete the HR profile. System login access can be granted separately.</p>
            </div>
            <div class="bg-amber-50 border border-amber-200 text-amber-800 text-xs font-bold px-4 py-2 rounded-xl flex items-center gap-2">
                <i class="fas fa-info-circle text-amber-500"></i> System login activated separately after profile creation
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl mb-6 flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-red-500 mt-0.5"></i>
                <ul class="list-disc pl-2 text-sm font-medium space-y-1">
                    <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">

            <!-- ── SECTION 1: Personal Information ─────────────────── -->
            <div class="section-card overflow-hidden">
                <div class="section-header">
                    <div class="section-icon bg-indigo-100">
                        <i class="fas fa-user text-indigo-600"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-base">Personal Information</h2>
                        <p class="text-xs text-gray-400 font-medium">Basic biographical and identity details</p>
                    </div>
                </div>
                <div class="p-6">
                    <div class="flex gap-8">
                        <!-- Photo Upload -->
                        <div class="flex-shrink-0">
                            <label class="field-label mb-2">Passport / Photo</label>
                            <label for="photo_input" class="photo-upload group cursor-pointer" id="photo_label">
                                <img id="photo_preview" class="photo-preview hidden">
                                <div id="photo_placeholder" class="flex flex-col items-center justify-center text-center p-2">
                                    <i class="fas fa-camera text-2xl text-gray-300 mb-2 group-hover:text-indigo-400 transition"></i>
                                    <span class="text-[10px] text-gray-400 font-bold group-hover:text-indigo-500">Click to upload</span>
                                </div>
                            </label>
                            <input type="file" name="photo" id="photo_input" accept=".jpg,.jpeg,.png,.webp" class="sr-only" onchange="previewPhoto(event)">
                        </div>

                        <!-- Personal fields grid -->
                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                            <div class="lg:col-span-2">
                                <label class="field-label">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" name="full_name" class="field-input" placeholder="As shown on Ghana Card" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
                            </div>
                            <div>
                                <label class="field-label">Staff ID (Auto-generated if blank)</label>
                                <input type="text" name="staff_code" class="field-input" placeholder="e.g. SMIS001-25" value="<?= htmlspecialchars($_POST['staff_code'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" class="field-input" required>
                                    <option value="Male" <?= ($_POST['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($_POST['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($_POST['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="field-input" value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Marital Status</label>
                                <select name="marital_status" class="field-input">
                                    <option value="">-- Select --</option>
                                    <?php foreach(['Single','Married','Divorced','Widowed'] as $ms): ?>
                                        <option value="<?= $ms ?>" <?= ($_POST['marital_status'] ?? '') === $ms ? 'selected' : '' ?>><?= $ms ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="field-label">Nationality</label>
                                <input type="text" name="nationality" class="field-input" placeholder="Ghanaian" value="<?= htmlspecialchars($_POST['nationality'] ?? 'Ghanaian') ?>">
                            </div>
                            <div>
                                <label class="field-label">Telephone Number</label>
                                <input type="tel" name="phone_number" class="field-input" placeholder="e.g. 0244123456" value="<?= htmlspecialchars($_POST['phone_number'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Ghana Card No.</label>
                                <input type="text" name="ghana_card_no" class="field-input" placeholder="GHA-XXXXXXXXX-X" value="<?= htmlspecialchars($_POST['ghana_card_no'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">SSNIT Number</label>
                                <input type="text" name="ssnit_number" class="field-input" placeholder="SSNIT number" value="<?= htmlspecialchars($_POST['ssnit_number'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Religion</label>
                                <input type="text" name="religion" class="field-input" placeholder="e.g. Christianity, Islam" value="<?= htmlspecialchars($_POST['religion'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Ghanaian Languages Spoken</label>
                                <input type="text" name="languages_spoken" class="field-input" placeholder="e.g. Twi, Ga, Ewe" value="<?= htmlspecialchars($_POST['languages_spoken'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Hometown</label>
                                <input type="text" name="hometown" class="field-input" placeholder="e.g. Kumasi" value="<?= htmlspecialchars($_POST['hometown'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── SECTION 2: Address ─────────────────── -->
            <div class="section-card overflow-hidden">
                <div class="section-header">
                    <div class="section-icon bg-green-100">
                        <i class="fas fa-map-marker-alt text-green-600"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-base">Place of Stay & Address</h2>
                        <p class="text-xs text-gray-400 font-medium">Current residential details</p>
                    </div>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="field-label">Home Address</label>
                        <textarea name="address" rows="2" class="field-input" placeholder="Full residential address" style="resize:none;"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label class="field-label">Landmark</label>
                        <input type="text" name="landmark" class="field-input" placeholder="Nearest landmark" value="<?= htmlspecialchars($_POST['landmark'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- ── SECTION 3: Employment Details ─────────────────── -->
            <div class="section-card overflow-hidden">
                <div class="section-header">
                    <div class="section-icon bg-blue-100">
                        <i class="fas fa-briefcase text-blue-600"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-base">Employment Details</h2>
                        <p class="text-xs text-gray-400 font-medium">Academic qualifications and work history</p>
                    </div>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div>
                        <label class="field-label">Job Title / Role</label>
                        <select name="job_title" class="field-input">
                            <option value="">-- Select Role --</option>
                            <?php foreach([
                                'Headmaster / Headmistress', 'Administrator / Manager', 'Class Teacher', 
                                'Assistant Teacher', 'Finance Officer / Accountant', 'Secretary / Front Desk', 
                                'IT / System Admin', 'Security', 'Facility Support (Cleaner / Caretaker)', 'Driver'
                            ] as $role): ?>
                                <option value="<?= $role ?>" <?= ($_POST['job_title'] ?? '') === $role ? 'selected' : '' ?>><?= $role ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Functional Areas <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4 py-2">
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="staff_type[]" value="teaching" class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" <?= in_array('teaching', $_POST['staff_type'] ?? ['teaching']) ? 'checked' : '' ?>>
                                <span class="text-xs font-bold text-gray-700 group-hover:text-indigo-600 transition">Teaching</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="staff_type[]" value="non-teaching" class="w-4 h-4 text-orange-600 rounded border-gray-300 focus:ring-orange-500" <?= in_array('non-teaching', $_POST['staff_type'] ?? []) ? 'checked' : '' ?>>
                                <span class="text-xs font-bold text-gray-700 group-hover:text-orange-600 transition">Non-Teaching</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="field-label">Department</label>
                        <select name="department" class="field-input">
                            <option value="">-- Select --</option>
                            <?php foreach(['Teaching Staff','Administration','Support Staff','Finance','Security'] as $d): ?>
                                <option value="<?= $d ?>" <?= ($_POST['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Highest Qualification</label>
                        <input type="text" name="highest_qualification" class="field-input" placeholder="e.g. B.Ed., HND, WASSCE" value="<?= htmlspecialchars($_POST['highest_qualification'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="field-label">Entry Qualification</label>
                        <input type="text" name="entry_qualification" class="field-input" placeholder="Qualification at time of joining" value="<?= htmlspecialchars($_POST['entry_qualification'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="field-label">Date of 1st Appointment</label>
                        <input type="date" name="first_appointment_date" class="field-input" value="<?= htmlspecialchars($_POST['first_appointment_date'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- ── SECTION 4: Bank Details ─────────────────── -->
            <div class="section-card overflow-hidden">
                <div class="section-header">
                    <div class="section-icon bg-yellow-100">
                        <i class="fas fa-university text-yellow-600"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-base">Bank Account Details</h2>
                        <p class="text-xs text-gray-400 font-medium">Bank Name, Account Number and Branch</p>
                    </div>
                </div>
                <div class="p-6">
                    <div>
                        <label class="field-label">Bank Details Summary</label>
                        <textarea name="bank_details" rows="2" class="field-input" placeholder="Bank Name, Account Number and Branch" style="resize:none;"><?= htmlspecialchars($_POST['bank_details'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ── SECTION 5: Emergency Contact ─────────────────── -->
            <div class="section-card overflow-hidden">
                <div class="section-header">
                    <div class="section-icon bg-red-100">
                        <i class="fas fa-phone-alt text-red-600"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-base">Emergency Contact</h2>
                        <p class="text-xs text-gray-400 font-medium">Name and Phone Number</p>
                    </div>
                </div>
                <div class="p-6">
                    <div>
                        <label class="field-label">Contact Name & Phone</label>
                        <textarea name="emergency_contact" rows="2" class="field-input" placeholder="Full emergency contact name and phone" style="resize:none;"><?= htmlspecialchars($_POST['emergency_contact'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ── SECTION 6: Guarantors ─────────────────── -->
            <div class="section-card overflow-hidden">
                <div class="section-header">
                    <div class="section-icon bg-purple-100">
                        <i class="fas fa-user-shield text-purple-600"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 text-base">Guarantors</h2>
                        <p class="text-xs text-gray-400 font-medium">Two guarantors required for employment verification</p>
                    </div>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Guarantor 1 -->
                        <div class="bg-purple-50 p-5 rounded-xl border border-purple-100 space-y-4">
                            <h3 class="font-bold text-sm text-purple-800 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-6 h-6 bg-purple-600 text-white rounded-full text-xs flex items-center justify-center font-extrabold">1</span>
                                First Guarantor
                            </h3>
                            <div>
                                <label class="field-label">Full Name</label>
                                <input type="text" name="guarantor1_name" class="field-input" placeholder="Guarantor's full name" value="<?= htmlspecialchars($_POST['guarantor1_name'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Phone Number</label>
                                <input type="tel" name="guarantor1_phone" class="field-input" placeholder="Guarantor's phone" value="<?= htmlspecialchars($_POST['guarantor1_phone'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Home Address & Location</label>
                                <textarea name="guarantor1_address" rows="2" class="field-input" placeholder="Full address and location" style="resize:none;"><?= htmlspecialchars($_POST['guarantor1_address'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <!-- Guarantor 2 -->
                        <div class="bg-indigo-50 p-5 rounded-xl border border-indigo-100 space-y-4">
                            <h3 class="font-bold text-sm text-indigo-800 uppercase tracking-wider flex items-center gap-2">
                                <span class="w-6 h-6 bg-indigo-600 text-white rounded-full text-xs flex items-center justify-center font-extrabold">2</span>
                                Second Guarantor
                            </h3>
                            <div>
                                <label class="field-label">Full Name</label>
                                <input type="text" name="guarantor2_name" class="field-input" placeholder="Guarantor's full name" value="<?= htmlspecialchars($_POST['guarantor2_name'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Phone Number</label>
                                <input type="tel" name="guarantor2_phone" class="field-input" placeholder="Guarantor's phone" value="<?= htmlspecialchars($_POST['guarantor2_phone'] ?? '') ?>">
                            </div>
                            <div>
                                <label class="field-label">Home Address & Location</label>
                                <textarea name="guarantor2_address" rows="2" class="field-input" placeholder="Full address and location" style="resize:none;"><?= htmlspecialchars($_POST['guarantor2_address'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Bar -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center justify-between sticky bottom-4">
                <p class="text-sm text-gray-400 font-medium">
                    <i class="fas fa-lock text-gray-300 mr-1"></i> System login access is granted <strong class="text-gray-600">separately</strong> after saving this profile.
                </p>
                <div class="flex gap-3">
                    <a href="view_staff.php" class="px-6 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition text-sm">Cancel</a>
                    <button type="submit" class="px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm transition text-sm flex items-center gap-2">
                        <i class="fas fa-save"></i> Save Staff Profile
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script>
        function previewPhoto(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('photo_preview');
                const placeholder = document.getElementById('photo_placeholder');
                preview.src = e.target.result;
                preview.classList.remove('hidden');
                placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
