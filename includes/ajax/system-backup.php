<?php
require_once '../../includes/session-check.php';
checkRole(['Admin']);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'raquel_hris';

$backup_dir = dirname(dirname(__DIR__)) . '/backups/';
$filename = 'raquel_hris_backup_' . date('Y-m-d_His') . '.sql';
$dest_path = $backup_dir . $filename;

// mysqldump command for XAMPP (Windows)
$mysqldump_path = 'C:\xampp\mysql\bin\mysqldump.exe';

if (!file_exists($mysqldump_path)) {
    // Fallback: try relative path if first one fails for some reason
    $mysqldump_path = 'mysqldump'; 
}

// Build command
// Note: --user, --password (no space!), --host, and redirection to file
$command = sprintf(
    '"%s" --user=%s --password=%s --host=%s %s > "%s"',
    $mysqldump_path,
    $db_user,
    $db_pass != '' ? $db_pass : "''",
    $db_host,
    $db_name,
    $dest_path
);

// Run the command
exec($command, $output, $return_var);

if ($return_var === 0 && file_exists($dest_path)) {
    // Log success
    require_once '../../includes/functions.php';
    logAudit($conn, $_SESSION['user_id'], 'CREATE', 'Backup', 0, 'Created system backup: ' . $filename);
    
    echo json_encode([
        'success' => true,
        'filename' => $filename,
        'size' => filesize($dest_path)
    ]);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'MySQL Dump failed. Return code: ' . $return_var . '. Ensure mysqldump is available and permissions are correct.',
        'command_debug' => $command // Useful for troubleshooting if it fails
    ]);
}
?>
