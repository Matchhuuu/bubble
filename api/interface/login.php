<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="icon" href= "/media/BUBBLE.jpg">

    <link rel="stylesheet" href="/fonts/fonts.css">
    <title>Bubble Hideout Login</title>
</head>
<style>
    
.box {
    position: relative;
    padding:15px; 
    border-radius: 15px;


    margin: auto;
    width: 30%;
}


label {
    color: black;
    position: relative;
    text-indent: 40%;
    font-weight: bold;
    font-size: 15px;

}

.input {
    border-color: transparent;
    border-radius: 25px;
    background-color: #337609;
    margin-bottom: 10px;
    color: #f0f0f0;
    height: 30px;
    width: 100%;
    text-indent: 10px;
    outline: none;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
}


::placeholder {
    color: #f0f0f0;
}


.btn {
    margin-top: 40px;
    background:#2c2c2c; 
    font-family: Poppins;
    width: 100px; 
    height: 40px;
    color: white; 
    border: none;
    border-radius: 10px;
    font-weight: bold;
    box-shadow: 0px 5px 11px 1px rgba(0,0,0,0.28);
}

.btn:hover {
    background:#181818; 
}

.img-bbl {
    padding-top: 30px;
    width: 30%;
    height: 30%;

}

.footer {
    color: #181818;
    text-align: center;
    font-size: 12px;
    
    width: 100%;
    height: 7%;
    position: absolute; 
    bottom: 0; 
    left: 0; 
    z-index: 10;
}

.check{
    width: 17px;
    height: 17px;
    accent-color: #337609;
}

.toggle{
    display: flex;
    justify-content: right;
    
}

.aa{
    position: relative;
    display: flex;
    justify-content: center;
    
}

.bb{
    position: relative;
    height: 10px;
}
</style>

<body>
    <center> <div class="img-bbl">
        <img src="/media/BUBBLE.jpg" width="200px" height="200px"> 

    </div>
    <br></center>

    <div class="box">

        <form action="login_conn.php" method="post">

            <?php if (isset($_GET['error'])) { ?>
                <p class="error" 
                
                style=" background: #181818;
                        text-indent: 10px;
                        color: #f0f0f0;
                        padding: 5px;
                        width: 100%;
                        border-radius: 15px;"
                
                
                > <?php echo $_GET['error']; ?> </p>
            <?php } ?>

            <div class="row mb-3">
                <label>Username</label>
                <div class="col-sm-3">
                    <input type="text" class="input" style="font-family: Poppins;" autocomplete="off" name="name" placeholder="Enter Username">

                </div>
            </div>


            <div class="row mb-3">
                <label>Password</label>
                <div class="col-sm-3">
                    <input type="password" id="pass_input" class="input" style="font-family: Poppins;" name="pass"  placeholder="Enter Password">
                    <div class="toggle">
                        <div class="aa">
                            <input class="check" type="checkbox" onclick="Toggle()" accesskey="c">
                        </div>
                        <div class="bb">
                            <label>Show Password</label>
                        </div>
                    </div>
                </div>
            </div>

            <br>

            <center><button type="submit" class="btn"> LOGIN </button></center>

        </form>
    </div>



<script>
    function Toggle() {
        var x = document.getElementById("pass_input");
        if (x.type === "password") {
            x.type = "text";
        } 
        else {
            x.type = "password";
        }
    }
</script>
    
</body>



   


</html>
