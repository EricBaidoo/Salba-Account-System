<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

// Enforce admin only
if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

// Fetch all parents and their linked children
$query = "
    SELECT p.id, p.title, p.first_name, p.last_name, p.phone, p.email, p.address, p.is_primary,
           GROUP_CONCAT(DISTINCT CONCAT(s.first_name, ' ', s.last_name) SEPARATOR ', ') as linked_students
    FROM parents p
    LEFT JOIN student_parents sp ON p.id = sp.parent_id
    LEFT JOIN students s ON sp.student_id = s.id
    GROUP BY p.id
    ORDER BY p.id DESC
";
$result = $conn->query($query);

// Helper to extract initials
function getInitials($first_name, $last_name) {
    $f = function_exists('mb_substr') ? mb_substr(trim($first_name ?? ''), 0, 1) : substr(trim($first_name ?? ''), 0, 1);
    $l = function_exists('mb_substr') ? mb_substr(trim($last_name ?? ''), 0, 1) : substr(trim($last_name ?? ''), 0, 1);
    $initials = strtoupper($f . $l);
    return !empty($initials) ? $initials : 'P';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parents Directory - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
        
        /* Premium custom sorting arrow styling */
        table.dataTable thead th {
            position: relative;
            background-image: none !important;
            cursor: pointer;
        }
        table.dataTable thead th::after {
            content: "\f0dc";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            right: 1.25rem;
            top: 50%;
            transform: translateY(-50%);
            color: #cbd5e1;
            font-size: 0.75rem;
            transition: color 0.2s;
        }
        table.dataTable thead th.sorting_asc::after {
            content: "\f0de";
            color: #4f46e5;
        }
        table.dataTable thead th.sorting_desc::after {
            content: "\f0dd";
            color: #4f46e5;
        }
        
        /* Custom DataTables Pagination Styling */
        .dataTables_wrapper .dataTables_paginate {
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.25rem;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            border: 0.0625rem solid #e2e8f0 !important;
            border-radius: 0.75rem !important;
            background: #ffffff !important;
            color: #4b5563 !important;
            padding: 0.5rem 0.875rem !important;
            font-size: 0.875rem !important;
            font-weight: 600 !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            box-shadow: 0 0.0625rem 0.125rem 0 rgba(0, 0, 0, 0.05) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background: #f9fafb !important;
            color: #111827 !important;
            border-color: #d1d5db !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background: #4f46e5 !important;
            color: #ffffff !important;
            border-color: #4f46e5 !important;
            box-shadow: 0 4px 6px -1px rgba(79, 70, 229, 0.1), 0 2px 4px -1px rgba(79, 70, 229, 0.06) !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
            background: #4338ca !important;
            color: #ffffff !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            background: #f9fafb !important;
            color: #9ca3af !important;
            border-color: #f3f4f6 !important;
            cursor: not-allowed !important;
            opacity: 0.6 !important;
        }
        .dataTables_wrapper .dataTables_info {
            color: #6b7280 !important;
            font-size: 0.875rem !important;
            font-weight: 500 !important;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen">
        <!-- Header -->
        <div class="bg-white border-b border-gray-100 px-8 py-6">
            <div class="flex items-center gap-3 mb-4">
                <a href="../dashboard" class="text-gray-400 hover:text-indigo-600 transition-colors flex items-center gap-2 text-sm font-black uppercase tracking-widest">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                        <i class="fas fa-users text-indigo-600"></i> Parents Directory
                    </h1>
                    <p class="text-gray-500 mt-2 font-medium">
                        Manage all parents, guardians, and their linked children.
                    </p>
                </div>
            <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-6 mt-4 md:mt-0 w-full xl:w-auto flex-1 xl:justify-end">
                <!-- Search Bar moved to top header -->
                <div class="relative w-full xl:w-[400px] max-w-lg">
                    <input type="text" 
                           id="customSearchInput" 
                           placeholder="Search parents by name, phone, or email..." 
                           class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl pl-10 pr-4 py-3 outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all text-sm shadow-sm font-medium" 
                           autocomplete="off">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                        <i class="fas fa-search text-sm"></i>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
                    <a href="add_parent.php" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-50 shadow-sm transition-all whitespace-nowrap">
                        <i class="fas fa-user-plus text-indigo-600"></i> New Parent
                    </a>
                    <a href="../students/add_student_form.php" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-all whitespace-nowrap">
                        <i class="fas fa-plus"></i> New Student
                    </a>
                </div>
            </div>
        </div>
    </div>

        <div class="p-8">
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl flex items-center gap-3">
                    <i class="fas fa-check-circle"></i> <span><?= htmlspecialchars($_SESSION['success_msg']) ?></span>
                </div>
                <?php unset($_SESSION['success_msg']); ?>
            <?php endif; ?>
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($_SESSION['error_msg']) ?></span>
                </div>
                <?php unset($_SESSION['error_msg']); ?>
            <?php endif; ?>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden p-6 md:p-8">
                <!-- Custom DataTables Filters Header -->
                <div class="flex flex-col sm:flex-row justify-end items-center gap-4 mb-6">
                    <!-- Search input was moved to top header -->
                    
                    <div class="flex items-center gap-3 w-full sm:w-auto justify-end">
                        <label class="text-xs font-black text-gray-400 uppercase tracking-wider">Show</label>
                        <div class="relative">
                            <select id="customLengthSelect" class="bg-gray-50 border border-gray-200 text-gray-900 rounded-xl pl-3 pr-8 py-2 outline-none focus:bg-white focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all text-sm appearance-none cursor-pointer shadow-sm font-bold">
                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                            <div class="absolute inset-y-0 right-0 pr-2.5 flex items-center pointer-events-none text-gray-400 text-[10px]">
                                <i class="fas fa-chevron-down"></i>
                            </div>
                        </div>
                        <span class="text-xs font-black text-gray-400 uppercase tracking-wider">entries</span>
                    </div>
                </div>

                <div class="overflow-x-auto rounded-xl">
                    <table id="parentsTable" class="w-full text-left text-sm text-gray-600 divide-y divide-gray-100">
                        <thead class="bg-gray-50 text-gray-900 text-xs uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-6 py-4 rounded-tl-xl">Parent Name</th>
                                <th class="px-6 py-4">Contact Number</th>
                                <th class="px-6 py-4">Linked Children</th>
                                <th class="px-6 py-4">Address</th>
                                <th class="px-6 py-4 text-right rounded-tr-xl">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): 
                                    // Generate distinct aesthetic color scheme per row initials
                                    $name_hash = crc32($row['first_name'] . ' ' . $row['last_name']);
                                    $bg_colors = [
                                        'bg-indigo-50 text-indigo-700 border-indigo-100',
                                        'bg-emerald-50 text-emerald-700 border-emerald-100',
                                        'bg-violet-50 text-violet-700 border-violet-100',
                                        'bg-amber-50 text-amber-700 border-amber-100',
                                        'bg-rose-50 text-rose-700 border-rose-100',
                                        'bg-sky-50 text-sky-700 border-sky-100',
                                        'bg-teal-50 text-teal-700 border-teal-100',
                                        'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-100'
                                    ];
                                    $avatar_color = $bg_colors[$name_hash % count($bg_colors)];
                                    $initials = getInitials($row['first_name'], $row['last_name']);
                                ?>
                                    <tr class="hover:bg-indigo-50/20 transition-colors">
                                        <td class="px-6 py-4 font-bold text-gray-900">
                                            <div class="flex items-center gap-3">
                                                <div class="w-9 h-9 rounded-xl border flex items-center justify-center font-black text-xs flex-shrink-0 <?= $avatar_color ?>">
                                                    <?= $initials ?>
                                                </div>
                                                <div class="flex flex-col">
                                                    <span class="leading-tight"><?= htmlspecialchars(trim($row['title'] . ' ' . $row['first_name'] . ' ' . $row['last_name'])) ?></span>
                                                    <?php if($row['is_primary']): ?>
                                                        <span class="mt-1 self-start inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-100" title="Receives SMS Notifications">
                                                            <i class="fas fa-comment-sms text-[8px]"></i> SMS Enabled
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 font-semibold text-gray-700">
                                            <?php if($row['phone']): ?>
                                                <span class="flex items-center gap-1.5 whitespace-nowrap">
                                                    <i class="fas fa-phone text-slate-400 text-xs"></i>
                                                    <?= htmlspecialchars($row['phone']) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($row['linked_students']): ?>
                                                <div class="flex flex-wrap gap-1.5">
                                                    <?php foreach(explode(', ', $row['linked_students']) as $child): ?>
                                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-indigo-50/50 text-indigo-700 rounded-lg text-xs font-semibold border border-indigo-100/30 whitespace-nowrap">
                                                            <i class="fas fa-graduation-cap text-[10px] text-indigo-400"></i>
                                                            <?= htmlspecialchars($child) ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 text-gray-400 italic text-xs font-medium">
                                                    <i class="fas fa-users-slash text-[10px]"></i> No children linked
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-xs font-medium text-gray-600 max-w-xs truncate" title="<?= htmlspecialchars($row['address'] ?? '') ?>">
                                            <?php if($row['address']): ?>
                                                <span class="flex items-center gap-1.5">
                                                    <i class="fas fa-map-marker-alt text-slate-400 text-xs flex-shrink-0"></i>
                                                    <span class="truncate"><?= htmlspecialchars($row['address']) ?></span>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2.5">
                                                <a href="edit_parent.php?id=<?= $row['id'] ?>" class="w-8 h-8 rounded-xl bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white flex items-center justify-center hover:shadow-md hover:shadow-indigo-100 transition-all duration-200" title="Edit Profile">
                                                    <i class="fas fa-user-edit text-xs"></i>
                                                </a>
                                                <form method="POST" action="delete_parent.php" onsubmit="return confirm('Are you sure you want to delete this parent? This will unlink them from any children.');" class="inline-block">
                                                    <input type="hidden" name="parent_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="w-8 h-8 rounded-xl bg-red-50 text-red-600 hover:bg-red-600 hover:text-white flex items-center justify-center hover:shadow-md hover:shadow-red-100 transition-all duration-200" title="Delete Parent">
                                                        <i class="fas fa-trash-alt text-xs"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#parentsTable').DataTable({
                pageLength: 25,
                dom: 'rtip', // Hide default DataTables search box and entries select
                ordering: true,
                info: true,
                paging: true,
                language: {
                    paginate: {
                        previous: "<i class='fas fa-chevron-left'></i>",
                        next: "<i class='fas fa-chevron-right'></i>"
                    }
                }
            });

            // Bind Custom Search Input
            $('#customSearchInput').on('input', function() {
                table.search(this.value).draw();
            });

            // Bind Custom Length Dropdown
            $('#customLengthSelect').on('change', function() {
                table.page.len(parseInt(this.value)).draw();
            });
        });
    </script>
</body>
</html>
