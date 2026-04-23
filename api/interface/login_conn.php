<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';
include "db_conn.php";

// Initialize login attempts counter if not set
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Validation function (moved outside)
function validate($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

if (isset($_POST['name']) && isset($_POST['pass'])) {

    $name = validate($_POST['name']);
    $pass = validate($_POST['pass']);

    if (empty($name)) {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 3) {
            $_SESSION['login_attempts'] = 0;
            header("Location: locked_login.php");
            exit();
        }
        header("Location: login.php?error=Email is Required");
        exit();
    } else if (empty($pass)) {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 3) {
            $_SESSION['login_attempts'] = 0;
            header("Location: locked_login.php");
            exit();
        }
        header("Location: login.php?error=Password is Required");
        exit();
    }

    // Secure query
    $stmt = $conn->prepare("SELECT * FROM accounts WHERE EMAIL = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $passfromdb = $row['PASS'];

        if (password_verify($pass, $passfromdb)) {
            $_SESSION['EMAIL'] = $row['EMAIL'];
            $_SESSION['ACC_ID'] = $row['ACC_ID'];
            $_SESSION['NAME'] = $row['LNAME'];

            date_default_timezone_set("Asia/Manila");
            $date = date('Y-m-d');
            $time = date('h:i A');

            $sql1 = "INSERT INTO login_history (ACC_ID, EMAIL, DATE_OF_LOGIN, TIME_OF_LOGIN)
                     VALUES ('{$row['ACC_ID']}', '{$row['EMAIL']}', '$date', '$time')";
            mysqli_query($conn, $sql1);

            $sql2 = "UPDATE accounts SET `status` = 'Online' WHERE ACC_ID = '{$row['ACC_ID']}'";
            mysqli_query($conn, $sql2);

            $_SESSION['login_attempts'] = 0;

            if ($row['ROLE'] === 'Admin') {
                header("Location: admin_homepage.php");
            } else {
                header("Location: homepage.php");
            }
            exit();
        } else {
            header("Location: login.php?error=Incorrect Email or Password");
            exit();
        }
    } else {
        $_SESSION['login_attempts']++;
        if ($_SESSION['login_attempts'] >= 3) {
            $_SESSION['login_attempts'] = 0;
            header("Location: locked_login.php");
            exit();
        }
        header("Location: login.php?error=Incorrect Email or Password");
        exit();
    }

} else {
    header("Location: login.php?error=Missing POST data");
    exit();
}
?>
