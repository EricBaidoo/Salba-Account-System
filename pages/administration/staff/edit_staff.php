<?php
session_start();
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/staff_migration.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../login'); exit;
}
run_staff_migration($conn);

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: view_staff.php'); exit; }

$res = $conn->query("SELECT * FROM staff_profiles WHERE id = $id LIMIT 1");
if (!$res || $res->num_rows === 0) { header('Location: view_staff.php'); exit; }
$s = $res->fetch_assoc();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!isset($_POST['csrf_token']) || !verify_csrf($_POST['csrf_token'])) {
        die("Security Token Error. Please refresh the page and try again.");
    }
    $staff_code             = trim($_POST['staff_code'] ?? '');
    $full_name              = trim($_POST['full_name'] ?? '');
    $gender                 = $_POST['gender'] ?? ($s['gender'] ?? 'Male');
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
    
    // Status
    $employment_status      = $_POST['employment_status'] ?? ($s['employment_status'] ?? 'active');

    // Categorization
    $functional_areas = $_POST['staff_type'] ?? [];
    $staff_type       = !empty($functional_areas) ? implode(',', $functional_areas) : 'teaching';

    if (!$full_name) $errors[] = 'Full Name is required.';

    if (!empty($staff_code)) {
        $chk = $conn->prepare("SELECT id FROM staff_profiles WHERE staff_code = ? AND id != ? LIMIT 1");
        $chk->bind_param('si', $staff_code, $id);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $errors[] = "Staff ID '$staff_code' is already assigned to another member.";
        }
        $chk->close();
    }

    // Handle new photo upload (optional)
    $photo_path = $s['photo_path'] ?? '';
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
            UPDATE staff_profiles SET
                full_name=?, date_of_birth=?, marital_status=?, nationality=?, religion=?, languages_spoken=?,
                phone_number=?, ghana_card_no=?, ssnit_number=?, address=?, landmark=?, hometown=?,
                job_title=?, department=?, highest_qualification=?, entry_qualification=?, first_appointment_date=?,
                bank_details=?, emergency_contact=?,
                guarantor1_name=?, guarantor1_phone=?, guarantor1_address=?,
                guarantor2_name=?, guarantor2_phone=?, guarantor2_address=?,
                photo_path=?, staff_code=?, staff_type=?, gender=?, employment_status=?
            WHERE id=?
        ");
        $stmt->bind_param(
            "ssssssssssssssssssssssssssssssi",
            $full_name, $date_of_birth, $marital_status, $nationality, $religion, $languages_spoken,
            $phone_number, $ghana_card_no, $ssnit_number, $address, $landmark, $hometown,
            $job_title, $department, $highest_qualification, $entry_qualification, $first_appointment_date,
            $bank_details, $emergency_contact,
            $guarantor1_name, $guarantor1_phone, $guarantor1_address,
            $guarantor2_name, $guarantor2_phone, $guarantor2_address,
            $photo_path, $staff_code, $staff_type, $gender, $employment_status, $id
        );
        if ($stmt->execute()) {
            header("Location: profile_staff.php?id=$id&success=Profile+updated+successfully");
            exit;
        } else {
            $errors[] = 'Database error: ' . $conn->error;
        }
    }

    // Re-merge POST data into $s for form re-population
    $s = array_merge($s, $_POST);
}

