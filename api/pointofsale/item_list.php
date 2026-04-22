<?php
session_start();
include "db_conn.php";

if(isset($_SESSION['ACC_ID'])  && isset($_SESSION['EMAIL'])){ ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="icon" href=/media/BUBBLE.jpg></link>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="/fonts/fonts.css"></link>
    <title>Item List</title>
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
    z-index: 10px;
    display: flex;
    justify-content:right;

}

h1{
    margin-bottom: 5px;
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

.left-img{
    position: relative;
    width: 50%;
    display: flex;
    justify-content: space-evenly;
    align-items: center;
}

.right-button{
    position: relative;
    width: 50%;
    height: 300px;
    display: flex;
    flex-direction: column;
    justify-content: space-evenly;
    align-items: center;
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

</style>

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
        

    <div class="menu">
        <div class="left-img">
            <div class="logo"><img src="/media/BUBBLE.jpg" width="400px"></div>
        </div>

        <div class="right-button">
            <form action="/pointofsale/drinks.php"><button type="submit" class="btn-main">Drinks</button></form>
            <form action="/pointofsale/food.php"><button type="submit" class="btn-main">Food Items</button></form>
            <form action="/pointofsale/addons.php"><button type="submit" class="btn-main">Add Ons</button></form>
        </div>
    </div>

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

<?php
}

else  {
    header("Location: login.php");
    exit();
}
