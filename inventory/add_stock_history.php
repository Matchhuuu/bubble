<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        .highlight {
            background-color: yellow;
            font-weight: bold;
        }
    </style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <link rel="stylesheet" href="/bubble/interface/admin_homepage.css">
    <link rel="stylesheet" href="/bubble/fonts/fonts.css">
    
    <link rel="icon" href=/bubble/media/BUBBLE.jpg></link>
    <title>Stock History</title>
</head>
<script>
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
    z-index: 10px;
    display: flex;
    justify-content:right;

}

h1{
    margin-bottom: 5px;
    padding-left: 50px;
}

.menu{
    position: relative;
    top: 100px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: auto;
    width: 100%;
    height: 400px;
    
}

.btn-main{
    font-family: Poppins;
    font-weight: bold;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);
    font-size: larger;

    height: 60px;
    width: 400px;
    background-color: #337609;
    border: none;
    border-radius: 25px;
    transition: 0.5s;
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

.btn:hover, .btn-main:hover {
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

.input-group{
    padding: 10px;
    display: flex;
    margin-left: 40px;
}

.form-outline{
    width: 80%;
}

input{
    font-family: Poppins;
    font-weight: bold;
    background-color: transparent;
    border-radius: 25px;
    border: 2px #337609 solid ;
    margin-bottom: 10px;
    color: #181818;
    width: 300px;
    height: 30px;
    text-indent: 10px;
    outline: none;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
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
    cursor: pointer;
}

.btn-edit:hover{
    background-color: rgb(79, 128, 79);
}

.btn-dgr{
    text-decoration: none;
    background-color: rgb(91, 149, 91); 
    padding: 3px;
    border-radius: 5px;
    color: #f0f0f0;
    border-color: transparent;
    font-family: Poppins;
    cursor: pointer;
    transition: 0.5s;
}

.btn-dgr:hover{
    background-color: rgb(79, 128, 79);
    transition: 0.5s;
}

.a {
    position: fixed;
    width:50%;
    height: 50%;
    z-index: 15;
    left: 25%;
    top: 25%;
}

.dropdown-ver{
    display: none;
    position: fixed;
    background-color: #f1f1f1;
    width: 45.5%;
    padding: 30px;
    margin: auto;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1;
    top:200px;

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

.main{
    position: relative; 
    top: 30px;
}

h1, h2, h3{
    font-size: 80px;
}

.scroll {
    overflow: scroll;
    overflow-x: hidden;
    height: 400px;
}

.btn-aa{
    position: relative;
    font-family: Poppins;
    font-weight: bold;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);
    right: 60px;

    height: 40px;
    width: auto;
    background-color:  rgb(91, 149, 91);
    border: none;
    border-radius: 25px;
    transition: 0.5s;

}

.btn-bb{
    position: relative;
    font-family: Poppins;
    font-weight: bold;
    color: #f0f0f0;
    box-shadow: 3px 4px 11px 1px rgba(0,0,0,0.28);
    right: 60px;
    margin-right: 15px;
    z-index: 40;

    height: 40px;
    width: auto;
    background-color: firebrick;
    border: none;
    border-radius: 25px;
    transition: 0.5s;

}

.btn-aa:hover{
    background-color: rgb(91, 129, 91);
    transition: 0.5s;
}

.btn-bb:hover{
    background-color: rgb(90, 0, 20);
    transition: 0.5s;
}
</style>

<body>

        <div class="navbar">
            <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
            <div class="buttons">
                <form action="/bubble/inventory/current_inventory.php"><button type="submit" class="btn"> Back </button></form>
            </div>
            
        </div>

        <div class="navbar-right">
            <div class="dropdown">
                <button onclick="myFunction()" class="dropbtn">Admin</button>
                <div id="myDropdown" class="dropdown-content">
                <a href="/bubble/interface/logout.php">Logout</a>
                </div>
            </div>
            <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
        </div>

<div class="main">
    <h1>Stock History</h1>
   
    <div class="input-group">
        <div class="form-outline">
            <input type="text" id="searchInput" class="form-control mb-3" placeholder="Search by Product Name or ID">
        </div>
    </div>

    <!--
    <div class="remove-history" style="display: flex; justify-content: right;">
        <form action="remove_rec.php"><button type="submit" class="btn-bb"> &nbsp; Remove Receipt Record &nbsp;</button></form>
        <form action="dl_stock_his.php"><button type="submit" class="btn-aa"> &nbsp; Download Receipt Record &nbsp;</button></form>
    </div>
-->

<div class="scroll">
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th>Quantity Added</th>
                    <th>Total Price</th>
                    <th>Date Added</th>
                    <th>Time Added</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $servername = "localhost";
        $username = "root";
        $password = "";
        $database = "bh";

        
        $connection = new mysqli($servername, $username, $password, $database);

        
        if ($connection->connect_error) {
            die("Connection failed: " . $connection->connect_error);
        }

        
        $sql_returned_goods = "SELECT *
                               FROM stock_history
                               ORDER BY DATE_ADD DESC";
        $result_returned_goods = $connection->query($sql_returned_goods);

        if (!$result_returned_goods) {
            die("Invalid query: " . $connection->error);
        }

    
        while ($row = $result_returned_goods->fetch_assoc()) {
            
                echo "
                <tr>
                    <td>{$row['PROD_ID']}</td>
                    <td>{$row['PROD_NAME']}</td>
                    <td>{$row['QTY_ADDED']}</td>
                    <td>{$row['TOT_PRICE']}</td>
                    <td>{$row['DATE_ADD']}</td>
                    <td>{$row['TIME_ADD']}</td>
                
                </tr>
                ";
        }
        ?>
        </tbody>
    </table>
</div>    
    <div class="a">
        <div class="dropdown-ver" id="del-drop">
                <div class="body">
                    <p>Update Item</p>
                    <form action="add_inventory.php" id="form-delete-user">
                        <div>
                            <label>Item Id  </label>
                            <input type="text" style="width: 20%; border-radius: 5px;" name="id">
                        </div>                                                                                    
                    </form>
                </div>
                <div class="b">
                    <button type="button" style="display:inline-block; width: 100px; align-items:center" class="btn-edit" onclick="confirmDel(this)">Close</button>
                    <button type="submit" style="display:inline-block; width: 100px; align-items:center" form="form-delete-user" class="btn-edit">Edit</button>

                </div>
        </div>
    </div>
</div>
<script>
    function close() {
                document.getElementById("del-drop").classList.toggle("rem1");
            }

    function confirmDel(self){
        var id = self.getAttribute("data-id");

        document.getElementById("form-delete-user").id.value = id;

        document.getElementById("del-drop").classList.toggle("show1");

    }

    // Search function for filtering table rows
    document.getElementById("searchInput").addEventListener("keyup", function() {
        var input, filter, table, tr, td, i, txtValue;
        input = document.getElementById("searchInput");
        filter = input.value.toUpperCase();
        table = document.querySelector("table");
        tr = table.getElementsByTagName("tr");
        
        for (i = 1; i < tr.length; i++) { // Start from index 1 to skip the header row
            var found = false;
            var tds = tr[i].getElementsByTagName("td");
            for (var j = 0; j < tds.length - 1; j++) { // Loop through all cells except actions
                td = tds[j];
                if (td) {
                    txtValue = td.textContent || td.innerText;
                    var index = txtValue.toUpperCase().indexOf(filter);
                    if (index > -1) {
                        found = true;
                        // Highlight the search term
                        var highlighted = txtValue.substring(0, index) + "<span class='highlight'>" + txtValue.substring(index, index + filter.length) + "</span>" + txtValue.substring(index + filter.length);
                        td.innerHTML = highlighted;
                    } else {
                        // Remove any previous highlighting if the search term isn't found
                        td.innerHTML = txtValue;
                    }
                }
            }
            // Show row if search term found, hide if not
            tr[i].style.display = found ? "" : "none";
        }
    });

</script>
</body>
</html>