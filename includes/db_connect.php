<?php
// db_connect.php
// Database connection file

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'ledgercore_db'; // Leaving DB name as is unless user wants to change the DB too

// Create connection
$dbc = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Check connection
if (!$dbc) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8mb4 for full unicode support
mysqli_set_charset($dbc, "utf8mb4");
?>
