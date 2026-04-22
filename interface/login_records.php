<?php
session_start();
include "db_conn.php";

if(isset($_SESSION['ACC_ID'])  && isset($_SESSION['EMAIL'])){ ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href="/bubble/media/BUBBLE.jpg"></link>
    <link rel="stylesheet" href="/bubble/interface/login_records.css">
    <link rel="stylesheet" href="/bubble/fonts/fonts.css">

    <title>Log In History</title>
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

.dropdown {
    position: relative; 
    width: 490px;
    display: flex;
    justify-content: right;
}

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

.dropdown-content a {
    color: black;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
}

.dropdown-content a:hover {
    background-color: #ddd;

    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;
}

.show {display:block;}

.list {
    position: relative;
    top: 100px;
}

th, td {
    padding: 10px;
}

table {
    position: relative;
    width: 90%;
    
    margin: auto;
    border-collapse: collapse;
}

tr {
    position: relative;
    border-bottom: 1px solid #ccc;
    padding: 20px;
    margin: 5px;
    
}

th {
    text-align: left;    
}


.btn-edit{
    text-decoration: none;
    padding: 5px;
    position: relative;
    background-color: rgb(91, 149, 91);
    
    border-radius: 5px;
    color: #f0f0f0;
    width: 5%;
    border-color: transparent;
    margin: 5px;
    font-family: Poppins;
}

.btn-edit:hover{
    background-color: rgb(79, 128, 79);
}

.btn-dgr{
    text-decoration: none;
    padding: 5px;
    position: relative;
    background-color: firebrick;
    
    border-radius: 5px;
    width: 5%;
    color: #f0f0f0;
    border-color: transparent;
    margin: 5px;
    font-family: Poppins;
    cursor: pointer;

    transition: 0.5s;
}

.btn-dgr:hover{
    background-color: #6d170cff; 
    transition: 0.5s;
}

.a {
    position: absolute;
    width:50%;
    height: 50%;
    z-index: 15;
    left: 25%;
    top: 25%;
}

.dropdown-ver{
    display: none;
    position: relative;
    background-color: #f1f1f1;
    width: 80%;
    padding: 30px;
    margin: auto;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
    top:80px;

    border-radius: 15px;

}

.b{
    display: flex;
    justify-content: space-evenly;
}

.a1{
    background-color: rgb(91, 149, 91);
    display: inline;
    color: #f0f0f0;
    width: 100px;
    border-radius: 15px;
    text-align: center;
    text-decoration: none;

    justify-content: center;
}

.b1{
    background-color: firebrick;
    display: inline;
    color: #f0f0f0;
    width: 100px;
    border-radius: 15px;
    text-align: center;
    text-decoration: none;

    justify-content: center;
}

.show1 {display:block;}
.rem1 {display:none;}

.remove-history{
    display: flex;
    justify-content: right;
}

.btn-del{
    position: relative;
    font-family: Poppins;
    font-weight: bold;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);
    right: 60px;
    cursor: pointer;

    height: 40px;
    width: 150px;
    background-color: firebrick;
    border: none;
    border-radius: 25px;
    transition: 0.5s;
}

.btn-del:hover{
    background-color: rgb(143, 27, 27);
    
    transition: 0.5s;
}

.scroll {
    overflow: scroll;
    overflow-x: hidden;
    height: 400px;
}

select{
    font-family: Poppins;
    width: 20%;
    padding: 5px;
}

.filter{
    position: relative;
    margin-bottom: 20px;    
}


</style>

