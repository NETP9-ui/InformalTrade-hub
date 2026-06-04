<?php
session_start();

// Clear the current user session, then return visitors to the home page
session_destroy();
header('Location: index.php');
exit;
?>
