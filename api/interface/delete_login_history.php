<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';
include "db_conn.php";

$notif = "DELETE FROM login_history";

$result = mysqli_query($conn, $notif);

header("Location: login_records.php");
