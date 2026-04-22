<?php
include "db_conn.php";


// Get the submitted star rating
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;

// Validate rating is between 1 and 5
if ($rating < 1 || $rating > 5) {
    die("Invalid rating submitted.");
}

// Prepare and insert into database
$stmt = $conn->prepare("INSERT INTO ratings (stars) VALUES (?)");
$stmt->bind_param("i", $rating);

if ($stmt->execute()) {
    echo "Thank you for rating!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
