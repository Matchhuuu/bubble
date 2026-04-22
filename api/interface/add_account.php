<?php

session_start();
include "db_conn.php";

if(isset($_SESSION['ACC_ID'])  && isset($_SESSION['EMAIL'])){ 

include "db_conn.php"; $connection = $conn;

$id = "";
$fname = "";
$lname = "";
$email = "";
$pass = "";
$confirm_pass = "";
$contact = "";
$bday = "";
$role = "";


$errorMessage = "";
$errorMessage2 = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fname = $_POST["fname"];
    $lname = $_POST["lname"];
    $role = $_POST["role"];
    $email = $_POST["email"];
    $pass = $_POST["password"];
    $confirm_pass = $_POST["confirm_password"];
    $contact = $_POST["contact"];
    $bday = $_POST["bday"];

    do {
        if (empty($fname) || empty($lname) || empty($email) || empty($pass) || empty($confirm_pass) || empty($contact) || empty($bday) || empty($role)) {
            $errorMessage = "All Fields Must Be Required";
            break;
        }

        // <CHANGE> Add email duplication check
        $checkEmailQuery = "SELECT * FROM accounts WHERE email = '$email'";
        $emailCheckResult = $connection->query($checkEmailQuery);
        
        if ($emailCheckResult->num_rows > 0) {
            $errorMessage = "Email already exists. Please use a different email.";
            break;
        }

        // <CHANGE> Add password validation checks
        if (strlen($pass) < 8) {
            $errorMessage = "Password must be at least 8 characters long.";
            break;
        }

        if ($pass !== $confirm_pass) {
            $errorMessage = "Passwords do not match.";
            break;
        }

        if (!preg_match('/[A-Z]/', $pass)) {
            $errorMessage = "Password must contain at least one uppercase letter.";
            break;
        }

        if (!preg_match('/[0-9]/', $pass)) {
            $errorMessage = "Password must contain at least one number.";
            break;
        }

        // Hash Code
        $hashedpass = password_hash($pass, PASSWORD_DEFAULT);

        $sql1 = "INSERT INTO accounts (fname, lname, role, status, email, orig_pass, pass, cntc_num, bday)" .
            "VALUES ('$fname', '$lname', '$role','Offline','$email', '$pass', '$hashedpass', '$contact', '$bday');";

        $result1 = $connection->query($sql1);

        if (!$result1) {
            $errorMessage = "Invalid Query: " . $connection->error;
            break;
        }

        $id = "";
        $fname = "";
        $lname = "";
        $email = "";
        $pass = "";
        $confirm_pass = "";
        $contact = "";
        $bday = "";

        $successMessage = "Client Added Successfully";

        header("location: /interface/user_accounts.php");
        exit;
    } while (false);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href=/media/BUBBLE.jpg></link>
    <link rel="stylesheet" href="/fonts/fonts.css">

    <title>Add New Employee Account</title>
</head>

<style>
.navbar {
    background-color: #7e5832;
    width: 100%;
    height: 80px;
    position: absolute; 
    top: 0; 
    left: 0; 
    z-index: 10px;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
    display: flex;
    justify-content:left;

}

.buttons{
    position: relative; 
    width: 370px;
    left: 30px;

    display: flex;
    justify-content:space-between;
    align-items:center;
}

.btn{

    font-family: Poppins;
    font-weight: bold;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);

    height: 40px;
    width: 150px;
    background-color: #337609;
    border: none;
    border-radius: 25px;
    transition: 0.5s;
}

.btn:hover {
    background-color: #326810;
    transition: 0.5s;
}

.space{
    position: relative; 

    width: 320px;
    left: 30px;

    display: flex;
    justify-content: space-between;
    align-items: center;
}

