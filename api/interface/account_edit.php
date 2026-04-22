<?php

session_start();
include "db_conn.php";

if(isset($_SESSION['ACC_ID'])  && isset($_SESSION['EMAIL'])){ 

$connection = $conn;

$id = "";
$fname = "";
$lname = "";
$email = "";
$pass = "";
$orig_pass = "";
$contact = "";
$bday = "";

$errorMessage = "";
$successMessage = "";

if ($_SERVER['REQUEST_METHOD'] =='GET'){
    //GET

    if ( !isset($_GET['id'])) {
        header("location: bubble/interface/admin_homepage.php");
        exit;
    }

    $id = $_GET["id"];

    $sql1 = "   SELECT ACC_ID, FNAME, LNAME, EMAIL, ORIG_PASS, CNTC_NUM, BDAY
                FROM accounts
                WHERE ACC_ID = $id";

    $result1 = $connection->query($sql1);
    $row = $result1->fetch_assoc();    
    
    if (!$row) {
        header("location: /hotelreservation/back-end/all_table.php");
        exit;
    }

    $id = $row["ACC_ID"];
    $fname = $row["FNAME"];
    $lname = $row["LNAME"];
    $email = $row["EMAIL"];
    $pass = $row["ORIG_PASS"];
    $contact = $row["CNTC_NUM"];
    $bday = $row["BDAY"];
}

else {
    //POST
    $id = $_POST["id"];
    $fname = $_POST["fname"];
    $lname = $_POST["lname"];
    $email = $_POST["email"];
    $pass = $_POST["password"];
    $contact = $_POST["contact"];
    $bday = $_POST["bday"];

    do{
        if ( empty($fname) || empty($lname) || empty($email) || empty($pass) || empty($contact) || empty($bday)) {
            $errorMessage = "All Fields Must Be Required";
            break;
        }

         //Hash Code
        $hashedpass = password_hash($pass, PASSWORD_DEFAULT);

    // Step 1
        $sql = "UPDATE accounts
                 SET    FNAME='$fname', 
                        LNAME='$lname', 
                        EMAIL='$email', 
                        PASS='$hashedpass',
                        CNTC_NUM='$contact', 
                        BDAY='$bday'
                 WHERE ACC_ID = $id";

        $result = $connection->query($sql);

        if (!$result) {
            $errorMessage = "Invalid Query: " . $connection->error;
            break;
        }

        $successMessage = "Client Addedd Successfully";

        header("location: /interface/user_accounts.php");
        exit;
    }while (false);
}   

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href=/media/BUBBLE.jpg></link>
    <link rel="stylesheet" href="/fonts/fonts.css">

    <title>Edit Employee Accounts</title>
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
}

.left-a, .right-a{
  position: relative;
}

.right {
  position: relative;
  height: auto;
  width: 50%;
  padding: 20px;
}

.inputs {
    padding: 10px;
}

input {
  border-color: solid #337609;
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
    
            <center><h1>Edit Employee's Info</h1></center>

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

        <div class="inputs">
            <form method="post">
                <input type="hidden" name="id" value="<?php echo $id; ?>">

                <div class="main">
                    <div class="left">
                        <div class="left-a">

                            <label>First Name</label>
                            <div style="margin-right: 5%;">
                                <input style="font-family: Poppins;" type="text" class="form-control" autocomplete="off" name="fname" value="<?php echo $fname; ?>">

                            </div>

                            <label>Email</label>
                            <div style="margin-right: 5%;">
                                <input style="font-family: Poppins;" type="email" class="form-control" autocomplete="off" name="email" value="<?php echo $email; ?>">

                            </div>

                            <label>Contact Number</label>
                            <div style="margin-right: 5%;">
                                <input style="font-family: Poppins;" type="text" class="form-control" autocomplete="off" name="contact" value="<?php echo $contact; ?>">

                            </div>                        
                        </div>
                    </div>

                    <div class="right">     
                        <div class="right-a">

                            <label>Last Name</label>
                            <div>
                                <input style="font-family: Poppins;" type="text" class="form-control" autocomplete="off"  name="lname" value="<?php echo $lname; ?>">

                            </div>

                            <label>Password</label>
                            <div class="col-sm-31">
                                <input style="font-family: Poppins;" type="text" class="form-control" autocomplete="off" name="password" value="<?php echo $pass; ?>">

                            </div>

                            <label>Birthday</label>
                            <div class="col-sm-31">
                                <input style="font-family: Poppins;" type="date" class="form-control" autocomplete="off" name="bday" value="<?php echo $bday; ?>">

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
                    <form action="user_accounts.php"><button type="submit" class="btn1"> Cancel </button></form>   
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
