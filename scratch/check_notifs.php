<?php
require_once 'config/database.php';
$res = $conn->query("SELECT notification_id, title, link FROM notifications WHERE user_id = 4");
while ($row = $res->fetch_assoc()) {
    echo "ID: " . $row['notification_id'] . " | Title: " . $row['title'] . " | Link: " . $row['link'] . "\n";
}
?>
