<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';
include '../../../includes/system_settings.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php'); exit;
}

// ── Load all settings ─────────────────────────────────────────────────
$s = [
    'print_title'       => getSystemSetting($conn, 'stationery_print_title',        'STATIONERY LIST'),
    'print_instruction' => getSystemSetting($conn, 'stationery_print_instruction',  'Dear Parent / Guardian, kindly ensure your child/ward reports with the items listed below. All items should be labelled with the student\'s name. Thank you for your cooperation.'),
    'print_footer_1'    => getSystemSetting($conn, 'stationery_print_footer_1',     'Items must be brought on or before the first week of the term.'),
    'print_footer_2'    => getSystemSetting($conn, 'stationery_print_footer_2',     'All items should be neatly labelled with your child\'s full name and class.'),
    'print_footer_3'    => getSystemSetting($conn, 'stationery_print_footer_3',     'For inquiries, please contact the class teacher or school administration.'),
    'print_show_price'  => getSystemSetting($conn, 'stationery_print_show_price',   '0'),
    'print_show_notes'  => getSystemSetting($conn, 'stationery_print_show_notes',   '1'),
    'print_show_sig'    => getSystemSetting($conn, 'stationery_print_show_sig',     '1'),
    'billing_fee_name'  => getSystemSetting($conn, 'stationery_billing_fee_name',   'stationery'),
];

// ── Verify billing fee exists ─────────────────────────────────────────
$billing_fee_name = trim($s['billing_fee_name']);
$fee_check = $conn->query("SELECT id, name FROM fees WHERE LOWER(name)=LOWER('".$conn->real_escape_string($billing_fee_name)."') LIMIT 1")->fetch_assoc();

// ── Handle POST save ──────────────────────────────────────────────────
$saved   = false;
$errors  = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_SESSION['username'] ?? 'Admin';
    $fields = [
        'stationery_print_title'       => trim($_POST['print_title']       ?? ''),
        'stationery_print_instruction' => trim($_POST['print_instruction'] ?? ''),
        'stationery_print_footer_1'    => trim($_POST['print_footer_1']    ?? ''),
        'stationery_print_footer_2'    => trim($_POST['print_footer_2']    ?? ''),
        'stationery_print_footer_3'    => trim($_POST['print_footer_3']    ?? ''),
        'stationery_print_show_price'  => isset($_POST['print_show_price'])  ? '1' : '0',
        'stationery_print_show_notes'  => isset($_POST['print_show_notes'])  ? '1' : '0',
        'stationery_print_show_sig'    => isset($_POST['print_show_sig'])    ? '1' : '0',
        'stationery_billing_fee_name'  => strtolower(trim($_POST['billing_fee_name'] ?? 'stationery')),
    ];
    foreach ($fields as $key => $val) {
        setSystemSetting($conn, $key, $val, $user);
        $short = str_replace('stationery_', '', $key);
        $s[$short] = $val;
    }
    // Re-check billing fee
    $billing_fee_name = $fields['stationery_billing_fee_name'];
    $fee_check = $conn->query("SELECT id, name FROM fees WHERE LOWER(name)=LOWER('".$conn->real_escape_string($billing_fee_name)."') LIMIT 1")->fetch_assoc();
    $saved = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stationery Settings | Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="bg-slate-50 text-slate-900">
<?php include '../../../includes/sidebar_admin_modern.php'; ?>