/* Dropdown Button */
.dropbtn {
    background-color: transparent;
    color: #f0f0f0;
    padding: 16px;
    min-width: 110px;
    font-size: 16px;
    border: none;
    cursor: pointer;

    font-family: Poppins;
    font-weight: bolder;
  }
  
  .dropbtn:hover {
    background-color: #5a4026;
  }

  
  /* The container <div> - needed to position the dropdown content */
  .dropdown {
    position: relative; 
    width: 490px;
    display: flex;
    justify-content: right;
  }
  
  /* Dropdown Content (Hidden by Default) */
  .dropdown-content {
    display: none;
    position: absolute;
    background-color: #f1f1f1;
    min-width: 110px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
    top:80px;

    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
  }
  
  /* Links inside the dropdown */
  .dropdown-content a {
    color: black;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
  }
  
  /* Change color of dropdown links on hover */
  .dropdown-content a:hover {
    background-color: #ddd;
    
    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
}
  
  /* Show the dropdown menu (use JS to add this class to the .dropdown-content container when the user clicks on the dropdown button) */
  .show {display:block;}

.list {
    position: relative;
    top: 100px;
}

.main {
  position: relative;
  margin: auto;
  width: 70%;
  display: flex;
  align-items: center;
}

.left {
  position: relative;
  height: auto;
  width: 50%;
  padding: 20px;
  align-self: flex-start;
  
}

.left-a, .right-a{
  position: relative;
  
}

.right {
  position: relative;
  height: auto;
  width: 50%;
  padding: 20px;
  align-self: flex-start;
}

.inputs {
    padding: 10px;
}

input {
  border-color: #337609 solid;
  border-radius: 10px;
  background-color: white;
  margin-bottom: 10px;
  color: #181818;
  height: 30px;
  width: 100%;
  text-indent: 10px;
  outline: none;
  box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);  
}


.submit {
  display: flex;
  width: 70%;
  margin: auto;
  justify-content: center;
}

.space1{
  width: 20%;
}


.btn1{
  width: 200px;
  background-color: #2c2c2c;
  box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
  border: none;
  height: 30px;
  margin: 10px 50px;
  border-radius: 15px;
  color: #f0f0f0;
  font-family: Poppins;
}

.btn2{
    position: relative;
    background-color: #2c2c2c;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
    top: 13px;
    border: none;
    height: 30px;
    padding: 5px 75px;;
    margin: 10px 50px;
    border-radius: 15px;
    color: #f0f0f0;
    font-family: Poppins;
    font-size: 14px;
    text-decoration: none;
}

.navbar-right {
    width: 50%;
    height: 80px;
    position: absolute; 
    top: 0; 
    right: 0; 
    z-index: 11;
    display: flex;
    justify-content:right;

}


::placeholder{
    color: #181818;
    font-family: Poppins;
    font-style: italic;
    opacity: 50%;
}

select{
    font-family: Poppins; 
    border-color: #337609; 
    border-radius: 10px; 
    background-color: white; 
    color: #181818; 
    height: 30px; 
    width: 101%; 
    text-indent: 10px; 
    outline: none; 
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
    font-style: italic;
}

select option{
    background-color: #ddd;
    color: #181818;
    scrollbar-highlight-color: #326810;
}




</style>



<body>
<div class="navbar">
            <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
            <div class="logo"><img src="/media/BUBBLE.jpg" width="80px"></div>
            <div class="buttons">
                <form action="/interface/user_accounts.php"><button type="submit" class="btn"> Back </button></form>
            </div>
            
        </div>

        <div class="navbar-right">
            <div class="dropdown">
                <button onclick="myFunction()" class="dropbtn">Admin</button>
                <div id="myDropdown" class="dropdown-content">
                    <a href="logout.php">Logout</a>
                </div>
            </div>
            <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
        </div>

        <script>
    // Dropdown
    function myFunction() {
        document.getElementById("myDropdown").classList.toggle("show");
    }

    window.onclick = function(event) {
        if (!event.target.matches('.dropbtn')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            var i;
            for (i = 0; i < dropdowns.length; i++) {
                var openDropdown = dropdowns[i];
                    if (openDropdown.classList.contains('show')) {
                        openDropdown.classList.remove('show');
                    }
            }
        }
    }
        </script>

