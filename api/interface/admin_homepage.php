<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/session_handler.php';
include "db_conn.php";

if(isset($_SESSION['ACC_ID']) && isset($_SESSION['EMAIL'])) {
    
    // <CHANGE> Add database query to get logged-in user's name
    $sql = "SELECT fname, lname FROM accounts WHERE ACC_ID = " . $_SESSION['ACC_ID'];
    $result = $conn->query($sql);
    
    if($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $fname = htmlspecialchars($user['fname']);
        $lname = htmlspecialchars($user['lname']);
        $full_name = $fname . " " . $lname;
    } else {
        $full_name = "Admin";
    }
?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    

    <link rel="icon" href="/media/BUBBLE.jpg"> </link>
    
    <link rel="stylesheet" href="/fonts/fonts.css">

        <title>Admin Interface</title>
    </head>
<style>
        .left-img {
            position: relative;

            width: 45%;
            display: flex;
            justify-content: space-evenly;
            align-items: center;
        }

        .right-button {
            position: relative;

            width: 55%;
            height: 400px;
            display: flex;
            justify-content: space-evenly;
            align-items: center;
        }

        .menu {
            position: relative;
            top: 100px;

            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 90%;
            height: 500px;
        }

        .navbar {
            background-color: #7e5832;
            width: 100%;
            height: 80px;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 10;
            box-shadow: 0px 5px 11px 1px rgba(0, 0, 0, 0.28);
            display: flex;
            justify-content: left;

        }

        .navbar-right {
            width: 50%;
            height: 80px;
            position: absolute;
            top: 0;
            right: 0;
            z-index: 11;
            display: flex;
            justify-content: right;

        }

        .buttons {
            position: relative;
            width: 330px;
            left: 30px;

            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn {

            font-family: Poppins;
            font-weight: bold;
            color: #f0f0f0;
            box-shadow: 3px 4px 11px 1px rgba(0, 0, 0, 0.28);

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

        /* Dropdown Button */
        .dropbtn {
            background-color: transparent;
            color: #f0f0f0;
            padding: 16px;
            font-size: 16px;
            min-width: 110px;
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
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            z-index: 1;
            top: 80px;

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
        .show {
            display: block;
        }

        .pos,
        .inv {
            height: 100%;
            width: 450px;
            border: none;
            border-radius: 25px;

        }

        h4 {
            font-family: BoldFont;
            font-size: 30px;
            margin: 20px 0;
        }

        .btn1 {

            font-family: Poppins;
            font-size: 20px;
            color: #f0f0f0;
            box-shadow: 3px 4px 11px 1px rgba(0, 0, 0, 0.28);

            height: 60px;
            width: 90%;
            margin: 20px;
            background-color: #337609;
            border: none;
            border-radius: 20px;
            transition: 0.5s;
        }

        .btn-notif {

            position: relative;

            font-family: Poppins;
            font-size: 20px;
            color: #f0f0f0;
            box-shadow: 3px 4px 11px 1px rgba(0, 0, 0, 0.28);

            height: 60px;
            width: 90%;
            margin: 20px;
            margin-bottom: 15px;
            background-color: #337609;
            border: none;
            border-radius: 20px;
            transition: 0.5s;

            display: flex;
            justify-content: center;
            align-items: center;
        }

        .btn-notifa {

            position: relative;

            font-family: Poppins;
            font-size: 20px;
            color: #f0f0f0;
            box-shadow: 3px 4px 11px 1px rgba(0, 0, 0, 0.28);

            height: 60px;
            width: 90%;
            margin: 40px;
            left: -20px;
            background-color: #337609;
            border: none;
            border-radius: 20px;
            transition: 0.5s;

            display: flex;
            justify-content: center;
            align-items: center;
        }

        .btn1:hover,
        .btn-notif:hover,
        .btn-notifa:hover {
            background-color: #1d3c09;
            transition: 0.5s;
        }

        .notif,
        .notif2 {
            position: absolute;
            background-color: red;
            border-radius: 50px;
            color: #f0f0f0;
            padding: 0 5px;
            display: flex;
            justify-content: center;
            align-items: center;

            top: -10px;
            right: -10px;

            width: auto;
            min-width: 30px;
            height: 40px;
            font-family: Poppins;
            font-size: 13px;
            font-weight: bold;

        }

        .notif-hide {
            display: none;
        }

        .footer {
            color: #181818;
            text-align: center;
            font-size: 12px;

            width: 100%;
            height: 10%;
            position: absolute;
            bottom: 100px;
            left: 0;
            z-index: 10;
        }

        .clock {
            font-size: 20px;
            color: white;
            font-weight: bold;
            font-family: Poppins;
            position: relative;
            width: auto;
            left: 37%;
            display: flex;
            align-items: center;

        }


        .flex-container {

            display: flex;

            justify-content: center;
            align-items: stretch;
            /* flex-flow: row wrap; */
            flex-direction: row;
            flex-wrap: wrap;

            height: 100%;
            padding: 15px;
            gap: 5px;
            padding-top: 10%;

        }

        .flex-container>div {
            border-radius: 5px;
            padding: 8px;
        }


        .item1 {
            /* flex:0 1 auto; */
            order: 1;
            align-self: auto;
        }

        .item2 {
            /* flex:0 1 auto; */
            order: 2;
        }

        .item3 {
            /* flex:0 1 auto; */
            order: 3;
        }

        .title {
            padding: 10px;
        }

        .title h4 {
            font-size: clamp(1rem, 2vw + 0.5rem, 1.5rem);
        }
        
        .welcome-float {
            position: fixed;
            top: 50px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #1d3c09;
            color: white;
            padding: 15px 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            z-index: 9999;
            font-size: 16px;
            font-weight: 500;
            animation: fadeOut 5s ease-in-out forwards;
        }
        
        .welcome-float p {
            margin: 0;
            padding: 0;
        }

@keyframes fadeOut {
    0% {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    85% {
        opacity: 1;
        transform: translateX(-50%) translateY(0);
    }
    100% {
        opacity: 0;
        transform: translateX(-50%) translateY(-20px);
    }
}
    </style>

    <body>
        
        <div id="welcome-notification" class="welcome-float">
            <p>Welcome Admin <?php echo $full_name; ?></p>
        </div>

        <div class="navbar">
            <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
            <div class="buttons">
                <form action="/interface/user_accounts.php"><button type="submit" class="btn"> User Accounts </button></form>
                <form action="/interface/login_records.php"><button type="submit" class="btn"> Login History </button></form>
                
            </div>
            
        </div>

        <div class="navbar-right">
            
            <div id="clock" class="clock"></div>
            <div class="dropdown">
                <button onclick="myFunction()" class="dropbtn">Admin</button>
                <div id="myDropdown" class="dropdown-content">
                    <a href="logout.php">Logout</a>
                </div>
            </div>


            <div style="position: relative; width: 20px; right: 30px; display: flex; align-items: center;"></div>
        </div>



        <div class="flex-container">
   
                <div class="item1">
                    <div class="logo"><img src="/media/BUBBLE.jpg" width="500px"></div>
                </div>

                <div class="item2">
                    <div class="pos">
                        <center>
                        <div class="title">
                            <h4>Menu and Order Management</h4>
                        </div>
                        <form action="/pointofsale/food2.php"><button type="submit" class="btn1"> Item List </button></form>
                        <form action="/pointofsale/new_receipt_records.php"><button type="submit" class="btn1"> Order Records </button></form>
                        <form action="/pointofsale/sales.php"><button type="submit" class="btn1"> Sale Records </button></form>
                        </center>
                    </div>
                </div>
                <div class="item3">
                    <div class="inv">
                        <center>    
                        <div class="title">
                            <h4>Inventory Management</h4>
                        </div>
                        <form action="/inventory/summary.php"><button type="submit" class="btn1"> Inventory Summary</button></form>

                        <form action="/inventory/needed_inventory.php">
                            <button type="submit" class="btn-notif"> Stocks to Order 
                                <div class = "notif" id = "notif"></div> 
                            </button>                
                        </form>

                        <form action="/inventory/current_inventory.php">
                            <button type="submit" class="btn-notifa"> Current Stocks
                                <div class = "notif2" id = "notif2"></div> 
                            </button>
                        </form>
                        </center>
                    </div>
                </div>

            </div>
    <script>
    // Remove the notification after 5 seconds
    setTimeout(function() {
        var notification = document.getElementById('welcome-notification');
        if(notification) {
            notification.remove();
        }
    }, 5000);
</script>
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

    function fetchNotifications() {
    const x = new XMLHttpRequest();
    x.open("GET", "notifs.php", true);
    x.onload = function () {
        if (this.status === 200) {
            const data = JSON.parse(this.responseText);

            // Update Notifications
            const notifElem = document.getElementById('notif');
            const notif2Elem = document.getElementById('notif2');

            if (data.total > 0) {
                notifElem.style.display = 'flex';
                notifElem.innerText = data.total;
            } else {
                notifElem.style.display = 'none';
            }

            if (data.total1 > 0) {
                notif2Elem.style.display = 'flex';
                notif2Elem.innerText = data.total1;
            } else {
                notif2Elem.style.display = 'none';
            }
        }
    };
    x.send();
}

setInterval(fetchNotifications, 1000);
fetchNotifications();

function updateClock() {
        const now = new Date();
        let hours = now.getHours();
        const minutes = now.getMinutes().toString().padStart(2, '0');
        const seconds = now.getSeconds().toString().padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12 || 12; // convert 0 to 12
        document.getElementById('clock').textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

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
