<?php
require_once '../includes/db_connect.php';

// Check if column exists
$check = mysqli_query($dbc, "SHOW COLUMNS FROM users LIKE 'recovery_phrase_hash'");
if (mysqli_num_rows($check) == 0) {
    // Add column
    $sql = "ALTER TABLE users ADD COLUMN recovery_phrase_hash VARCHAR(255) DEFAULT NULL AFTER password";
    if (mysqli_query($dbc, $sql)) {
        echo "Successfully added recovery_phrase_hash column to users table.";
    } else {
        echo "Error adding column: " . mysqli_error($dbc);
    }
} else {
    echo "Column recovery_phrase_hash already exists.";
}
?>