</div>

<div class="list">
    
            <center><h1 style="margin: 2px;">Add New Employee</h1></center>

            <?php
            if (!empty($errorMessage)) {
                ?>
                <center><p class="error" 
                
                style=" background: #ff4545;
                        text-indent: 10px;
                        color: #f0f0f0;
                        padding: 5px;
                        width: 30%;
                        margin-bottom: 3px;
                        border-radius: 15px;"
                
                
                > <?php echo $errorMessage; ?> </p> 
                </button></form>   </center>
            <?php } ?>

            <?php
            if (!empty($errorMessage2)) {
                ?>
                <center><p class="error" 
                
                style=" background: #ff4545;
                        text-indent: 10px;
                        color: #f0f0f0;
                        padding: 5px;
                        width: 30%;
                        margin-bottom: 3px;
                        border-radius: 15px;"
                
                
                > <?php echo $errorMessage2; ?> </p> 
                </button></form>   </center>
            <?php } ?>
            

        <div class="inputs">
            <form method="post">
                <div class="main">
                    <div class="left">
                        <div class="left-a">

                            <label>First Name</label>
                            <div style="margin-right: 5%;">
                                <input style="font-family: Poppins;" type="text" class="form-control" placeholder="Enter First Name" autocomplete="off" name="fname" value="<?php echo $fname; ?>">

                            </div>

                            <label>Email</label>
                            <div style="margin-right: 5%;">
                                <input style="font-family: Poppins;" type="email" class="form-control" placeholder="lastname@bhemployee" autocomplete="off" name="email" value="<?php echo $email; ?>">

                            </div>

                            <label>Contact Number</label>
                            <div style="margin-right: 5%;">
                                <input style="font-family: Poppins;" type="text" class="form-control" placeholder="Enter Employee's Phone Number" autocomplete="off" name="contact" value="<?php echo $contact; ?>">

                            </div>
                            
                           <label>Role</label>
                            <div style="margin-right: 5%;">
                                <select name="role" class="drop-select">
                                    <option value="" disabled selected>Select Role</option>
                                    <option value="Admin" <?php if (isset($_POST['role']) && $_POST['role'] == 'Admin') echo 'selected'; ?>>Admin</option>
                                    <option value="Employee" <?php if (isset($_POST['role']) && $_POST['role'] == 'Employee') echo 'selected'; ?>>Employee</option>
                                </select>
                            </div>


                        </div>
                    </div>

                    <div class="right">     
                        <div class="right-a">

                            <label>Last Name</label>
                            <div>
                                <input style="font-family: Poppins;" type="text" class="form-control" placeholder="Enter Last Name" autocomplete="off"  name="lname" value="<?php echo $lname; ?>">

                            </div>

                            <label>Password</label>
                            <div class="col-sm-31">
                                <input style="font-family: Poppins;" type="text" class="form-control" placeholder="Default: bubblehideout" autocomplete="off" name="password" value="<?php echo $pass; ?>">

                            </div>

                            <label>Confirm Password</label>
                            <div class="col-sm-31">
                                <input style="font-family: Poppins;" type="text" class="form-control" placeholder="Re-enter Password" autocomplete="off" name="confirm_password" value="<?php echo $confirm_pass; ?>">
                            </div>

                            <label>Birthday</label>
                            <div class="col-sm-31">
                                <input style="font-family: Poppins;" type="date" class="form-control" placeholder="YYYY-MM-DD" autocomplete="off" name="bday" value="<?php echo $bday; ?>">

                            </div>

                        
                        </div>
                    </div>
                </div>

                <div class="submit">
                    <div class="sub1">
                    <button type="submit" class="btn1"> Submit </button>
                    </div>

                    <div class="space1"></div>

                    <div class="sub2">
                    <a class="btn2" href="/interface/user_accounts.php" role="button"> Cancel </a>
                    </div>
                </div>
            </form>
    </div>
    
                            
</div>


</body>
</html>

<?php
}

else  {
    header("Location: login.php");
    exit();
}

