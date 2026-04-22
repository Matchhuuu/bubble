<?php

include "db_conn.php";

// Delete Receipt Orders After File Creation
$sql1 = "DELETE FROM stock_history";

$result1 = $conn->query($sql1);

header("Location: add_stock_history.php");
exit();

