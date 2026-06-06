<?php
include '../../../includes/auth_check.php';
include '../../../includes/db_connect.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ' . BASE_URL . 'index');
    exit;
}

$error_msg = '';
$success_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $relationship = trim($_POST['relationship'] ?? 'guardian');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $is_primary = isset($_POST['is_primary']) ? 1 : 0;

    if (empty($first_name) || empty($last_name)) {
        $error_msg = "First Name and Last Name are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO parents (title, first_name, last_name, relationship, phone, email, address, is_primary) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssi", $title, $first_name, $last_name, $relationship, $phone, $email, $address, $is_primary);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Parent successfully created.";
            header("Location: view_parents.php");
            exit;
        } else {
            $error_msg = "Error creating parent: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Parent - Administration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../../assets/css/style.css">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="admin-main-content lg:ml-72 min-h-screen">
        <div class="bg-white border-b border-gray-100 px-8 py-6 flex justify-between items-center">
            <div>
                <a href="view_parents.php" class="text-sm font-bold text-indigo-600 hover:text-indigo-800 mb-2 inline-block"><i class="fas fa-arrow-left mr-1"></i> Back to Directory</a>
                <h1 class="text-3xl font-extrabold text-gray-900 flex items-center gap-3">
                    <i class="fas fa-user-plus text-indigo-600"></i> Add New Parent
                </h1>
                <p class="text-gray-500 mt-2 font-medium">Register a new parent or guardian independently.</p>
            </div>
        </div>

        <div class="p-8 max-w-4xl mx-auto mt-4">
            <?php if ($error_msg): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl flex items-center gap-3">
                    <i class="fas fa-exclamation-circle"></i> <span><?= htmlspecialchars($error_msg) ?></span>
                </div>
            <?php endif; ?>

            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <form method="POST" class="p-8">
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Title</label>
                            <select name="title" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                <option value="Mr.">Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="first_name" required class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="last_name" required class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Relationship</label>
                            <select name="relationship" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                                <option value="mother">Mother</option>
                                <option value="father">Father</option>
                                <option value="guardian" selected>Guardian</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Phone Number</label>
                            <input type="text" name="phone" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">Email Address</label>
                            <input type="email" name="email" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all">
                        </div>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-bold text-gray-700 mb-2">Address / Place of Stay</label>
                        <textarea name="address" rows="2" class="w-full bg-gray-50 border border-gray-200 text-gray-900 rounded-xl px-4 py-2.5 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition-all"></textarea>
                    </div>

                    <div class="mb-8">
                        <label class="flex items-center gap-3 cursor-pointer p-4 rounded-xl border border-gray-200 hover:bg-gray-50 transition-colors">
                            <input type="checkbox" name="is_primary" value="1" class="w-5 h-5 text-indigo-600 rounded focus:ring-indigo-500 border-gray-300">
                            <div>
                                <div class="font-bold text-gray-900 text-sm">Receive SMS Notifications</div>
                                <div class="text-xs text-gray-500">Check this box if this parent should receive SMS communications like instant payment receipts and updates.</div>
                            </div>
                        </label>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-gray-100">
                        <button type="submit" class="px-6 py-2.5 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 shadow-sm transition-all flex items-center gap-2">
                            <i class="fas fa-save"></i> Save Parent
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

</body>
</html>