<main class="admin-main-content lg:ml-72 p-4 md:p-8 min-h-screen">

    <!-- Save toast -->
    <?php if ($saved): ?>
    <div id="save-toast" class="fixed top-5 right-5 z-50 bg-emerald-600 text-white text-sm font-semibold px-5 py-3 rounded-2xl shadow-xl flex items-center gap-3">
        <i class="fas fa-circle-check"></i> Settings saved successfully.
        <button onclick="this.parentElement.remove()" class="ml-1 text-white/70 hover:text-white"><i class="fas fa-xmark"></i></button>
    </div>
    <script>setTimeout(() => document.getElementById('save-toast')?.remove(), 4000);</script>
    <?php endif; ?>

    <!-- Header -->
    <header class="mb-6">
        <div class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-3 uppercase tracking-wider">
            <a href="../dashboard.php" class="hover:text-indigo-600 transition-colors"><i class="fas fa-home"></i> Admin</a>
            <span>/</span>
            <a href="dashboard.php" class="hover:text-indigo-600 transition-colors">Stationery</a>
            <span>/</span>
            <span class="text-indigo-600">Settings</span>
        </div>
        <h1 class="text-3xl font-black text-slate-900">Stationery <span class="text-indigo-600">Settings</span></h1>
        <p class="text-slate-500 mt-1 text-sm">Configure how stationery lists are printed and how billing works.</p>
    </header>

    <form method="POST" class="space-y-6 max-w-3xl">

        <!-- ── Printout Text ── -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-file-lines text-indigo-500"></i> Printout Text
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Text that appears on stationery lists sent home to parents.</p>
            </div>
            <div class="p-6 space-y-5">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Document Title</label>
                    <input type="text" name="print_title" value="<?= htmlspecialchars($s['print_title']) ?>"
                        class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 w-full"
                        placeholder="STATIONERY LIST">
                    <p class="text-xs text-slate-400">Shown as the main heading on the printed document.</p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Parent Instruction Paragraph</label>
                    <textarea name="print_instruction" rows="4"
                        class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-indigo-500 w-full resize-none"
                        placeholder="Dear Parent / Guardian..."><?= htmlspecialchars($s['print_instruction']) ?></textarea>
                    <p class="text-xs text-slate-400">Shown in italics below the title. Leave blank to hide this section.</p>
                </div>
                <div class="flex flex-col gap-2">
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Footer Lines <span class="normal-case tracking-normal font-normal">(★ bullet points at the bottom)</span></label>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400 text-sm font-bold w-4">★</span>
                        <input type="text" name="print_footer_1" value="<?= htmlspecialchars($s['print_footer_1']) ?>"
                            class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 flex-1"
                            placeholder="Footer line 1">
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400 text-sm font-bold w-4">★</span>
                        <input type="text" name="print_footer_2" value="<?= htmlspecialchars($s['print_footer_2']) ?>"
                            class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 flex-1"
                            placeholder="Footer line 2">
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-slate-400 text-sm font-bold w-4">★</span>
                        <input type="text" name="print_footer_3" value="<?= htmlspecialchars($s['print_footer_3']) ?>"
                            class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2 text-sm outline-none focus:ring-2 focus:ring-indigo-500 flex-1"
                            placeholder="Footer line 3">
                    </div>
                    <p class="text-xs text-slate-400">Leave any line blank to hide it.</p>
                </div>
            </div>
        </div>

        <!-- ── Print Display Options ── -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-print text-violet-500"></i> Print Display Options
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">Choose what columns and sections appear on the printed list.</p>
            </div>
            <div class="p-6 space-y-4">
                <label class="flex items-start gap-4 cursor-pointer group">
                    <div class="relative mt-0.5">
                        <input type="checkbox" name="print_show_price" value="1" <?= $s['print_show_price'] == '1' ? 'checked' : '' ?>
                            class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-checked:bg-indigo-600 rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Show Price Column</p>
                        <p class="text-xs text-slate-500">Display the item price in the printed list. Useful when parents need to purchase items themselves.</p>
                    </div>
                </label>
                <label class="flex items-start gap-4 cursor-pointer group">
                    <div class="relative mt-0.5">
                        <input type="checkbox" name="print_show_notes" value="1" <?= $s['print_show_notes'] == '1' ? 'checked' : '' ?>
                            class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-checked:bg-indigo-600 rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Show Notes Column</p>
                        <p class="text-xs text-slate-500">Display the notes/description column if any item has notes set (e.g. "Must be labelled").</p>
                    </div>
                </label>
                <label class="flex items-start gap-4 cursor-pointer group">
                    <div class="relative mt-0.5">
                        <input type="checkbox" name="print_show_sig" value="1" <?= $s['print_show_sig'] == '1' ? 'checked' : '' ?>
                            class="sr-only peer">
                        <div class="w-11 h-6 bg-slate-200 peer-checked:bg-indigo-600 rounded-full transition-colors"></div>
                        <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform peer-checked:translate-x-5"></div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-800">Show Signature Block</p>
                        <p class="text-xs text-slate-500">Include the Student Name / Class / Parent Signature / Date lines at the bottom of the printout.</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- ── Billing Configuration ── -->
        <div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
                <h2 class="text-sm font-black text-slate-800 uppercase tracking-widest flex items-center gap-2">
                    <i class="fas fa-file-invoice-dollar text-amber-500"></i> Billing Configuration
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">How stationery charges are added to student fee accounts.</p>
            </div>
            <div class="p-6 space-y-4">
                <div class="flex flex-col gap-1.5">
                    <label class="text-[0.5rem] font-black text-slate-400 uppercase tracking-widest">Billing Fee Name</label>
                    <div class="flex flex-wrap items-center gap-3">
                        <input type="text" name="billing_fee_name" value="<?= htmlspecialchars($s['billing_fee_name']) ?>"
                            class="bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm font-semibold outline-none focus:ring-2 focus:ring-indigo-500 flex-1 min-w-[140px]"
                            placeholder="stationery">
                        <?php if ($fee_check): ?>
                        <span class="flex items-center gap-1.5 text-xs font-black text-emerald-600 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-lg">
                            <i class="fas fa-circle-check"></i> Found: "<?= htmlspecialchars($fee_check['name']) ?>"
                        </span>
                        <?php else: ?>
                        <span class="flex items-center gap-1.5 text-xs font-black text-rose-600 bg-rose-50 border border-rose-200 px-3 py-1.5 rounded-lg">
                            <i class="fas fa-triangle-exclamation"></i> Fee not found in database
                        </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-400">
                        When you bill a student for a missing item, the system looks for a fee with this name (case-insensitive) in
                        <a href="../../finance/fees/view_fees.php" class="text-indigo-600 hover:underline" target="_blank">Finance → Fees</a>.
                        <?php if (!$fee_check): ?>
                        <strong class="text-rose-600">Create this fee first so billing works.</strong>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Save Button -->
        <div class="flex justify-end">
            <button type="submit"
                class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black uppercase tracking-widest px-8 py-3 rounded-2xl transition-colors flex items-center gap-2 shadow-sm">
                <i class="fas fa-floppy-disk"></i> Save All Settings
            </button>
        </div>

    </form>

</main>

</body>
</html>
