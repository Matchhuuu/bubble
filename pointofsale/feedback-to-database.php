<?php
$host = 'localhost';
$dbname = 'bh';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$name = isset($_POST['name']) && trim($_POST['name']) !== '' ? trim($_POST['name']) : 'Anonymous';
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if (empty($comment)) {
    die("Comment is required.");
}

$stmt = $conn->prepare("INSERT INTO feedback (name, comment) VALUES (?, ?)");
$stmt->bind_param("ss", $name, $comment);

if ($stmt->execute()) {
    echo "Thank you for your feedback!";
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
