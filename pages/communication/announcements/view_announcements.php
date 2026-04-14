<?php
include '../../../includes/db_connect.php';
include '../../../includes/auth_functions.php';
include '../../../includes/system_settings.php';

if (!is_logged_in()) { header('Location: ../../../includes/login.php'); exit; }

$school_name          = getSystemSetting($conn, 'school_name', 'Salba Montessori');
$current_term         = getCurrentTerm($conn);
$academic_year        = getAcademicYear($conn);
$display_academic_year = formatAcademicYearDisplay($conn, $academic_year);
$user_role            = $_SESSION['role'] ?? 'staff';

// Handle new announcement POST (admin/supervisor only)
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($user_role, ['admin','supervisor'])) {
    $title   = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $audience = $_POST['audience'] ?? 'all';
    $created_by = $_SESSION['username'] ?? 'Unknown';

    if ($title && $message) {
        $ins = $conn->prepare("INSERT INTO announcements (title, message, audience, created_by, created_at) VALUES (?,?,?,?,NOW())");
        if ($ins) {
            $ins->bind_param('ssss', $title, $message, $audience, $created_by);
            if ($ins->execute()) {
                $flash = ['type' => 'success', 'message' => 'Announcement posted successfully.'];
            } else {
                $flash = ['type' => 'danger', 'message' => 'Error posting: ' . $conn->error];
            }
            $ins->close();
        } else {
            // Table may not exist yet
            $conn->query("CREATE TABLE IF NOT EXISTS announcements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                audience VARCHAR(50) DEFAULT 'all',
                created_by VARCHAR(100),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $flash = ['type' => 'warning', 'message' => 'Announcements table was just created. Please try again.'];
        }
    } else {
        $flash = ['type' => 'danger', 'message' => 'Title and message are required.'];
    }
}

// Fetch announcements
$announcements = [];
$r = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 50");
if ($r) {
    while ($row = $r->fetch_assoc()) $announcements[] = $row;
} else {
    // Table not yet created — create silently
    $conn->query("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        audience VARCHAR(50) DEFAULT 'all',
        created_by VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements — <?php echo htmlspecialchars($school_name); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../../assets/css/style.css">
</head>
<body class="clean-page">

    <?php include '../../../includes/sidebar.php'; ?>

    <main class="ml-72 min-h-screen">

        <!-- Header -->
        <div class="clean-page-header">
            <div class="mb-1">
                <a href="../dashboard.php" class="clean-back-px-3 py-2 rounded">
                    <i class="fas fa-arrow-left"></i> Back to Communication
                </a>
            </div>
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="clean-page-title"><i class="fas fa-bullhorn mr-2 text-orange-500"></i>Announcements</h1>
                    <p class="clean-page-subtitle">Post and manage school-wide announcements</p>
                </div>
                <?php if (in_array($user_role, ['admin','supervisor'])): ?>
                <button onclick="document.getElementById('new-announcement').classList.toggle('hidden')"
                        class="rounded-clean-primary">
                    <i class="fas fa-plus"></i> New Announcement
                </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($flash): ?>
        <div class="clean-alert clean-alert-<?php echo $flash['type']; ?> mx-8 mt-4">
            <i class="fas fa-<?php echo $flash['type'] === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <span><?php echo htmlspecialchars($flash['message']); ?></span>
        </div>
        <?php endif; ?>

        <!-- New Announcement Form -->
        <?php if (in_array($user_role, ['admin','supervisor'])): ?>
        <div id="new-announcement" class="<?php echo (isset($_POST['title']) && $flash && $flash['type'] !== 'success') ? '' : 'hidden'; ?> mx-8 mt-4">
            <div class="bg-white rounded-xl border border-orange-200 shadow-sm p-6">
                <h3 class="font-bold text-gray-800 mb-4 text-sm uppercase tracking-wide text-orange-600">
                    <i class="fas fa-bullhorn mr-2"></i>Post New Announcement
                </h3>
                <form method="POST" action="">
                    <div class="clean-form-group">
                        <label class="clean-label" for="ann-title">Title *</label>
                        <input type="text" class="clean-input" id="ann-title" name="title"
                               placeholder="Announcement title..." required>
                    </div>
                    <div class="clean-form-group">
                        <label class="clean-label" for="ann-message">Message *</label>
                        <textarea class="clean-textarea" id="ann-message" name="message" rows="4"
                                  placeholder="Write your announcement..." required></textarea>
                    </div>
                    <div class="clean-form-group">
                        <label class="clean-label" for="ann-audience">Audience</label>
                        <select class="clean-select" id="ann-audience" name="audience">
                            <option value="all">Everyone (Staff + Parents)</option>
                            <option value="staff">Staff Only</option>
                            <option value="parents">Parents Only</option>
                        </select>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="rounded-clean-primary">
                            <i class="fas fa-paper-plane"></i> Post Announcement
                        </button>
                        <button type="button" onclick="document.getElementById('new-announcement').classList.add('hidden')"
                                class="rounded-clean-outline">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Announcements List -->
        <div class="mx-8 mt-4 space-y-3 pb-8">
            <?php if (empty($announcements)): ?>
            <div class="clean-empty-state">
                <div class="clean-empty-icon"><i class="fas fa-bullhorn"></i></div>
                <h4 class="clean-empty-title">No Announcements Yet</h4>
                <p class="clean-empty-text">Post the first announcement to notify staff and parents.</p>
            </div>
            <?php else: ?>
            <?php foreach ($announcements as $ann): ?>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="clean-badge clean-badge-warning">
                                <i class="fas fa-bullhorn"></i>
                                <?php echo htmlspecialchars(ucfirst($ann['audience'] ?? 'all')); ?>
                            </span>
                            <span class="text-xs text-gray-400">
                                by <strong><?php echo htmlspecialchars($ann['created_by'] ?? '—'); ?></strong>
                                &middot; <?php echo date('M j, Y g:i A', strtotime($ann['created_at'])); ?>
                            </span>
                        </div>
                        <h3 class="font-bold text-gray-800"><?php echo htmlspecialchars($ann['title']); ?></h3>
                        <p class="text-sm text-gray-500 mt-1"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                    </div>
                    <?php if ($user_role === 'admin'): ?>
                    <form method="POST" action="delete_announcement.php" onsubmit="return confirm('Delete this announcement?')">
                        <input type="hidden" name="id" value="<?php echo $ann['id']; ?>">
                        <button type="submit" class="text-gray-300 hover:text-red-500 transition-colors">
                            <i class="fas fa-trash-alt"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </main>
</body>
</html>
