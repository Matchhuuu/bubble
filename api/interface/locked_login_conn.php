<?php

session_start();
include "db_conn.php";

if (isset($_POST['password'])) {
    
    function validate($data){
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    } 
}

$pass = validate($_POST['password']);

if (empty($pass)){
    header("Location: locked_login.php?error= Admin Key is Required");
    exit();
}


$sql = "SELECT * FROM admin_key
        WHERE ADMIN_KEY = '$pass'";

$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) === 1) {
    $row = mysqli_fetch_assoc($result);

    if ($row['ADMIN_KEY'] === $pass) {
        
        header("Location: login.php");
        exit();
    }

} 

else {
    header("Location: locked_login.php?error=Invalid Admin Key");
    exit();
}
?>