$v = fn($key) => htmlspecialchars($s[$key] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Staff – <?= $v('full_name') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .fi { width:100%; padding:10px 14px; border:1.5px solid #e8e8f0; border-radius:10px; font-size:14px; font-weight:500; color:#1f2937; background:#fafafa; transition:all .2s; outline:none; }
        .fi:focus { border-color:#6366f1; background:white; box-shadow:0 0 0 3px rgba(99,102,241,.08); }
        .fi::placeholder { color:#c4c6d5; font-weight:400; }
        .fl { display:block; font-size:11px; font-weight:700; color:#8b8fa8; text-transform:uppercase; letter-spacing:.06em; margin-bottom:6px; }
        .card { background:white; border-radius:16px; border:1px solid #f0f0f5; box-shadow:0 1px 4px rgba(0,0,0,.04); overflow:hidden; }
        .card-header { display:flex; align-items:center; gap:12px; padding:16px 24px; border-bottom:1px solid #f5f5fa; }
        .card-icon { width:34px; height:34px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:14px; flex-shrink:0; }
        .photo-box { width:110px; height:130px; border:2.5px dashed #d1d5db; border-radius:12px; display:flex; flex-direction:column; align-items:center; justify-content:center; cursor:pointer; overflow:hidden; background:#f8f9fc; transition:all .2s; }
        .photo-box:hover { border-color:#6366f1; background:#f0f0ff; }
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-8">
            <div>
                <a href="profile_staff.php?id=<?= $id ?>" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-wider flex items-center gap-1 mb-2 w-fit">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
                <h1 class="text-3xl font-extrabold text-gray-900">Edit Staff Profile</h1>
                <p class="text-gray-500 mt-1 font-medium">Updating record for <span class="text-indigo-600 font-bold"><?= $v('full_name') ?></span></p>
            </div>
        </div>

        <?php if(!empty($errors)): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl mb-6 flex items-start gap-3">
                <i class="fas fa-exclamation-triangle text-red-500 mt-0.5"></i>
                <ul class="list-disc pl-2 text-sm font-medium space-y-1">
                    <?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <!-- ── SECTION 1: Personal ─── -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon bg-indigo-100"><i class="fas fa-user text-indigo-600"></i></div>
                    <div><h2 class="font-bold text-gray-900">Personal Information</h2><p class="text-xs text-gray-400 font-medium">Biographical and identity details</p></div>
                </div>
                <div class="p-6">
                    <div class="flex gap-8">
                        <!-- Photo -->
                        <div class="flex-shrink-0">
                            <label class="fl mb-2">Passport / Photo</label>
                            <label for="photo_input" class="photo-box">
                                <?php if(!empty($s['photo_path'])): ?>
                                    <img id="photo_preview" src="../../../<?= $v('photo_path') ?>" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <img id="photo_preview" class="w-full h-full object-cover hidden">
                                    <div id="photo_placeholder" class="flex flex-col items-center justify-center text-center p-2">
                                        <i class="fas fa-camera text-2xl text-gray-300 mb-2"></i>
                                        <span class="text-[10px] text-gray-400 font-bold">Change photo</span>
                                    </div>
                                <?php endif; ?>
                            </label>
                            <input type="file" name="photo" id="photo_input" accept=".jpg,.jpeg,.png,.webp" class="sr-only" onchange="previewPhoto(event)">
                            <p class="text-[10px] text-gray-400 mt-1 text-center font-medium">Leave blank to keep current</p>
                        </div>

                        <div class="flex-1 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                            <div class="lg:col-span-2">
                                <label class="fl">Full Name <span class="text-red-500">*</span></label>
                                <input type="text" name="full_name" class="fi" value="<?= $v('full_name') ?>" required>
                            </div>
                            <div>
                                <label class="fl">Staff ID</label>
                                <input type="text" name="staff_code" class="fi" value="<?= $v('staff_code') ?>" placeholder="e.g. SMIS001-25">
                            </div>
                            <div>
                                <label class="fl">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" class="fi" required>
                                    <option value="Male" <?= ($s['gender'] ?? 'Male') === 'Male' ? 'selected' : '' ?>>Male</option>
                                    <option value="Female" <?= ($s['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                                    <option value="Other" <?= ($s['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                            <div>
                                <label class="fl">Date of Birth</label>
                                <input type="date" name="date_of_birth" class="fi" value="<?= $v('date_of_birth') ?>">
                            </div>
                            <div>
                                <label class="fl">Marital Status</label>
                                <select name="marital_status" class="fi">
                                    <option value="">-- Select --</option>
                                    <?php foreach(['Single','Married','Divorced','Widowed'] as $ms): ?>
                                        <option value="<?= $ms ?>" <?= ($s['marital_status'] ?? '') === $ms ? 'selected' : '' ?>><?= $ms ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="fl">Nationality</label>
                                <input type="text" name="nationality" class="fi" value="<?= $v('nationality') ?>">
                            </div>
                            <div>
                                <label class="fl">Telephone Number</label>
                                <input type="tel" name="phone_number" class="fi" value="<?= $v('phone_number') ?>">
                            </div>
                            <div>
                                <label class="fl">Ghana Card No.</label>
                                <input type="text" name="ghana_card_no" class="fi" value="<?= $v('ghana_card_no') ?>">
                            </div>
                            <div>
                                <label class="fl">SSNIT Number</label>
                                <input type="text" name="ssnit_number" class="fi" value="<?= $v('ssnit_number') ?>">
                            </div>
                            <div>
                                <label class="fl">Religion</label>
                                <input type="text" name="religion" class="fi" value="<?= $v('religion') ?>">
                            </div>
                            <div>
                                <label class="fl">Languages Spoken</label>
                                <input type="text" name="languages_spoken" class="fi" value="<?= $v('languages_spoken') ?>">
                            </div>
                            <div>
                                <label class="fl">Hometown</label>
                                <input type="text" name="hometown" class="fi" value="<?= $v('hometown') ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── SECTION 2: Address ─── -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon bg-green-100"><i class="fas fa-map-marker-alt text-green-600"></i></div>
                    <div><h2 class="font-bold text-gray-900">Place of Stay & Address</h2><p class="text-xs text-gray-400 font-medium">Current residential details</p></div>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div class="md:col-span-2">
                        <label class="fl">Home Address</label>
                        <textarea name="address" rows="2" class="fi" style="resize:none;"><?= $v('address') ?></textarea>
                    </div>
                    <div>
                        <label class="fl">Landmark</label>
                        <input type="text" name="landmark" class="fi" value="<?= $v('landmark') ?>">
                    </div>
                </div>
            </div>

            <!-- ── SECTION 3: Employment ─── -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon bg-blue-100"><i class="fas fa-briefcase text-blue-600"></i></div>
                    <div><h2 class="font-bold text-gray-900">Employment Details</h2><p class="text-xs text-gray-400 font-medium">Qualifications and work history</p></div>
                </div>
                <div class="p-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                    <div>
                        <label class="fl">Job Title / Role</label>
                        <select name="job_title" class="fi">
                            <option value="">-- Select Role --</option>
                            <?php foreach([
                                'Headmaster / Headmistress', 'Administrator / Manager', 'Class Teacher', 
                                'Assistant Teacher', 'Finance Officer / Accountant', 'Secretary / Front Desk', 
                                'IT / System Admin', 'Security', 'Facility Support (Cleaner / Caretaker)', 'Driver'
                            ] as $role): ?>
                                <option value="<?= $role ?>" <?= $v('job_title') === $role ? 'selected' : '' ?>><?= $role ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="fl">Functional Areas <span class="text-red-500">*</span></label>
                        <div class="flex items-center gap-4 py-2">
                            <?php 
                                $current_types = explode(',', $s['staff_type'] ?? 'teaching');
                                $current_types = array_map('trim', $current_types);
                            ?>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="staff_type[]" value="teaching" class="w-4 h-4 text-indigo-600 rounded border-gray-300 focus:ring-indigo-500" <?= in_array('teaching', $current_types) ? 'checked' : '' ?>>
                                <span class="text-xs font-bold text-gray-700 group-hover:text-indigo-600 transition">Teaching</span>
                            </label>
                            <label class="flex items-center gap-2 cursor-pointer group">
                                <input type="checkbox" name="staff_type[]" value="non-teaching" class="w-4 h-4 text-orange-600 rounded border-gray-300 focus:ring-orange-500" <?= in_array('non-teaching', $current_types) ? 'checked' : '' ?>>
                                <span class="text-xs font-bold text-gray-700 group-hover:text-orange-600 transition">Non-Teaching</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="fl">Department</label>
                        <select name="department" class="fi">
                            <option value="">-- Select --</option>
                            <?php foreach(['Teaching Staff','Administration','Support Staff','Finance','Security'] as $d): ?>
                                <option value="<?= $d ?>" <?= ($s['department'] ?? '') === $d ? 'selected' : '' ?>><?= $d ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="fl">Highest Qualification</label>
                        <input type="text" name="highest_qualification" class="fi" value="<?= $v('highest_qualification') ?>">
                    </div>
                    <div>
                        <label class="fl">Entry Qualification</label>
                        <input type="text" name="entry_qualification" class="fi" value="<?= $v('entry_qualification') ?>">
                    </div>
                    <div>
                        <label class="fl">Date of 1st Appointment</label>
                        <input type="date" name="first_appointment_date" class="fi" value="<?= $v('first_appointment_date') ?>">
                    </div>
                    <div>
                        <label class="fl">Employment Status</label>
                        <select name="employment_status" class="fi">
                            <?php foreach(['active' => 'Active', 'inactive' => 'Inactive', 'retired' => 'Retired'] as $val => $lbl): ?>
                                <option value="<?= $val ?>" <?= ($s['employment_status'] ?? 'active') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ── SECTION 4: Bank ─── -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon bg-yellow-100"><i class="fas fa-university text-yellow-600"></i></div>
                    <div><h2 class="font-bold text-gray-900">Bank Account Details</h2><p class="text-xs text-gray-400 font-medium">Bank Name, Account Number and Branch</p></div>
                </div>
                <div class="p-6">
                    <div>
                        <label class="fl">Bank Details Summary</label>
                        <textarea name="bank_details" rows="2" class="fi" placeholder="Bank Name, Account Number and Branch" style="resize:none;"><?= $v('bank_details') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ── SECTION 5: Emergency ─── -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon bg-red-100"><i class="fas fa-phone-alt text-red-600"></i></div>
                    <div><h2 class="font-bold text-gray-900">Emergency Contact</h2><p class="text-xs text-gray-400 font-medium">Name and Phone Number</p></div>
                </div>
                <div class="p-6">
                    <div>
                        <label class="fl">Contact Name & Phone</label>
                        <textarea name="emergency_contact" rows="2" class="fi" placeholder="Primary emergency contact name and phone" style="resize:none;"><?= $v('emergency_contact') ?></textarea>
                    </div>
                </div>
            </div>

            <!-- ── SECTION 6: Guarantors ─── -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon bg-purple-100"><i class="fas fa-user-shield text-purple-600"></i></div>
                    <div><h2 class="font-bold text-gray-900">Guarantors</h2><p class="text-xs text-gray-400 font-medium">Two guarantors required for employment verification</p></div>
                </div>
                <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="bg-purple-50 p-5 rounded-xl border border-purple-100 space-y-4">
                        <h3 class="font-bold text-sm text-purple-800 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-6 h-6 bg-purple-600 text-white rounded-full text-xs flex items-center justify-center font-extrabold">1</span> First Guarantor
                        </h3>
                        <div><label class="fl">Full Name</label><input type="text" name="guarantor1_name" class="fi" value="<?= $v('guarantor1_name') ?>"></div>
                        <div><label class="fl">Phone Number</label><input type="tel" name="guarantor1_phone" class="fi" value="<?= $v('guarantor1_phone') ?>"></div>
                        <div><label class="fl">Home Address & Location</label><textarea name="guarantor1_address" rows="2" class="fi" style="resize:none;"><?= $v('guarantor1_address') ?></textarea></div>
                    </div>
                    <div class="bg-indigo-50 p-5 rounded-xl border border-indigo-100 space-y-4">
                        <h3 class="font-bold text-sm text-indigo-800 uppercase tracking-wider flex items-center gap-2">
                            <span class="w-6 h-6 bg-indigo-600 text-white rounded-full text-xs flex items-center justify-center font-extrabold">2</span> Second Guarantor
                        </h3>
                        <div><label class="fl">Full Name</label><input type="text" name="guarantor2_name" class="fi" value="<?= $v('guarantor2_name') ?>"></div>
                        <div><label class="fl">Phone Number</label><input type="tel" name="guarantor2_phone" class="fi" value="<?= $v('guarantor2_phone') ?>"></div>
                        <div><label class="fl">Home Address & Location</label><textarea name="guarantor2_address" rows="2" class="fi" style="resize:none;"><?= $v('guarantor2_address') ?></textarea></div>
                    </div>
                </div>
            </div>

            <!-- Submit Bar -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 sticky bottom-4">
                <p class="text-sm text-gray-400 font-medium"><i class="fas fa-info-circle text-gray-300 mr-1"></i> Changes are saved immediately to the HR database.</p>
                <div class="flex gap-3">
                    <a href="profile_staff.php?id=<?= $id ?>" class="px-6 py-2.5 border border-gray-200 text-gray-600 font-bold rounded-xl hover:bg-gray-50 transition text-sm">Cancel</a>
                    <button type="submit" class="px-8 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl shadow-sm transition text-sm flex items-center gap-2">
                        <i class="fas fa-save"></i> Save Changes
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
                if (placeholder) placeholder.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
