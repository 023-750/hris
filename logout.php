<?php
session_start();
session_unset();
session_destroy();
header("Location: /raquel-hris/index.php");
exit();
?>