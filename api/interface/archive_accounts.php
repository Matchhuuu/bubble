<?php
session_start();
$servername = "localhost";
$username = "root";
$password = "";
$database = "bh";

// Create Connection
$connection = new mysqli($servername, $username, $password, $database);

if ($connection->connect_error) {
    die("Connection Failed: " . $connection->connect_error);
}

$sql = "SELECT * FROM acc_archive";
$result = $connection->query($sql);

if (!$result) {
    die("Invalid Query: " . $connection->error);
}

if(isset($_SESSION['ACC_ID'])  && isset($_SESSION['EMAIL'])){ ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href=/bubble/media/BUBBLE.jpg></link>
    <link rel="stylesheet" href="user_accounts.css">
    <link rel="stylesheet" href="/bubble/fonts/fonts.css">

    <title>Archived Accounts</title>
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
    
    position: relative;
    background-color: rgb(91, 149, 91);
    padding: 3px;
    border-radius: 5px;
    color: #f0f0f0;
    border-color: transparent;
    margin: 5px;
    font-family: Poppins;
}

.btn-edit:hover{
    background-color: rgb(79, 128, 79);
}

.btn-dgr{
    
    text-decoration: none;
    background-color:firebrick;
    padding: 3px;
    border-radius: 5px;
    color: #f0f0f0;
    border-color: transparent;
    margin: 5px;
    font-family: Poppins;
    cursor: pointer;
}

.btn-dgr:hover{
    background-color: rgb(143, 27, 27);
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

.pass-col {
    width: 200px;
    word-break: break-all; 
}

</style>

<body>
                
<div class="a">
    <div class="dropdown-ver" id="del-drop">
            <div class="body">
                <Label>Are you sure you want to DELETE this account FOREVER?</Label>
                <p></p>
                <form method="GET" action="delete_account.php" id="form-delete-user">
                    <input type="hidden" name="id">
                </form>
            </div>
            <div class="b">
                <button type="button" style="display:inline-block; width: 100px; align-items:center" class="btn-edit" onclick="confirmDel(this)">Close</button>
                <button type="submit" style="display:inline-block; width: 100px; align-items:center" form="form-delete-user" class="btn-dgr">Delete</button>

            </div>
    </div>
</div>
                

        <div class="navbar">
            <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
            <div class="logo"><img src="/bubble/media/BUBBLE.jpg" width="80px"></div>
            <div class="buttons">
                <form action="/bubble/interface/user_accounts.php"><button type="submit" class="btn"> Back to Accounts </button></form>
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
    function close() {
        document.getElementById("del-drop").classList.toggle("rem1");
    }

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

      
    <center><h1 style="font-size: 30px;">Archived Accounts</h1></center>

    <table class="table">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Role</th>
                <th>Email</th>
                <th>Password</th>
                <th>Contact Number</th>
                <th>Birthday</th>
                <th>Options</th>
            </tr>
        </thead>
        <tbody>

        <?php
            $servername = "localhost";
            $username = "root";
            $password = "";
            $database = "bubblehideout";

            // Create Connection
            $connection = new mysqli($servername, $username, $password, $database);

            // Check Connection
            if ($connection->connect_error) {
                die("Connection Failed: " . $connection->connect_error);
            }

            $sql = "SELECT * FROM acc_archive";
            $result = $connection->query($sql);

            if (!$result) {
                die("Invalid Query: " . $connection->error);
            }

            while($row = $result->fetch_assoc()) {
                echo "
                <tr>
                    <td>$row[FNAME]</td>
                    <td>$row[LNAME]</td>
                    <td>$row[ROLE]</td>                
                    <td>$row[EMAIL]</td>
                    <td class='pass-col'>$row[PASS]</td>
                    <td>$row[CNTC_NUM]</td>
                    <td>$row[BDAY]</td> 
                    <td>
                    <center>
                        <a class='btn-edit' style='display:inline-block; width:30px; height: 30px; font-size: 20px; font-weight: bold;' href='/interface/restore_acc.php?id=$row[ACC_ID]'>↺</a>
                        <a class='btn-dgr' style='display:inline-block; width:30px; height: 30px; font-size: 20px;' data-id='$row[ID]' onclick='confirmDel(this)'>✖</a>
                    </center>
                    </td> 
                                
                </tr>
                ";
            }

            ?>
        </tbody>

    </table>
</div>
</div>
                            

<script>
    function confirmDel(self){
        var id = self.getAttribute("data-id");

        document.getElementById("form-delete-user").id.value = id;

        document.getElementById("del-drop").classList.toggle("show1");

    }
</script>          
</div>



        

        
</body>
</html>

<?php
}

else  {
    header("Location: login.php");
    exit();
}