<body>
    <div class="a">
        <div class="dropdown-ver" id="del-drop">
            <div class="body">
                <label>Are you sure you want to clear all login history?</label>
                <p></p>
                <form method="GET" action="delete_login_history.php" id="form-delete-user">
                    <input type="hidden" name="id">
                </form>
            </div>
            <div class="b">
                <button type="button" style="display:inline-block;width:100px;" class="btn-edit" onclick="confirmDel(this)">Close</button>
                <button type="submit" style="display:inline-block;width:100px;" form="form-delete-user" class="btn-dgr">Delete</button>
            </div>
        </div>
    </div>

    <div class="navbar">
        <div style="position:relative;width:20px;left:30px;display:flex;align-items:center;"></div>
        <div class="logo"><img src="/bubble/media/BUBBLE.jpg" width="80px"></div>
        <div class="buttons">
            <form action="/bubble/interface/admin_homepage.php">
                <button type="submit" class="btn">Back to Homepage</button>
            </form>
        </div>
    </div>

    <div class="navbar-right">
        <div class="dropdown">
            <button onclick="myFunction()" class="dropbtn">Admin</button>
            <div id="myDropdown" class="dropdown-content">
                <a href="logout.php">Logout</a>
            </div>
        </div>
        <div style="position:relative;width:20px;right:30px;display:flex;align-items:center;"></div>
    </div>

    <script>
    function myFunction() {
        document.getElementById("myDropdown").classList.toggle("show");
    }
    window.onclick = function(event) {
        if (!event.target.matches('.dropbtn')) {
            var dropdowns = document.getElementsByClassName("dropdown-content");
            for (let i = 0; i < dropdowns.length; i++) {
                dropdowns[i].classList.remove('show');
            }
        }
    }
    </script>

    <div class="list">
        <div class="container">

            <center><h1>Login History</h1></center>

            <div class="filter">
                <center>
                <form method="GET" style="font-family:Poppins;">
                    <label style="margin-left: 2%;">Account:</label>
                    <select name="acc_id">
                        <option value="">All Accounts</option>
                        <?php
                        $connection = new mysqli("localhost", "root", "", "bh");
                        $accResult = $connection->query("SELECT DISTINCT ACC_ID FROM login_history ORDER BY ACC_ID ASC");
                        while ($acc = $accResult->fetch_assoc()) {
                            $selected = (isset($_GET['acc_id']) && $_GET['acc_id'] == $acc['ACC_ID']) ? 'selected' : '';
                            echo "<option value='{$acc['ACC_ID']}' $selected>{$acc['ACC_ID']}</option>";
                        }
                        ?>

                    </select>
                    <label style="margin-left: 2%;">Month:</label>
                    <select name="month">
                        <option value="">All Months</option>
                        <?php
                        $monthResult = $connection->query("SELECT DISTINCT DATE_FORMAT(DATE_OF_LOGIN, '%M %Y') AS month_label, DATE_FORMAT(DATE_OF_LOGIN, '%Y-%m') AS month_value FROM login_history ORDER BY DATE_OF_LOGIN DESC");
                        while ($m = $monthResult->fetch_assoc()) {
                            $selected = (isset($_GET['month']) && $_GET['month'] == $m['month_value']) ? 'selected' : '';
                            echo "<option value='{$m['month_value']}' $selected>{$m['month_label']}</option>";
                        }
                        ?>
                    </select>

                    <button style="margin-left: 5%;" type="submit" class="btn-edit">Filter</button>
                    <button type="button" class="btn-dgr" onclick="window.location.href='login_records.php'">Reset</button>

    </div>

                </form>
                </center>
            </div>
            

            <div class="scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Account Id</th>
                            <th>Email</th>
                            <th>Date of Login</th>
                            <th>Time of Login</th>
                            <th>Time of Logout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $accFilter = $_GET['acc_id'] ?? '';
$monthFilter = $_GET['month'] ?? '';

$sql = "SELECT ID, ACC_ID, EMAIL, DATE_OF_LOGIN, TIME_OF_LOGIN, TIME_OF_LOGOUT 
        FROM login_history 
        WHERE 1=1";  // 👈 add a base WHERE

if ($accFilter != '') {
    $sql .= " AND ACC_ID = '$accFilter'";
}

if ($monthFilter != '') {
    $sql .= " AND DATE_FORMAT(DATE_OF_LOGIN, '%Y-%m') = '$monthFilter'";
}

$sql .= " ORDER BY (TIME_OF_LOGOUT IS NULL) DESC, DATE_OF_LOGIN DESC, ID DESC";

$result = $connection->query($sql);

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "
        <tr>
            <td>{$row['ACC_ID']}</td>
            <td>{$row['EMAIL']}</td>
            <td>{$row['DATE_OF_LOGIN']}</td>
            <td>{$row['TIME_OF_LOGIN']}</td>
            <td>{$row['TIME_OF_LOGOUT']}</td>
        </tr>";
    }
} else {
    echo "<tr><td colspan='5' style='text-align:center;'>No data found</td></tr>";
}

                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function confirmDel(self){
        document.getElementById("del-drop").classList.toggle("show1");
    }
    </script>  
</body>
</html>

<?php
}
else {
    header("Location: login.php");
    exit();
}
?>