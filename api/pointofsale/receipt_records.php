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
    
    <link rel="stylesheet" href="/interface/admin_homepage.css">
    <link rel="stylesheet" href="/fonts/fonts.css">
    
    <link rel="icon" href=/media/BUBBLE.jpg></link>
    <title>Receipt Records</title>
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
                <form action="/interface/admin_homepage.php"><button type="submit" class="btn"> Back </button></form>
            </div>
            
        </div>

        <div class="navbar-right">
            <div class="dropdown">
                <button onclick="myFunction()" class="dropbtn">Admin</button>
                <div id="myDropdown" class="dropdown-content">
                <a href="/interface/logout.php">Logout</a>
                </div>
            </div>
            <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
        </div>

<div class="main">
    <h1>Reciept Records</h1>
    <input style="margin-left: 50px; margin-bottom: 10px;" id="searchInput" placeholder="Search Order Id">

<div class="remove-history" style="display: flex; justify-content: right;">
    <button type="button" class="btn-aa" onclick="openExportModal()"> &nbsp; Download Receipt Record &nbsp;</button>
</div>

<!-- Export Modal -->
<div class="a" style="z-index: 30;">
    <div class="dropdown-ver" id="exportModal">
        <div class="body">
            <p style="font-size:20px; font-weight:bold;">Export Receipt Records</p>
            <form id="exportForm" method="POST">
                <div>
                    <label for="month">Select Month:</label>
                    <select name="month" id="month" style="width: 60%; border-radius: 5px;"></select>
                </div>
                <br>
                <div>
                    <label for="day">Select Day:</label>
                    <select name="day" id="day" style="width: 60%; border-radius: 5px;"></select>
                </div>
            </form>
        </div>
        <div class="b">
            <button type="button" class="btn-edit" onclick="closeExportModal()">Close</button>
            <button type="submit" form="exportForm" formaction="export_pdf.php" class="btn-edit">Export PDF</button>
            <button type="submit" form="exportForm" formaction="export_excel.php" class="btn-edit">Export Excel</button>
        </div>
    </div>
</div>



    <div class="scroll">
    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Order ID</th>
                <th>Product Ordered</th>
                <th>Size</th>
                <th>Quantity</th>
                <th>Total Price</th>
                <th>Date and Time</th>
            </tr>
        </thead>
        
        <tbody>
        <?php
        $servername = "mysql.hostinger.com";
        $username = "u412787669_soshe";
        $password = "6!cLt88>E++";
        $database = "u412787669_soshe";

        
        $connection = new mysqli($servername, $username, $password, $database);

        
        if ($connection->connect_error) {
            die("Connection failed: " . $connection->connect_error);
        }

        
        $sql_returned_goods = "SELECT order_id, menu_item_id, size_id, quantity, price, created_at
                               FROM customer_order_items
                               ORDER BY created_at DESC";
        $result_returned_goods = $connection->query($sql_returned_goods);

        if (!$result_returned_goods) {
            die("Invalid query: " . $connection->error);
        }

    
        while ($row = $result_returned_goods->fetch_assoc()) {

                $total_price = $row['quantity'] * $row['price'];
            
                echo "
                <tr>
                    <td>{$row['order_id']}</td>
                    <td>{$row['menu_item_id']}</td>
                    <td>{$row['size_id']}</td>
                    <td>{$row['quantity']}</td>
                    <td>Php {$total_price}</td>
                    <td>{$row['created_at']}</td>
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

function openExportModal() {
    document.getElementById("exportModal").classList.toggle("show1");
}

function closeExportModal() {
    document.getElementById("exportModal").classList.remove("show1");
}

// Populate month/day options dynamically from DB
window.onload = function() {
    fetch("get_available_dates.php")
        .then(response => response.json())
        .then(data => {
            let monthSelect = document.getElementById("month");
            let daySelect = document.getElementById("day");

            // Clear old options
            monthSelect.innerHTML = "<option value=''>All</option>";
            daySelect.innerHTML = "<option value=''>All</option>";

            // Populate months
            data.months.forEach(month => {
                let opt = document.createElement("option");
                opt.value = month;
                opt.text = month;
                monthSelect.appendChild(opt);
            });

            // Populate days
            data.days.forEach(day => {
                let opt = document.createElement("option");
                opt.value = day;
                opt.text = day;
                daySelect.appendChild(opt);
            });
        });
};


</script>
<div class="footer" style=" color: #181818;
                                    text-align: center;
                                    font-size: 12px;
                                
                                    width: 100%;
                                    height: 10%;
                                    position: absolute; 
                                    bottom: 0; 
                                    left: 0; 
                                    z-index: 10;">

    <p>Bubble Hideout© 2024</p>
</div>
</body>
</html>
