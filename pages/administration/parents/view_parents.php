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
        .dataTables_wrapper .dataTables_filter input {
            border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.25rem 0.75rem; margin-left: 0.5rem; outline: none;
        }
        .dataTables_wrapper .dataTables_filter input:focus { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.1); }
        .dataTables_wrapper .dataTables_length select { border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.25rem 2rem 0.25rem 0.75rem; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen">
        <!-- Header -->
        <div class="bg-white border-b border-gray-100 px-8 py-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
            <div>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-users text-indigo-600"></i> Parents Directory
                </h1>
                <p class="text-gray-500 mt-2 font-medium">
                    Manage all parents, guardians, and their linked children.
                </p>
            </div>
            <div class="flex flex-col sm:flex-row gap-3 mt-4 md:mt-0">
                <a href="add_parent.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-gray-200 text-gray-700 text-sm font-bold rounded-xl hover:bg-gray-50 shadow-sm transition-all">
                    <i class="fas fa-user-plus text-indigo-600"></i> New Parent
                </a>
                <a href="../students/add_student_form.php" class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white text-sm font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-all">
                    <i class="fas fa-plus"></i> New Student
                </a>
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
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="p-6">
                    <table id="parentsTable" class="w-full text-left text-sm text-gray-600">
                        <thead class="bg-gray-50 text-gray-900 text-xs uppercase font-bold tracking-wider">
                            <tr>
                                <th class="px-6 py-4 rounded-tl-xl">Parent Name</th>
                                <th class="px-6 py-4">Contact Number</th>
                                <th class="px-6 py-4">Linked Children</th>
                                <th class="px-6 py-4">Address</th>
                                <th class="px-6 py-4 text-right rounded-tr-xl">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-indigo-50/30 transition-colors">
                                        <td class="px-6 py-4 font-bold text-gray-900 flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-xs flex-shrink-0">
                                                <?= strtoupper(substr($row['last_name'] ?: $row['first_name'] ?: 'P', 0, 1)) ?>
                                            </div>
                                            <div>
                                                <?= htmlspecialchars(trim($row['title'] . ' ' . $row['first_name'] . ' ' . $row['last_name'])) ?>
                                                <?php if($row['is_primary']): ?>
                                                    <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-[0.6rem] font-bold bg-green-100 text-green-800" title="Receives SMS Notifications">SMS</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?= htmlspecialchars($row['phone'] ?: 'N/A') ?>
                                        </td>
                                        <td class="px-6 py-4">
                                            <?php if ($row['linked_students']): ?>
                                                <div class="flex flex-wrap gap-1">
                                                    <?php foreach(explode(', ', $row['linked_students']) as $child): ?>
                                                        <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold"><?= htmlspecialchars($child) ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-gray-400 italic text-xs">No children linked</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 text-xs">
                                            <?= htmlspecialchars($row['address'] ?: 'N/A') ?>
                                        </td>
                                        <td class="px-6 py-4 text-right">
                                            <div class="flex items-center justify-end gap-2">
                                                <a href="edit_parent.php?id=<?= $row['id'] ?>" class="text-indigo-600 hover:text-indigo-900 font-bold text-xs px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors">
                                                    Edit
                                                </a>
                                                <form method="POST" action="delete_parent.php" onsubmit="return confirm('Are you sure you want to delete this parent? This will unlink them from any children.');" class="inline-block">
                                                    <input type="hidden" name="parent_id" value="<?= $row['id'] ?>">
                                                    <button type="submit" class="text-red-600 hover:text-red-900 font-bold text-xs px-3 py-1.5 bg-red-50 hover:bg-red-100 rounded-lg transition-colors">
                                                        Delete
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
            $('#parentsTable').DataTable({
                pageLength: 25,
                language: {
                    search: "",
                    searchPlaceholder: "Search parents..."
                },
                dom: '<"flex flex-col md:flex-row justify-between items-center mb-4"lf>rt<"flex flex-col md:flex-row justify-between items-center mt-4"ip>'
            });
            // Tailwind styling for DataTable
            $('.dataTables_filter input').addClass('bg-gray-50 text-sm');
            $('.dataTables_length select').addClass('bg-gray-50 text-sm');
        });
    </script>
</body>
</html>
