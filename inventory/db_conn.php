<?php

$conn = mysqli_connect("localhost","root","","bh");

//Check Connection
if (mysqli_connect_errno()){
    echo "Failed to Connect to MySQL: " . mysqli_connect_error();
    exit();
}


?>