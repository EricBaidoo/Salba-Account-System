<?php
session_start();
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';
include '../../../includes/staff_migration.php';

if (!is_logged_in() || $_SESSION['role'] !== 'admin') {
    header('Location: ../../../includes/login.php'); exit;
}
run_staff_migration($conn);

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: view_staff.php'); exit; }

$res = $conn->query("
    SELECT sp.*, u.id as user_id, u.username, u.role as user_role 
    FROM staff_profiles sp 
    LEFT JOIN users u ON u.staff_id = sp.id 
    WHERE sp.id = $id LIMIT 1
");
if (!$res || $res->num_rows === 0) { header('Location: view_staff.php'); exit; }
$s = $res->fetch_assoc();
$has_login = !empty($s['user_id']);

$initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_filter(explode(' ', $s['full_name']))));
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($s['full_name']) ?> – Staff Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .info-row { display: flex; padding: 10px 0; border-bottom: 1px solid #f5f5fa; }
        .info-label { width: 45%; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; padding-top: 2px; }
        .info-value { flex: 1; font-size: 14px; font-weight: 600; color: #1f2937; }
        .section-card { background: white; border-radius: 14px; border: 1px solid #f0f0f5; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 20px; overflow: hidden; }
        .section-header { display: flex; align-items: center; gap-10px; padding: 14px 20px; background: #fafafa; border-bottom: 1px solid #f0f0f5; font-weight: 700; font-size: 13px; color: #374151; }
    </style>
</head>
<body class="bg-gray-50">

    <?php include '../../../includes/sidebar_admin.php'; ?>

    <main class="ml-72 min-h-screen p-8 max-w-[1200px]">

        <a href="view_staff.php" class="text-xs font-bold text-indigo-600 hover:text-indigo-800 uppercase tracking-wider flex items-center gap-1 mb-5 w-fit">
            <i class="fas fa-arrow-left"></i> Back to Directory
        </a>

        <!-- Profile Header Card -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
            <div class="h-24 bg-gradient-to-r from-indigo-600 via-indigo-500 to-purple-600"></div>
            <div class="px-8 pb-6 -mt-12 flex items-end justify-between">
                <div class="flex items-end gap-5">
                    <?php if($s['photo_path']): ?>
                        <img src="../../../<?= htmlspecialchars($s['photo_path']) ?>" class="w-24 h-28 object-cover rounded-xl border-4 border-white shadow-lg" alt="">
                    <?php else: ?>
                        <div class="w-24 h-28 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl border-4 border-white shadow-lg flex items-center justify-center text-white font-extrabold text-3xl">
                            <?= $initials ?>
                        </div>
                    <?php endif; ?>
                    <div class="mb-1">
                        <h1 class="text-2xl font-extrabold text-gray-900"><?= htmlspecialchars($s['full_name']) ?></h1>
                        <div class="flex items-center gap-2 mt-0.5">
                            <p class="text-base font-semibold text-indigo-600"><?= htmlspecialchars(($s['job_title'] ?? '') ?: 'Staff') ?></p>
                            <?php if(!empty($s['staff_code'])): ?>
                                <span class="bg-indigo-600 text-white text-[10px] font-black px-2 py-0.5 rounded tracking-widest uppercase"><?= htmlspecialchars($s['staff_code']) ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider"><?= htmlspecialchars($s['department'] ?? '') ?></p>
                    </div>
                </div>
                
                <!-- Login Status + Actions -->
                <div class="flex flex-col items-end gap-3 mb-1">
                    <?php if($has_login): ?>
                        <div class="flex items-center gap-2 bg-green-50 border border-green-200 px-4 py-2 rounded-xl">
                            <i class="fas fa-check-circle text-green-500"></i>
                            <div>
                                <div class="text-xs font-extrabold text-green-800">System Login Active</div>
                                <div class="text-xs text-green-600 font-medium">@<?= htmlspecialchars($s['username']) ?> · <?= htmlspecialchars($s['user_role']) ?></div>
                            </div>
                        </div>
                        <a href="reset_password.php?id=<?= $s['id'] ?>" class="flex items-center gap-2 text-xs font-bold bg-yellow-50 text-yellow-700 border border-yellow-200 px-4 py-2 rounded-xl hover:bg-yellow-100 transition">
                            <i class="fas fa-key"></i> Reset Password
                        </a>
                    <?php else: ?>
                        <div class="flex items-center gap-2 bg-gray-50 border border-gray-200 px-4 py-2 rounded-xl">
                            <i class="fas fa-lock text-gray-300"></i>
                            <div class="text-xs font-bold text-gray-500">No System Login</div>
                        </div>
                        <a href="activate_login.php?id=<?= $s['id'] ?>" class="flex items-center gap-2 text-xs font-bold bg-green-600 text-white px-4 py-2 rounded-xl hover:bg-green-700 shadow-sm transition">
                            <i class="fas fa-user-check"></i> Activate System Login
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            <!-- Personal Info -->
            <div class="section-card">
                <div class="section-header px-5 py-3 bg-indigo-50 border-b border-indigo-100 font-bold text-indigo-800 text-sm flex items-center gap-2">
                    <i class="fas fa-user text-indigo-400"></i> Personal Information
                </div>
                <div class="px-5 py-2">
                    <?php
                    $personal = [
                        'Date of Birth'    => ($s['date_of_birth'] ?? '') ? date('F j, Y', strtotime($s['date_of_birth'])) : '—',
                        'Marital Status'   => ($s['marital_status'] ?? '') ?: '—',
                        'Nationality'      => ($s['nationality'] ?? '') ?: '—',
                        'Telephone'        => ($s['phone_number'] ?? '') ?: '—',
                        'Ghana Card No.'   => ($s['ghana_card_no'] ?? '') ?: '—',
                        'SSNIT Number'     => ($s['ssnit_number'] ?? '') ?: '—',
                        'Religion'         => ($s['religion'] ?? '') ?: '—',
                        'Languages Spoken' => ($s['languages_spoken'] ?? '') ?: '—',
                        'Hometown'         => ($s['hometown'] ?? '') ?: '—',
                    ];
                    foreach($personal as $l => $v): ?>
                        <div class="info-row">
                            <div class="info-label"><?= $l ?></div>
                            <div class="info-value"><?= htmlspecialchars($v) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Address + Employment -->
            <div class="space-y-5">
                <div class="section-card">
                    <div class="section-header px-5 py-3 bg-green-50 border-b border-green-100 font-bold text-green-800 text-sm flex items-center gap-2">
                        <i class="fas fa-map-marker-alt text-green-400"></i> Address
                    </div>
                    <div class="px-5 py-2">
                        <?php foreach(['Home Address' => ($s['address'] ?? ''), 'Landmark' => ($s['landmark'] ?? '')] as $l => $v): ?>
                            <div class="info-row">
                                <div class="info-label"><?= $l ?></div>
                                <div class="info-value"><?= htmlspecialchars($v ?: '—') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-header px-5 py-3 bg-blue-50 border-b border-blue-100 font-bold text-blue-800 text-sm flex items-center gap-2">
                        <i class="fas fa-briefcase text-blue-400"></i> Employment
                    </div>
                    <div class="px-5 py-2">
                        <?php
                        $emp = [
                            'Highest Qualification' => ($s['highest_qualification'] ?? ''),
                            'Entry Qualification'   => ($s['entry_qualification'] ?? ''),
                            'First Appointment'     => ($s['first_appointment_date'] ?? '') ? date('F j, Y', strtotime($s['first_appointment_date'])) : null,
                        ];
                        foreach($emp as $l => $v): ?>
                            <div class="info-row">
                                <div class="info-label"><?= $l ?></div>
                                <div class="info-value"><?= htmlspecialchars($v ?: '—') ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Bank Details -->
            <div class="section-card">
                <div class="section-header px-5 py-3 bg-yellow-50 border-b border-yellow-100 font-bold text-yellow-800 text-sm flex items-center gap-2">
                    <i class="fas fa-university text-yellow-400"></i> Bank Details
                </div>
                <div class="px-5 py-2">
                    <?php foreach(['Bank Name' => ($s['bank_name'] ?? ''), 'Account Number' => ($s['bank_account_no'] ?? ''), 'Branch' => ($s['bank_branch'] ?? '')] as $l => $v): ?>
                        <div class="info-row">
                            <div class="info-label"><?= $l ?></div>
                            <div class="info-value"><?= htmlspecialchars($v ?: '—') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="section-card">
                <div class="section-header px-5 py-3 bg-red-50 border-b border-red-100 font-bold text-red-800 text-sm flex items-center gap-2">
                    <i class="fas fa-phone-alt text-red-400"></i> Emergency Contact
                </div>
                <div class="px-5 py-2">
                    <?php foreach(['Name' => ($s['emergency_name'] ?? ''), 'Phone' => ($s['emergency_phone'] ?? '')] as $l => $v): ?>
                        <div class="info-row">
                            <div class="info-label"><?= $l ?></div>
                            <div class="info-value"><?= htmlspecialchars($v ?: '—') ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Guarantors -->
            <div class="section-card lg:col-span-2">
                <div class="section-header px-5 py-3 bg-purple-50 border-b border-purple-100 font-bold text-purple-800 text-sm flex items-center gap-2">
                    <i class="fas fa-user-shield text-purple-400"></i> Guarantors
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 divide-x divide-gray-100">
                    <div class="px-5 py-3">
                        <div class="text-xs font-extrabold text-purple-600 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-5 h-5 bg-purple-600 text-white rounded-full text-[10px] flex items-center justify-center font-black">1</span> First Guarantor
                        </div>
                        <?php foreach(['Name' => ($s['guarantor1_name'] ?? ''), 'Phone' => ($s['guarantor1_phone'] ?? ''), 'Address' => ($s['guarantor1_address'] ?? '')] as $l => $v): ?>
                            <div class="info-row"><div class="info-label"><?= $l ?></div><div class="info-value"><?= htmlspecialchars($v ?: '—') ?></div></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="px-5 py-3">
                        <div class="text-xs font-extrabold text-indigo-600 uppercase tracking-wider mb-3 flex items-center gap-2">
                            <span class="w-5 h-5 bg-indigo-600 text-white rounded-full text-[10px] flex items-center justify-center font-black">2</span> Second Guarantor
                        </div>
                        <?php foreach(['Name' => ($s['guarantor2_name'] ?? ''), 'Phone' => ($s['guarantor2_phone'] ?? ''), 'Address' => ($s['guarantor2_address'] ?? '')] as $l => $v): ?>
                            <div class="info-row"><div class="info-label"><?= $l ?></div><div class="info-value"><?= htmlspecialchars($v ?: '—') ?></div></div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- Edit Button -->
        <div class="mt-6 flex gap-3">
            <a href="edit_staff.php?id=<?= $s['id'] ?>" class="bg-indigo-600 text-white font-bold px-6 py-2.5 rounded-xl hover:bg-indigo-700 transition flex items-center gap-2">
                <i class="fas fa-pencil-alt"></i> Edit Profile
            </a>
            <a href="view_staff.php" class="bg-white border border-gray-200 text-gray-600 font-bold px-6 py-2.5 rounded-xl hover:bg-gray-50 transition">
                Back to Directory
            </a>
        </div>
    </main>
</body>
</html>
