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

// Handle Photo URLs
$photo_src = $s['photo_path'];
if ($photo_src && strpos($photo_src, 'http') === 0) {
    if (preg_match('/id=([a-zA-Z0-9_-]+)/', $photo_src, $matches)) {
        $photo_src = "https://lh3.googleusercontent.com/d/" . $matches[1];
    }
} else {
    $photo_src = $photo_src ? "../../../" . $photo_src : null;
}
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
        body { font-family: 'Inter', sans-serif; background-color: #f9fafb; }
        
        .glass-effect {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.4);
        }

        .section-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            border: 1px solid #f3f4f6;
            transition: all 0.2s ease-in-out;
        }

        .section-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
            transform: translateY(-2px);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.25rem;
        }

        .data-point-wrapper {
            background: #f8fafc;
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid #f1f5f9;
        }

        .data-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .data-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: #0f172a;
            word-break: break-word;
        }

        .action-button {
            transition: all 0.2s ease;
        }
        .action-button:hover {
            transform: scale(1.02);
        }

        /* Print Styles */
        @media print {
            body { background: white !important; font-size: 12pt; }
            aside, nav, .print-hidden { display: none !important; }
            main { margin: 0 !important; max-width: 100% !important; padding: 0 !important; }
            .section-card { box-shadow: none !important; border: 1px solid #ccc !important; break-inside: avoid; }
            .data-point-wrapper { background: transparent !important; padding: 0.5rem 0 !important; border: none !important; border-bottom: 1px dotted #ccc !important; border-radius: 0 !important; }
            .grid { display: block !important; }
            .lg\:col-span-1, .lg\:col-span-2 { width: 100% !important; margin-bottom: 1rem; }
            .print-header { display: flex !important; align-items: center; justify-content: space-between; border-bottom: 2px solid #000; padding-bottom: 1rem; margin-bottom: 2rem; }
        }
    </style>
</head>
<body class="text-slate-800 antialiased">

    <div class="print-hidden">
        <?php include '../../../includes/sidebar_admin.php'; ?>
    </div>

    <!-- Print Only Header -->
    <div class="hidden print-header p-8 pb-0">
        <div>
            <h1 class="text-3xl font-bold">SALBA Montessori International School</h1>
            <h2 class="text-xl text-gray-600 font-semibold mt-1">Staff Profile Report</h2>
        </div>
        <div class="text-right">
            <div class="text-sm text-gray-500">Generated: <?= date('d M Y, h:i A') ?></div>
            <div class="text-sm font-bold mt-1">ID: <?= htmlspecialchars($s['staff_code'] ?? 'N/A') ?></div>
        </div>
    </div>

    <main class="ml-72 min-h-screen p-8 lg:p-10 max-w-[1400px] mx-auto print:ml-0 print:p-8">

        <!-- Top Actions -->
        <div class="flex items-center justify-between mb-8 print-hidden">
            <a href="view_staff.php" class="inline-flex items-center gap-2 text-sm font-bold text-indigo-600 hover:text-indigo-800 bg-indigo-50 px-4 py-2 rounded-xl transition-colors">
                <i class="fas fa-arrow-left"></i> Back to Directory
            </a>
            <div class="flex gap-3">
                <button onclick="window.print()" class="action-button inline-flex items-center gap-2 text-sm font-bold bg-white text-slate-700 border border-slate-200 px-5 py-2.5 rounded-xl hover:bg-slate-50 shadow-sm">
                    <i class="fas fa-print text-slate-400"></i> Print Profile
                </button>
                <a href="edit_staff.php?id=<?= $s['id'] ?>" class="action-button inline-flex items-center gap-2 text-sm font-bold bg-indigo-600 text-white px-5 py-2.5 rounded-xl hover:bg-indigo-700 shadow-sm shadow-indigo-200">
                    <i class="fas fa-pen-to-square"></i> Edit Details
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- LEFT PANEL: Overview Profile -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Main Identity Card -->
                <div class="section-card relative overflow-hidden bg-white">
                    <!-- Decorative Background -->
                    <div class="absolute top-0 left-0 right-0 h-32 bg-gradient-to-br from-indigo-500 via-purple-500 to-indigo-600 print:hidden">
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(white 1px, transparent 1px); background-size: 16px 16px;"></div>
                    </div>
                    
                    <div class="p-6 pt-20 relative z-10 text-center print:pt-6">
                        <div class="w-32 h-32 mx-auto mb-4 relative z-20">
                            <?php if($photo_src): ?>
                                <img src="<?= htmlspecialchars($photo_src) ?>" class="w-full h-full object-cover rounded-2xl border-4 border-white shadow-xl bg-white" alt="Profile" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-full h-full bg-gradient-to-br from-indigo-100 to-white rounded-2xl border-4 border-white shadow-xl flex items-center justify-center text-indigo-500 font-extrabold text-4xl hidden">
                                    <?= $initials ?>
                                </div>
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-indigo-100 to-white rounded-2xl border-4 border-white shadow-xl flex items-center justify-center text-indigo-500 font-extrabold text-4xl">
                                    <?= $initials ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Status Indicator -->
                            <?php $status = $s['employment_status'] ?? 'active'; ?>
                            <div class="absolute bottom-1 right-1 w-5 h-5 rounded-full border-2 border-white 
                                <?= $status === 'active' ? 'bg-emerald-500' : ($status === 'retired' ? 'bg-amber-500' : 'bg-rose-500') ?>">
                            </div>
                        </div>

                        <h2 class="text-2xl font-extrabold text-slate-800 leading-tight"><?= htmlspecialchars($s['full_name']) ?></h2>
                        <p class="text-indigo-600 font-bold text-sm mt-1"><?= htmlspecialchars($s['job_title'] ?: 'Staff Member') ?></p>
                        
                        <div class="flex items-center justify-center gap-2 mt-4 flex-wrap">
                            <?php 
                            $types = explode(',', $s['staff_type'] ?? 'teaching');
                            foreach($types as $t): 
                                $t = trim($t);
                                if ($t === 'teaching'): ?>
                                <span class="bg-indigo-50 text-indigo-700 text-xs font-black px-3 py-1.5 rounded-lg uppercase tracking-wide border border-indigo-100 flex items-center gap-1.5">
                                    <i class="fas fa-chalkboard-user opacity-70"></i> Teaching
                                </span>
                            <?php elseif ($t === 'non-teaching'): ?>
                                <span class="bg-orange-50 text-orange-700 text-xs font-black px-3 py-1.5 rounded-lg uppercase tracking-wide border border-orange-100 flex items-center gap-1.5">
                                    <i class="fas fa-user-tie opacity-70"></i> Non-Teaching
                                </span>
                            <?php endif; endforeach; ?>
                        </div>

                        <div class="mt-6 pt-6 border-t border-slate-100 print:hidden">
                            <div class="flex justify-center gap-4">
                                <?php if($s['phone_number']): ?>
                                    <a href="tel:<?= htmlspecialchars($s['phone_number']) ?>" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-500 hover:bg-emerald-50 hover:text-emerald-600 transition shadow-sm border border-slate-100" title="Call">
                                        <i class="fas fa-phone"></i>
                                    </a>
                                <?php endif; ?>
                                <?php if($s['email']): ?>
                                    <a href="mailto:<?= htmlspecialchars($s['email']) ?>" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-500 hover:bg-blue-50 hover:text-blue-600 transition shadow-sm border border-slate-100" title="Email">
                                        <i class="fas fa-envelope"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Access & Credentials -->
                <div class="section-card p-5 print:hidden bg-gradient-to-b from-white to-slate-50">
                    <h3 class="text-xs font-black text-slate-400 uppercase tracking-widest mb-4 flex items-center gap-2">
                        <i class="fas fa-shield-halved"></i> System Access
                    </h3>
                    
                    <?php if($has_login): ?>
                        <div class="bg-emerald-50 border border-emerald-100 p-4 rounded-xl mb-4">
                            <div class="flex items-center gap-3 mb-2">
                                <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                                    <i class="fas fa-check"></i>
                                </div>
                                <div>
                                    <div class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Account Active</div>
                                    <div class="text-sm font-bold text-emerald-900">@<?= htmlspecialchars($s['username']) ?></div>
                                </div>
                            </div>
                            <div class="text-xs text-emerald-700 font-medium ml-11">Role: <strong class="capitalize"><?= htmlspecialchars($s['user_role']) ?></strong></div>
                        </div>
                        <a href="reset_password.php?id=<?= $s['id'] ?>" class="block w-full text-center text-xs font-bold bg-white text-slate-600 border border-slate-200 px-4 py-2.5 rounded-lg hover:bg-slate-50 transition shadow-sm">
                            <i class="fas fa-key mr-1.5 opacity-70"></i> Reset Password
                        </a>
                    <?php else: ?>
                        <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl mb-4 flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-500 flex items-center justify-center">
                                <i class="fas fa-ban"></i>
                            </div>
                            <div>
                                <div class="text-xs font-bold text-slate-700">No Login Access</div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Profile is read-only</div>
                            </div>
                        </div>
                        <a href="activate_login.php?id=<?= $s['id'] ?>" class="block w-full text-center text-xs font-bold bg-indigo-600 text-white px-4 py-2.5 rounded-lg hover:bg-indigo-700 transition shadow-sm shadow-indigo-200">
                            <i class="fas fa-user-plus mr-1.5 opacity-70"></i> Create System User
                        </a>
                    <?php endif; ?>
                </div>

            </div>

            <!-- RIGHT PANEL: Detailed Information -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Personal Info block -->
                <div class="section-card p-6 lg:p-8">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-3 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fas fa-address-card"></i></div>
                        Personal Information
                    </h3>
                    
                    <div class="info-grid text-sm">
                        <div class="data-point-wrapper">
                            <div class="data-label">Staff Code</div>
                            <div class="data-value font-mono bg-slate-200 text-slate-700 px-2 py-0.5 rounded text-xs inline-block mt-0.5 font-bold tracking-tight"><?= htmlspecialchars($s['staff_code'] ?? 'N/A') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-calendar-day"></i> Date of Birth</div>
                            <div class="data-value"><?= ($s['date_of_birth'] ?? '') ? date('d M, Y', strtotime($s['date_of_birth'])) : '—' ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-venus-mars"></i> Gender</div>
                            <div class="data-value"><?= htmlspecialchars($s['gender'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-ring"></i> Marital Status</div>
                            <div class="data-value"><?= htmlspecialchars($s['marital_status'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-globe-africa"></i> Nationality</div>
                            <div class="data-value"><?= htmlspecialchars($s['nationality'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-torii-gate"></i> Religion</div>
                            <div class="data-value"><?= htmlspecialchars($s['religion'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper sm:col-span-2">
                            <div class="data-label"><i class="fas fa-language"></i> Languages Spoken</div>
                            <div class="data-value"><?= htmlspecialchars($s['languages_spoken'] ?? '—') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Contact & Identification -->
                <div class="section-card p-6 lg:p-8">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-3 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center"><i class="fas fa-id-badge"></i></div>
                        Contact & Identifications
                    </h3>
                    
                    <div class="info-grid">
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-phone-alt"></i> Primary Phone</div>
                            <div class="data-value"><?= htmlspecialchars($s['phone_number'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-envelope"></i> Email Address</div>
                            <div class="data-value"><?= htmlspecialchars($s['email'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-id-card"></i> Ghana Card No.</div>
                            <div class="data-value tracking-wider font-mono text-xs"><?= htmlspecialchars($s['ghana_card_no'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-file-invoice"></i> SSNIT Number</div>
                            <div class="data-value tracking-wider font-mono text-xs"><?= htmlspecialchars($s['ssnit_number'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper sm:col-span-2">
                            <div class="data-label"><i class="fas fa-map-location-dot"></i> Residential Address</div>
                            <div class="data-value"><?= nl2br(htmlspecialchars($s['address'] ?? '—')) ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-map-pin"></i> Landmark</div>
                            <div class="data-value"><?= htmlspecialchars($s['landmark'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-house-user"></i> Hometown</div>
                            <div class="data-value"><?= htmlspecialchars($s['hometown'] ?? '—') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Employment & Academic -->
                <div class="section-card p-6 lg:p-8">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-3 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center"><i class="fas fa-user-graduate"></i></div>
                        Employment & Academic
                    </h3>
                    
                    <div class="info-grid">
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-briefcase"></i> Department</div>
                            <div class="data-value font-bold text-purple-700"><?= htmlspecialchars($s['department'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper">
                            <div class="data-label"><i class="fas fa-calendar-check"></i> 1st Appointment</div>
                            <div class="data-value"><?= ($s['first_appointment_date'] ?? '') ? date('d M, Y', strtotime($s['first_appointment_date'])) : '—' ?></div>
                        </div>
                        <div class="data-point-wrapper sm:col-span-2">
                            <div class="data-label"><i class="fas fa-award"></i> Highest Qualification</div>
                            <div class="data-value"><?= htmlspecialchars($s['highest_qualification'] ?? '—') ?></div>
                        </div>
                        <div class="data-point-wrapper sm:col-span-2">
                            <div class="data-label"><i class="fas fa-certificate"></i> Entry Qualification</div>
                            <div class="data-value"><?= htmlspecialchars($s['entry_qualification'] ?? '—') ?></div>
                        </div>
                    </div>
                </div>

                <!-- Financial & Emergency -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="section-card p-6">
                        <h3 class="text-sm font-bold text-slate-800 mb-5 flex items-center gap-3 border-b border-slate-100 pb-2">
                            <div class="w-7 h-7 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-xs"><i class="fas fa-building-columns"></i></div>
                            Bank Details
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <div class="data-label">Bank Account Details</div>
                                <div class="data-value"><?= nl2br(htmlspecialchars($s['bank_details'] ?? '—')) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="section-card p-6">
                        <h3 class="text-sm font-bold text-slate-800 mb-5 flex items-center gap-3 border-b border-slate-100 pb-2">
                            <div class="w-7 h-7 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-xs"><i class="fas fa-truck-medical"></i></div>
                            Emergency Contact
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <div class="data-label">Contact Name & Phone</div>
                                <div class="data-value font-bold text-rose-700"><?= nl2br(htmlspecialchars($s['emergency_contact'] ?? '—')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Guarantors -->
                <div class="section-card p-6 lg:p-8 mb-10">
                    <h3 class="text-sm font-bold text-slate-800 mb-6 flex items-center gap-3 border-b border-slate-100 pb-3">
                        <div class="w-8 h-8 rounded-lg bg-sky-50 text-sky-600 flex items-center justify-center"><i class="fas fa-users-viewfinder"></i></div>
                        Guarantors
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:divide-x divide-slate-100">
                        <div class="md:pr-6 space-y-4">
                            <div class="text-[10px] font-black text-sky-600 uppercase tracking-widest flex items-center gap-2 mb-2">
                                <span class="w-5 h-5 bg-sky-600 text-white rounded-full flex items-center justify-center">1</span> 
                                First Guarantor
                            </div>
                            <div>
                                <div class="data-label">Name</div>
                                <div class="data-value font-bold"><?= htmlspecialchars($s['guarantor1_name'] ?? '—') ?></div>
                            </div>
                            <div>
                                <div class="data-label">Phone</div>
                                <div class="data-value"><?= htmlspecialchars($s['guarantor1_phone'] ?? '—') ?></div>
                            </div>
                            <div>
                                <div class="data-label">Address / Location</div>
                                <div class="data-value text-sm"><?= nl2br(htmlspecialchars($s['guarantor1_address'] ?? '—')) ?></div>
                            </div>
                        </div>

                        <div class="md:pl-6 space-y-4 pt-4 md:pt-0 border-t border-slate-100 md:border-0">
                            <div class="text-[10px] font-black text-indigo-600 uppercase tracking-widest flex items-center gap-2 mb-2">
                                <span class="w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center">2</span> 
                                Second Guarantor
                            </div>
                            <div>
                                <div class="data-label">Name</div>
                                <div class="data-value font-bold"><?= htmlspecialchars($s['guarantor2_name'] ?? '—') ?></div>
                            </div>
                            <div>
                                <div class="data-label">Phone</div>
                                <div class="data-value"><?= htmlspecialchars($s['guarantor2_phone'] ?? '—') ?></div>
                            </div>
                            <div>
                                <div class="data-label">Address / Location</div>
                                <div class="data-value text-sm"><?= nl2br(htmlspecialchars($s['guarantor2_address'] ?? '—')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- End Right Panel -->
        </div>

    </main>
</body>
</html>
