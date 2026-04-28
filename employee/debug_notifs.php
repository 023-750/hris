<?php
require_once '../config/database.php';
$res = $conn->query("SELECT * FROM notifications");
echo "<table border=1><tr><th>ID</th><th>User</th><th>Title</th><th>Link</th></tr>";
while ($row = $res->fetch_assoc()) {
    echo "<tr><td>{$row['notification_id']}</td><td>{$row['user_id']}</td><td>{$row['title']}</td><td>{$row['link']}</td></tr>";
}
echo "</table>";
?>
