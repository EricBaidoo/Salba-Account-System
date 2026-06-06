<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

// Enforce admin only for enrollment
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// Ensure new columns exist in the students table
$columns_to_add = [
    'address' => 'TEXT NULL',
    'place_of_birth' => 'VARCHAR(255) NULL',
    'emergency_contact' => 'VARCHAR(100) NULL',
    'photo_path' => 'VARCHAR(255) NULL',
    'landmark' => 'VARCHAR(255) NULL',
    'date_admitted' => 'DATE NULL',
    'previous_school' => 'VARCHAR(255) NULL'
];
foreach ($columns_to_add as $col => $type) {
    $check = $conn->query("SHOW COLUMNS FROM `students` LIKE '$col'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE `students` ADD COLUMN `$col` $type");
    }
}

// Fetch all classes for the class dropdown
$classes_result = $conn->query("SELECT name FROM classes ORDER BY id ASC");
$class_options = [];
while ($row = $classes_result->fetch_assoc()) {
    $class_options[] = $row['name'];
}

// Fetch all parents for the select dropdowns
$parents_result = $conn->query("SELECT id, title, first_name, last_name, phone FROM parents ORDER BY last_name ASC, first_name ASC");
$parents_list = [];
while ($row = $parents_result->fetch_assoc()) {
    $parents_list[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollment - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .section-card { background: white; border-radius: 1rem; border: 0.0625rem solid #f0f0f5; box-shadow: 0 0.0625rem 0.25rem rgba(0,0,0,0.04); }
        .section-header { display: flex; align-items: center; gap: 0.75rem; padding: 1.125rem 1.5rem; border-bottom: 0.0625rem solid #f5f5fa; }
        .section-icon { width: 2.25rem; height: 2.25rem; border-radius: 0.625rem; display: flex; align-items: center; justify-content: center; font-size: 0.9375rem; flex-shrink: 0; }
        .field-label { display: block; font-size: 0.6875rem; font-weight: 700; color: #8b8fa8; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.375rem; }
        .field-input { width: 100%; padding: 0.625rem 0.875rem; border: 0.09375rem solid #e8e8f0; border-radius: 0.625rem; font-size: 0.875rem; font-weight: 500; color: #1f2937; background: #fafafa; transition: all 0.2s; outline: none; }
        .field-input:focus { border-color: #6366f1; background: white; box-shadow: 0 0 0 0.1875rem rgba(99,102,241,0.08); }
        .field-input::placeholder { color: #c4c6d5; font-weight: 400; }
        select.field-input { cursor: pointer; }
        .photo-upload { width: 6.875rem; height: 8.125rem; border: 0.15625rem dashed #d1d5db; border-radius: 0.75rem; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s; overflow: hidden; background: #f8f9fc; }
        .photo-upload:hover { border-color: #6366f1; background: #f0f0ff; }
        .photo-preview { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen">
        <!-- Header Section -->
        <div class="bg-white border-b border-gray-100 px-8 py-6">
            <div class="flex items-center gap-3 mb-4">
                <a href="view_students" class="text-gray-400 hover:text-blue-600 transition-colors flex items-center gap-2 text-sm font-black uppercase tracking-widest">
                    <i class="fas fa-arrow-left"></i> Directory
                </a>
            </div>
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-user-plus text-indigo-600"></i> Student Enrollment
                </h1>
                <p class="text-gray-500 mt-2 font-medium">
                    Complete the student's biographical, parent, and admission details.
                </p>
            </div>
        </div>

        <div class="p-8 max-w-6xl mx-auto">
            
            <?php if (isset($_SESSION['flash_error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl mb-6 flex items-start gap-3">
                    <i class="fas fa-exclamation-triangle text-red-500 mt-0.5"></i>
                    <span class="text-sm font-medium"><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <form action="add_student" method="POST" enctype="multipart/form-data" class="space-y-6">
                
                <!-- ── SECTION 1: Personal Information ─────────────────── -->
                <div class="section-card overflow-hidden">
                    <div class="section-header">
                        <div class="section-icon bg-indigo-100">
                            <i class="fas fa-user text-indigo-600"></i>
                        </div>
                        <div>
                            <h2 class="font-bold text-gray-900 text-base">Personal Information</h2>
                            <p class="text-xs text-gray-400 font-medium">Student's basic biographical identity</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <div class="flex flex-col md:flex-row gap-8">
                            <!-- Photo Upload -->
                            <div class="flex-shrink-0">
                                <label class="field-label mb-2">Passport Picture</label>
                                <label for="photo_input" class="photo-upload group cursor-pointer" id="photo_label">
                                    <img id="photo_preview" class="photo-preview hidden">
                                    <div id="photo_placeholder" class="flex flex-col items-center justify-center text-center p-2">
                                        <i class="fas fa-camera text-2xl text-gray-300 mb-2 group-hover:text-indigo-400 transition"></i>
                                        <span class="text-[0.625rem] text-gray-400 font-bold group-hover:text-indigo-500">Click to upload</span>
                                    </div>
                                </label>
                                <input type="file" name="photo" id="photo_input" accept=".jpg,.jpeg,.png,.webp" class="sr-only" onchange="previewPhoto(event)">
                            </div>

                            <!-- Fields -->
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                                <div>
                                    <label class="field-label">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="first_name" class="field-input" placeholder="e.g. John" required>
                                </div>
                                <div>
                                    <label class="field-label">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="last_name" class="field-input" placeholder="e.g. Doe" required>
                                </div>
                                <div>
                                    <label class="field-label">Date of Birth</label>
                                    <input type="date" name="date_of_birth" class="field-input">
                                </div>
                                <div class="lg:col-span-2">
                                    <label class="field-label">Place of Birth</label>
                                    <input type="text" name="place_of_birth" class="field-input" placeholder="City or Hospital of birth">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 2: Academic Details ─────────────────── -->
                <div class="section-card overflow-hidden">
                    <div class="section-header">
                        <div class="section-icon bg-blue-100">
                            <i class="fas fa-graduation-cap text-blue-600"></i>
                        </div>
                        <div>
                            <h2 class="font-bold text-gray-900 text-base">Admission Details</h2>
                            <p class="text-xs text-gray-400 font-medium">Placement and academic history</p>
                        </div>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <label class="field-label">Class / Grade <span class="text-red-500">*</span></label>
                            <select name="class" class="field-input" required>
                                <option value="">-- Select Class --</option>
                                <?php foreach($class_options as $c): ?>
                                    <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="field-label">Date Admitted</label>
                            <input type="date" name="date_admitted" class="field-input">
                        </div>
                        <div>
                            <label class="field-label">Previous School (if any)</label>
                            <input type="text" name="previous_school" class="field-input" placeholder="Name of previous institution">
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 3: Address & Emergency ─────────────────── -->
                <div class="section-card overflow-hidden">
                    <div class="section-header">
                        <div class="section-icon bg-green-100">
                            <i class="fas fa-map-marker-alt text-green-600"></i>
                        </div>
                        <div>
                            <h2 class="font-bold text-gray-900 text-base">Address & Emergency</h2>
                            <p class="text-xs text-gray-400 font-medium">Residential location and emergency contacts</p>
                        </div>
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div class="md:col-span-2">
                            <label class="field-label">Place of Stay (Address)</label>
                            <textarea name="address" rows="2" class="field-input" placeholder="Full residential address" style="resize:none;"></textarea>
                        </div>
                        <div>
                            <label class="field-label">Landmark (Famous Location)</label>
                            <input type="text" name="landmark" class="field-input" placeholder="Nearest landmark to residence">
                        </div>
                        <div>
                            <label class="field-label">Emergency Contact</label>
                            <input type="tel" name="emergency_contact" class="field-input" placeholder="Name and Phone Number">
                        </div>
                    </div>
                </div>

                <!-- ── SECTION 4: Parents Information ─────────────────── -->
                <div class="section-card overflow-hidden">
                    <div class="section-header">
                        <div class="section-icon bg-purple-100">
                            <i class="fas fa-users text-purple-600"></i>
                        </div>
                        <div>
                            <h2 class="font-bold text-gray-900 text-base">Parents Information</h2>
                            <p class="text-xs text-gray-400 font-medium">Names and contacts for the Communication Engine</p>
                        </div>
                    </div>
                    <div class="p-6 grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Mother -->
                        <div class="bg-purple-50 p-5 rounded-xl border border-purple-100 space-y-4">
                            <h3 class="font-bold text-sm text-purple-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fas fa-female"></i> Mother's Details
                            </h3>
                            <div>
                                <label class="field-label text-purple-700">Link Existing Mother</label>
                                <select name="existing_mother_id" class="field-input bg-purple-50 border-purple-200 focus:border-purple-500" onchange="toggleMotherFields(this.value)">
                                    <option value="">-- Add New Mother Profile --</option>
                                    <?php foreach($parents_list as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars(trim($p['title'].' '.$p['first_name'].' '.$p['last_name'])) ?> (<?= htmlspecialchars($p['phone']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="new_mother_section" class="space-y-4 pt-3 border-t border-purple-200/60">
                                <div>
                                    <label class="field-label">New Mother's Full Name</label>
                                    <input type="text" name="mother_name" id="mother_name" class="field-input" placeholder="e.g. Jane Doe">
                                </div>
                                <div>
                                    <label class="field-label">New Mother's Contact</label>
                                    <input type="tel" name="mother_contact" id="mother_contact" class="field-input" placeholder="Phone number for SMS">
                                </div>
                            </div>
                        </div>
                        <!-- Father -->
                        <div class="bg-indigo-50 p-5 rounded-xl border border-indigo-100 space-y-4">
                            <h3 class="font-bold text-sm text-indigo-800 uppercase tracking-wider flex items-center gap-2">
                                <i class="fas fa-male"></i> Father's Details
                            </h3>
                            <div>
                                <label class="field-label text-indigo-700">Link Existing Father</label>
                                <select name="existing_father_id" class="field-input bg-indigo-50 border-indigo-200 focus:border-indigo-500" onchange="toggleFatherFields(this.value)">
                                    <option value="">-- Add New Father Profile --</option>
                                    <?php foreach($parents_list as $p): ?>
                                        <option value="<?= $p['id'] ?>"><?= htmlspecialchars(trim($p['title'].' '.$p['first_name'].' '.$p['last_name'])) ?> (<?= htmlspecialchars($p['phone']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div id="new_father_section" class="space-y-4 pt-3 border-t border-indigo-200/60">
                                <div>
                                    <label class="field-label">New Father's Full Name</label>
                                    <input type="text" name="father_name" id="father_name" class="field-input" placeholder="e.g. John Doe">
                                </div>
                                <div>
                                    <label class="field-label">New Father's Contact</label>
                                    <input type="tel" name="father_contact" id="father_contact" class="field-input" placeholder="Phone number for SMS">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit Bar -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center justify-between sticky bottom-4">
                    <p class="text-xs text-gray-500">
                        <i class="fas fa-asterisk text-red-500 mr-1"></i> Fields marked with red are required.
                    </p>
                    <div class="flex gap-3">
                        <button type="reset" class="px-6 py-2.5 text-sm font-bold text-gray-600 border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                            Clear
                        </button>
                        <button type="submit" class="px-8 py-2.5 text-sm font-bold text-white bg-indigo-600 rounded-xl hover:bg-indigo-700 shadow-sm transition-all flex items-center gap-2">
                            <i class="fas fa-check"></i> Enroll Student
                        </button>
                    </div>
                </div>

            </form>
        </div>
    </main>

    <script>
        function toggleMotherFields(val) {
            const section = document.getElementById('new_mother_section');
            const nameInput = document.getElementById('mother_name');
            const contactInput = document.getElementById('mother_contact');
            
            if (val !== "") {
                section.style.opacity = '0.5';
                section.style.pointerEvents = 'none';
                nameInput.required = false;
                contactInput.required = false;
                nameInput.value = '';
                contactInput.value = '';
            } else {
                section.style.opacity = '1';
                section.style.pointerEvents = 'auto';
                nameInput.required = true;
                contactInput.required = true;
            }
        }

        function toggleFatherFields(val) {
            const section = document.getElementById('new_father_section');
            const nameInput = document.getElementById('father_name');
            const contactInput = document.getElementById('father_contact');
            
            if (val !== "") {
                section.style.opacity = '0.5';
                section.style.pointerEvents = 'none';
                nameInput.value = '';
                contactInput.value = '';
            } else {
                section.style.opacity = '1';
                section.style.pointerEvents = 'auto';
            }
        }

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
