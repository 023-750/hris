<?php
require_once 'config/database.php';

$seeds = [
    'database/sample/1st_create_tables.sql',
    'database/sample/2nd_seed_data.sql'
];

foreach ($seeds as $file) {
    echo "Running $file...\n";
    $sql = file_get_contents($file);
    if ($conn->multi_query($sql)) {
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        echo "Successfully executed $file\n";
    } else {
        echo "Error executing $file: " . $conn->error . "\n";
        break;
    }
}
?>
