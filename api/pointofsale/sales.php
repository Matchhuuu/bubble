<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href=/media/BUBBLE.jpg></link>
    <link rel="stylesheet" href="/fonts/fonts.css"></link>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>

    <title>Sale Records</title>
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

.scroll {
    overflow: scroll;
    overflow-x: hidden;
    height: 400px;
}

h1, h2, h3{
    font-size: 80px;
}

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
        


<?php include('db_conn.php')  ?>
        
    <div class="navbar">
        <div style="position: relative; width: 20px; left: 30px; display: flex; align-items: center;"></div>
        <div class="buttons">
            <form action="/interface/admin_homepage.php"><button type="submit" class="btn"> Back </button></form>
            <form action="/pointofsale/sales_forecast.php"><button type="submit" class="btn"> Forecast </button></form>
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
    <h1>Sale Records</h1>

    <div class="input-group">
        <div class="form-outline">
        <input style="width: 30%;" type="text" class="form-control" id="live_search" autocomplete="off" placeholder="Search...">
        </div>
    </div>

    <div id="searchresult" class="scroll"></div>

</div>
        


    <script type="text/javascript">
        $(document).ready(function(){

            $("#live_search").keyup(function(){
                var input = $(this).val();
                //alert(input);

                if(input !=""){
                    $.ajax({
                        url: "sales_search.php",
                        method: "POST",
                        data:{input:input},

                        success:function(data){
                            $("#searchresult").html(data);
                            $("#searchresult").css("display", "block");
                        }
                    });
                }    

                else{
                    $("#searchresult").css("display", "block");

                    $.ajax({
                        url: "sales_search.php",
                        method: "GET",
                        

                        success:function(data){
                            $("#searchresult").html(data);
                            $("#searchresult").css("display", "block");
                        }
                    });
                }

            });

        });

        $(document).ready(function(){
                        
            //alert(input);

            
            $.ajax({
                url: "sales_search.php",
                method: "GET",
                

                success:function(data){
                    $("#searchresult").html(data);
                    $("#searchresult").css("display", "block");
                }
            });
    
        });


    </script>
    
</body>
</html>
