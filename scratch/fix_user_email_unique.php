<?php
require_once 'config/database.php';

$sql = "ALTER TABLE users DROP INDEX email";
if ($conn->query($sql)) {
    echo "Removed unique constraint on email.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$sql = "ALTER TABLE users ADD INDEX (email)";
if ($conn->query($sql)) {
    echo "Added non-unique index on email.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
?>
