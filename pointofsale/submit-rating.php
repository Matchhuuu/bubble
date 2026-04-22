<?php
// Database connection details
$host = 'localhost';
$dbname = 'bh';  
$user = 'root';
$pass = ''; 

$conn = new mysqli($host, $user, $pass, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
