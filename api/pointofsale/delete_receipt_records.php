<?php

include "db_conn.php";

// Delete Receipt Orders After File Creation
$sql1 = "DELETE FROM order_items";

$result1 = $conn->query($sql1);

header("Location: receipt_records.php");
exit();

