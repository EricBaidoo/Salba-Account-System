<?php
// Quick diagnostic — visit this page in browser, then DELETE it after use
include '../includes/db_connect.php';

echo "<h2>Users in Database</h2><table border='1' cellpadding='8'>";
echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Password (first 20 chars)</th><th>Is Hashed?</th><th>Fix Action</th></tr>";

$res = $conn->query("SELECT id, username, role, password FROM users ORDER BY id DESC");
while($row = $res->fetch_assoc()) {
    $is_hash = (strlen($row['password']) > 30 && substr($row['password'], 0, 1) === '$');
    echo "<tr>";
    echo "<td>{$row['id']}</td>";
    echo "<td><b>{$row['username']}</b></td>";
    echo "<td>{$row['role']}</td>";
    echo "<td>" . htmlspecialchars(substr($row['password'], 0, 30)) . "...</td>";
    echo "<td style='color:" . ($is_hash ? 'green' : 'red') . "'>" . ($is_hash ? '✅ Hashed' : '❌ Plain Text') . "</td>";
    echo "<td>";
    if (!$is_hash) {
        // Auto-fix: re-hash the plain text password
        $new_hash = password_hash($row['password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param('si', $new_hash, $row['id']);
        $stmt->execute();
        echo "<span style='color:orange'>⚠️ Auto-fixed! New password = <b>" . htmlspecialchars($row['password']) . "</b></span>";
    } else {
        echo "OK";
    }
    echo "</td></tr>";
}
echo "</table>";
echo "<br><p style='color:red'><b>DELETE this file after you are done!</b></p>";
?>
